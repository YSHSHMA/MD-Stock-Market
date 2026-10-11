<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CalculatedHolding;
use App\DTOs\PortfolioSummary;
use App\Models\DailyMetric;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

use App\Contracts\EloquentTransactionServiceInterface;
use App\Contracts\EloquentMetricServiceInterface;
use App\Contracts\StandardPortfolioCalculatorInterface;

use App\DTOs\MarketDataContext;

final readonly class StandardPortfolioCalculator implements StandardPortfolioCalculatorInterface
{
  public function __construct(
    private EloquentTransactionServiceInterface $transactionService,
    private EloquentMetricServiceInterface $metricService
  ) {}

  public function calculate(User $user, ?Carbon $date = null): ?PortfolioSummary
  {
    $date = $date ?? Carbon::now();
    $holdings = $this->transactionService->getUserHoldings($user->id);

    if ($holdings->isEmpty()) {
      return null;
    }

    $marketData = $this->fetchMarketData($date, $user->id, $holdings->pluck('ticker_id')->toArray());

    return $this->buildPortfolioSummary($holdings, $marketData);
  }

  /**
   * @param string[] $tickerIds
   */
  private function fetchMarketData(Carbon $date, string $userId, array $tickerIds): MarketDataContext
  {
    // 🎯 Clear boundaries: Start of current day's business logic
    $startOfToday = $date->copy()->startOfDay();
    $startOfMonth = $date->copy()->startOfMonth();
    $startOfYear = $date->copy()->startOfYear();

    // Reference metrics: we want the LAST available price BEFORE the period starts
    $mtdBaseMetrics = $this->metricService->getClosingMetricBeforeDateBulk($tickerIds, $startOfMonth->copy()->subDay());
    if ($mtdBaseMetrics->isEmpty()) {
      $mtdBaseMetrics = $this->metricService->getFirstMetricOfMonthBulk($tickerIds, $date);
    }

    $ytdBaseMetrics = $this->metricService->getClosingMetricBeforeDateBulk($tickerIds, $startOfYear->copy()->subDay());
    if ($ytdBaseMetrics->isEmpty()) {
      $ytdBaseMetrics = $this->metricService->getFirstMetricOfYearBulk($tickerIds, $date);
    }

    return new MarketDataContext(
      latest: $this->metricService->getLatestMetricsBulk($tickerIds),
      mtd: $mtdBaseMetrics,
      ytd: $ytdBaseMetrics,
      avgCosts: $this->transactionService->getAverageCostsBulk($userId),
      transactionTotals: $this->transactionService->getTransactionTotalsBulk($userId),
      mtdTotals: $this->transactionService->getTransactionTotalsBulk($userId, $startOfMonth),
      ytdTotals: $this->transactionService->getTransactionTotalsBulk($userId, $startOfYear),
      mtdHoldings: $this->transactionService->getHoldingsAtDateBulk($userId, $startOfMonth),
      ytdHoldings: $this->transactionService->getHoldingsAtDateBulk($userId, $startOfYear)
    );
  }

  private function buildPortfolioSummary(Collection $holdings, MarketDataContext $marketData): PortfolioSummary
  {
    $calculatedHoldings = collect();
    $totals = $this->initializeTotals();

    foreach ($holdings as $holding) {
      $calculated = $this->calculateHolding($holding, $marketData);

      if (!$calculated) {
        continue;
      }

      $calculatedHoldings->push($calculated);
      $this->aggregateTotals($totals, $calculated);
    }

    return $this->createSummaryDTO($calculatedHoldings, $totals);
  }

  private function calculateHolding($holding, MarketDataContext $marketData): ?CalculatedHolding
  {
    /** @var DailyMetric|null $latest */
    $latest = $marketData->latest->get($holding->ticker_id);

    if (!$latest) {
      return null;
    }

    $data = $this->computeHoldingMetrics(
      $holding->ticker_id,
      (float) $holding->total_quantity,
      (float) $latest->price,
      (float) ($marketData->avgCosts->get($holding->ticker_id) ?? 0.0),
      (float) $latest->change,
      $marketData,
    );

    return new CalculatedHolding(
      symbol: $holding->ticker->symbol,
      quantity: $data['quantity'],
      currentPrice: $data['currentPrice'],
      avgCost: $data['avgCost'],
      currentValue: $data['value'],
      totalCost: $data['cost'],
      totalPL: $data['pl'],
      totalPLPercent: $data['plPercent'],
      dayChange: $data['dayChange'],
      mtdChange: $data['mtdChange'],
      ytdChange: $data['ytdChange'],
      lastUpdated: $latest->date instanceof Carbon ? $latest->date->format('Y-m-d') : (string) $latest->date
    );
  }

  private function computeHoldingMetrics(
    string $tickerId,
    float $quantity,
    float $currentPrice,
    float $avgCost,
    float $dailyChange,
    MarketDataContext $marketData
  ): array {
    $valueCurrent = $quantity * $currentPrice;

    // 💡 Performance Logic: Realized + Unrealized Gains
    $totals = $marketData->transactionTotals->get($tickerId, ['buys' => 0.0, 'sells' => 0.0]);
    $totalPL = ($totals['sells'] + $valueCurrent) - $totals['buys'];

    // MTD Change = Current Absolute PL - Absolute PL at start of month
    $mtdMetric = $marketData->mtd->get($tickerId);
    $mtdQty = (float) $marketData->mtdHoldings->get($tickerId, 0.0);
    $mtdValueStart = $mtdMetric ? $mtdQty * (float) $mtdMetric->price : 0.0;
    $mtdTotals = $marketData->mtdTotals->get($tickerId, ['buys' => 0.0, 'sells' => 0.0]);
    $mtdPLStart = ($mtdTotals['sells'] + $mtdValueStart) - $mtdTotals['buys'];
    $mtdChange = $totalPL - $mtdPLStart;

    // YTD Change = Current Absolute PL - Absolute PL at start of year
    $ytdMetric = $marketData->ytd->get($tickerId);
    $ytdQty = (float) $marketData->ytdHoldings->get($tickerId, 0.0);
    $ytdValueStart = $ytdMetric ? $ytdQty * (float) $ytdMetric->price : 0.0;
    $ytdTotals = $marketData->ytdTotals->get($tickerId, ['buys' => 0.0, 'sells' => 0.0]);
    $ytdPLStart = ($ytdTotals['sells'] + $ytdValueStart) - $ytdTotals['buys'];
    $ytdChange = $totalPL - $ytdPLStart;

    return [
      'quantity' => $quantity,
      'currentPrice' => $currentPrice,
      'avgCost' => $avgCost,
      'value' => $valueCurrent,
      'cost' => $quantity * $avgCost,
      'pl' => $totalPL,
      'plPercent' => $avgCost > 0 ? (($currentPrice - $avgCost) / $avgCost) * 100 : 0,
      'dayChange' => $quantity * $dailyChange,
      'mtdChange' => $mtdChange,
      'ytdChange' => $ytdChange,
    ];
  }

  private function initializeTotals(): array
  {
    return [
      'value' => 0.0,
      'cost' => 0.0,
      'dayChange' => 0.0,
      'mtdChange' => 0.0,
      'ytdChange' => 0.0,
      'pl' => 0.0,
    ];
  }

  private function aggregateTotals(array &$totals, CalculatedHolding $holding): void
  {
    $totals['value'] += $holding->currentValue;
    $totals['cost'] += $holding->totalCost;
    $totals['dayChange'] += $holding->dayChange;
    $totals['mtdChange'] += $holding->mtdChange;
    $totals['ytdChange'] += $holding->ytdChange;
    $totals['pl'] += $holding->totalPL;
  }

  private function createSummaryDTO(Collection $holdings, array $totals): PortfolioSummary
  {
    // 💡 PL Percent on current portfolio should be Unrealized gain
    $unrealizedPL = $totals['value'] - $totals['cost'];
    $unrealizedPLPercent = $totals['cost'] > 0 ? ($unrealizedPL / $totals['cost']) * 100 : 0.0;

    return new PortfolioSummary(
      holdings: $holdings,
      totalValue: $totals['value'],
      totalCost: $totals['cost'],
      totalPL: $totals['pl'],
      totalPLPercent: $unrealizedPLPercent,
      totalDayChange: $totals['dayChange'],
      totalMTDChange: $totals['mtdChange'],
      totalYTDChange: $totals['ytdChange']
    );
  }
}
