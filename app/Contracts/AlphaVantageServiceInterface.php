<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\StockQuote;
use App\DTOs\StockSearchMatch;
use Illuminate\Support\Collection;

interface AlphaVantageServiceInterface
{
  /**
   * @return Collection<int, StockSearchMatch>
   */
  public function searchTicker(string $keywords): Collection;

  public function getQuote(string $symbol): ?StockQuote;
}
