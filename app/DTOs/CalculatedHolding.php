<?php

declare(strict_types=1);

namespace App\DTOs;

use Spatie\LaravelData\Data;

final class CalculatedHolding extends Data
{
  public function __construct(
    public string $symbol,
    public float $quantity,
    public float $currentPrice,
    public float $avgCost,
    public float $currentValue,
    public float $totalCost,
    public float $totalPL,
    public float $totalPLPercent,
    public float $dayChange,
    public float $mtdChange,
    public float $ytdChange,
    public string $lastUpdated,
  ) {}
}
