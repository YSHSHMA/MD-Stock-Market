<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EloquentMetricServiceInterface;
use App\Models\DailyMetric;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class EloquentMetricService implements EloquentMetricServiceInterface
{
  public function getLatestMetricForTicker(string $tickerId): ?DailyMetric
  {
    return $this->getQuery($tickerId)->first();
  }

  public function getFirstMetricOfMonth(string $tickerId, Carbon $date): ?DailyMetric
  {
    return $this->getQuery($tickerId)
      ->whereYear('date', $date->year)
      ->whereMonth('date', $date->month)
      ->first();
  }

  public function getFirstMetricOfYear(string $tickerId, Carbon $date): ?DailyMetric
  {
    return $this->getQuery($tickerId)
      ->whereYear('date', $date->year)
      ->first();
  }

  /**
   * @param array<string> $tickerIds
   * @return Collection<string, DailyMetric>
   */
  public function getLatestMetricsBulk(array $tickerIds): Collection
  {
    return $this->fetchBulkMetrics($tickerIds, 'MAX', 'latest_metrics');
  }

  /**
   * @param array<string> $tickerIds
   * @return Collection<string, DailyMetric>
   */
  public function getFirstMetricOfMonthBulk(array $tickerIds, Carbon $date): Collection
  {
    return $this->fetchBulkMetrics($tickerIds, 'MIN', 'first_metrics_month', function (Builder $query) use ($date) {
      $query->whereYear('date', $date->year)->whereMonth('date', $date->month);
    });
  }

  /**
   * @param array<string> $tickerIds
   * @return Collection<string, DailyMetric>
   */
  public function getFirstMetricOfYearBulk(array $tickerIds, Carbon $date): Collection
  {
    return $this->fetchBulkMetrics($tickerIds, 'MIN', 'first_metrics_year', function (Builder $query) use ($date) {
      $query->whereYear('date', $date->year);
    });
  }

  public function getClosingMetricBeforeDateBulk(array $tickerIds, Carbon $date): Collection
  {
    return $this->fetchBulkMetrics($tickerIds, 'MAX', 'closing_metrics_period', function (Builder $query) use ($date) {
      $query->where('date', '<=', $date);
    });
  }

  /**
   * Core logic to fetch bulk metrics using a subquery join.
   * Database-agnostic and optimized for performance.
   *
   * @param array<string> $tickerIds
   * @param "MIN"|"MAX" $aggregate
   */
  private function fetchBulkMetrics(
    array $tickerIds,
    string $aggregate,
    string $alias,
    ?callable $constraint = null
  ): Collection {
    $columnName = strtolower($aggregate) . "_date";

    $subquery = DailyMetric::query()
      ->whereIn('ticker_id', $tickerIds)
      ->when($constraint, fn($q) => $constraint($q))
      ->select('ticker_id', DB::raw("{$aggregate}(date) as {$columnName}"))
      ->groupBy('ticker_id');

    return DailyMetric::query()
      ->select('daily_metrics.*')
      ->joinSub($subquery, $alias, function ($join) use ($alias, $columnName) {
        $join->on('daily_metrics.ticker_id', '=', "{$alias}.ticker_id")
          ->on('daily_metrics.date', '=', "{$alias}.{$columnName}");
      })
      ->get()
      ->keyBy('ticker_id');
  }

  private function getQuery(string $tickerId): Builder
  {
    return DailyMetric::query()
      ->where('ticker_id', $tickerId)
      ->orderByDesc('date');
  }
}
