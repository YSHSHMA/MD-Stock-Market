<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\DailyMetric;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface EloquentMetricServiceInterface
{
  /**
   * @param array<string> $tickerIds
   * @return Collection<string, DailyMetric>
   */
  public function getLatestMetricsBulk(array $tickerIds): Collection;

  /**
   * @param array<string> $tickerIds
   */
  public function getFirstMetricOfMonthBulk(array $tickerIds, Carbon $date): Collection;

  /**
   * @param array<string> $tickerIds
   */
  public function getFirstMetricOfYearBulk(array $tickerIds, Carbon $date): Collection;

  /**
   * @param array<string> $tickerIds
   */
  public function getClosingMetricBeforeDateBulk(array $tickerIds, Carbon $date): Collection;
}
