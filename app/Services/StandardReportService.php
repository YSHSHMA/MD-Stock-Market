<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\CloudStorageServiceInterface;
use App\Contracts\StandardPortfolioCalculatorInterface;
use App\Contracts\StandardReportServiceInterface;
use App\Contracts\HttpTelegramServiceInterface;
use App\Contracts\SettingServiceInterface;
use App\DTOs\PortfolioSummary;
use App\Jobs\ProcessUserDailyReport;
use App\Jobs\SendDailyReport;
use App\Models\User;
use App\Traits\HasCurrencyFormatting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use League\Csv\Writer;
use Throwable;

final readonly class StandardReportService implements StandardReportServiceInterface
{
  use HasCurrencyFormatting;

  public function __construct(
    private StandardPortfolioCalculatorInterface $calculator,
    private HttpTelegramServiceInterface $telegramService,
    private SettingServiceInterface $settingService,
    private CloudStorageServiceInterface $cloudStorage
  ) {}

  /**
   * @param callable(string): void $onSent
   */
  public function sendDailyReports(callable $onSent): void
  {
    $userIds = User::whereNotNull('telegram_id')->pluck('id');

    foreach ($userIds as $userId) {
      $user = User::find($userId);
      if ($user) {
        ProcessUserDailyReport::dispatch($user);
        $onSent($user->name);
      }
    }
  }

  public function generateAndSendReport(User $user): void
  {
    $summary = $this->calculator->calculate($user);

    if ($summary) {
      $reportText = $this->formatAsText($summary);
      $csvUrl = null;

      try {
        $csvContent = $this->formatAsCSV($summary);
        $random = Str::random(12);
        $fileName = 'portfolio_' . now()->format('Ymd') . '_' . $user->id . '_' . $random . '.csv';
        $csvUrl = $this->cloudStorage->upload($fileName, $csvContent);
        $reportText .= "\n\n📂 *CSV Report*: [Download]({$csvUrl})";
      } catch (Throwable $e) {
        Log::error("Cloud upload failed: " . $e->getMessage());
      }

      // Send Telegram notification
      $this->telegramService->sendMessage($user->telegram_id, $reportText);

      // Send Email with CSV if user has email (Queued!)
      if ($user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
        SendDailyReport::dispatch($user, $reportText, $csvUrl)->onQueue('email');
      }
    }
  }

  public function generateDailyReport(User $user): ?string
  {
    $summary = $this->calculator->calculate($user);
    return $summary ? $this->formatAsText($summary) : null;
  }

  private function sanitizeCsvField(mixed $value): string
  {
    $value = (string) $value;
    if (in_array(substr(trim($value), 0, 1), ['=', '+', '-', '@', "\t", "\r"])) {
      return "'" . $value;
    }
    return $value;
  }

  private function formatAsText(PortfolioSummary $summary): string
  {
    $currency = $this->settingService->getCurrencySettings();
    $rate = $currency['exchange_rate'];
    $symbol = $currency['symbol'];

    $now = now();
    $message = "📊 *Daily Performance Report*\n";
    $message .= "_" . $now->format('l, F j, Y') . "_\n\n";

    foreach ($summary->holdings as $holding) {
      $symbolText = HttpTelegramService::escapeMarkdown($holding->symbol);
      $changeSymbol = $holding->dayChange >= 0 ? "📈" : "📉";
      $message .= "{$changeSymbol} *{$symbolText}*: {$symbol}" . number_format($holding->currentPrice * $rate, 2) . "\n";
      $message .= "   Qty: {$holding->quantity} | Value: {$symbol}" . number_format($holding->currentValue * $rate, 2) . "\n";
      $message .= "   Day: " . $this->formatCurrency($holding->dayChange * $rate, $symbol) . "\n";
      $message .= "   MTD: " . $this->formatCurrency($holding->mtdChange * $rate, $symbol) . "\n";
      $message .= "   YTD: " . $this->formatCurrency($holding->ytdChange * $rate, $symbol) . "\n\n";
    }

    $message .= "━━━━━━━━━━━━━━━\n";
    $message .= "💰 *Portfolio Value*: {$symbol}" . number_format($summary->totalValue * $rate, 2) . "\n";
    $message .= "📊 *Total P/L*: " . $this->formatCurrency($summary->totalPL * $rate, $symbol) . " (" . number_format($summary->totalPLPercent, 2) . "%)\n";
    $message .= "📅 *Day Change*: " . $this->formatCurrency($summary->totalDayChange * $rate, $symbol) . "\n";
    $message .= "📆 *MTD Change*: " . $this->formatCurrency($summary->totalMTDChange * $rate, $symbol) . "\n";
    $message .= "📈 *YTD Change*: " . $this->formatCurrency($summary->totalYTDChange * $rate, $symbol);

    return $message;
  }

  private function formatAsCSV(PortfolioSummary $summary): string
  {
    $currency = $this->settingService->getCurrencySettings();
    $rate = $currency['exchange_rate'];
    $code = $currency['code'];

    $csv = Writer::fromString('');

    $csv->insertOne([
      'Symbol',
      'Quantity',
      "Current Price ({$code})",
      "Current Value ({$code})",
      "Avg Cost ({$code})",
      "Total Cost ({$code})",
      "P/L ({$code})",
      'P/L %',
      "Day Change ({$code})",
      "MTD Change ({$code})",
      "YTD Change ({$code})",
      'Last Updated'
    ]);

    $csv->insertAll(collect($summary->holdings)->map(fn($holding) => [
      $this->sanitizeCsvField($holding->symbol),
      number_format($holding->quantity, 4, '.', ''),
      number_format($holding->currentPrice * $rate, 4, '.', ''),
      number_format($holding->currentValue * $rate, 2, '.', ''),
      number_format($holding->avgCost * $rate, 4, '.', ''),
      number_format($holding->totalCost * $rate, 2, '.', ''),
      number_format(($holding->currentValue - $holding->totalCost) * $rate, 2, '.', ''),
      number_format($holding->totalPLPercent, 2, '.', ''),
      number_format($holding->dayChange * $rate, 2, '.', ''),
      number_format($holding->mtdChange * $rate, 2, '.', ''),
      number_format($holding->ytdChange * $rate, 2, '.', ''),
      $holding->lastUpdated
    ])->toArray());

    return $csv->toString();
  }
}
