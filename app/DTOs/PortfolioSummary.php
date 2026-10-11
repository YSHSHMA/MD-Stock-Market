<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

final class PortfolioSummary extends Data
{
  /**
   * @param Collection<int, CalculatedHolding> $holdings
   */
  public function __construct(
    public Collection $holdings,
    public float $totalValue,
    public float $totalCost,
    public float $totalPL,
    public float $totalPLPercent,
    public float $totalDayChange,
    public float $totalMTDChange,
    public float $totalYTDChange,
  ) {}
}
