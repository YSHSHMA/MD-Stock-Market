<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\DailyMetric;
use App\Models\Ticker;

interface DefaultStockServiceInterface
{
  /**
   * @param callable(string): void $onUpdate
   */
  public function updateAllTickers(callable $onUpdate): void;

  public function updateTickerPrice(Ticker $ticker, ?string $date = null): ?DailyMetric;
}
