<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AlphaVantageServiceInterface;
use App\Contracts\DefaultStockServiceInterface;
use App\Jobs\UpdateStockPrice;
use App\Models\DailyMetric;
use App\Models\Ticker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class DefaultStockService implements DefaultStockServiceInterface
{
  public function __construct(private AlphaVantageServiceInterface $stockProvider) {}

  /**
   * @param callable(string): void $onUpdate
   */
  public function updateAllTickers(callable $onUpdate): void
  {
    $tickers = Ticker::all();

    if ($tickers->isEmpty()) {
      return;
    }

    foreach ($tickers as $index => $ticker) {
      // Dispatch job with staggered delay (AlphaVantage free tier limits)
      UpdateStockPrice::dispatch($ticker)
        ->delay(now()->addSeconds($index * config('settings.alphavantage.stagger_delay', 12)));

      $onUpdate($ticker->symbol);
    }
  }

  public function updateTickerPrice(Ticker $ticker, ?string $date = null): ?DailyMetric
  {
    return DB::transaction(function () use ($ticker, $date) {
      $quote = $this->stockProvider->getQuote($ticker->symbol);

      if (!$quote) {
        Log::warning("Failed to fetch data for {$ticker->symbol}");
        return null;
      }

      // 📅 Use the actual trading day from API if no specific date was requested
      $targetDate = $date ?? $quote->latestTradingDay;

      return DailyMetric::updateOrCreate(
        [
          'ticker_id' => $ticker->id,
          'date' => $targetDate,
        ],
        [
          'price' => $quote->price,
          'change' => $quote->change,
          'change_percent' => $quote->changePercent,
        ]
      );
    });
  }
}
