<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Ticker;

interface EloquentTickerServiceInterface
{
  public function getOrCreateTicker(string $symbol): Ticker;

  public function findBySymbol(string $symbol): ?Ticker;
}
