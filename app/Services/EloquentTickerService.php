<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EloquentTickerServiceInterface;
use App\Models\Ticker;
use Illuminate\Support\Facades\DB;

final readonly class EloquentTickerService implements EloquentTickerServiceInterface
{
  public function getOrCreateTicker(string $symbol): Ticker
  {
    $symbol = strtoupper($symbol);

    return DB::transaction(function () use ($symbol) {
      return Ticker::firstOrCreate(
        ['symbol' => $symbol],
        ['name' => $symbol]
      );
    });
  }

  public function findBySymbol(string $symbol): ?Ticker
  {
    return Ticker::where('symbol', strtoupper($symbol))->first();
  }
}
