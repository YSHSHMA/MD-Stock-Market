<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class MarketDataContext extends Data
{
  public function __construct(
    public Collection $latest,
    /** @var Collection<string, \App\Models\DailyMetric> */
    public Collection $mtd,
    /** @var Collection<string, \App\Models\DailyMetric> */
    public Collection $ytd,
    /** @var Collection<string, float> */
    public Collection $avgCosts,
    /** @var Collection<string, array{buys: float, sells: float}> */
    public Collection $transactionTotals,
    /** @var Collection<string, array{buys: float, sells: float}> */
    public Collection $mtdTotals,
    /** @var Collection<string, array{buys: float, sells: float}> */
    public Collection $ytdTotals,
    /** @var Collection<string, float> */
    public Collection $mtdHoldings,
    /** @var Collection<string, float> */
    public Collection $ytdHoldings
  ) {}
}
