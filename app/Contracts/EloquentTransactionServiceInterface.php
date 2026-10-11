<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

interface EloquentTransactionServiceInterface
{
  /**
   * @return Collection<int, Transaction>
   */
  public function getUserHoldings(string $userId): Collection;

  public function createTransaction(string $userId, string $tickerId, string $type, float|int $quantity, float|int $price, ?string $date = null): Transaction;

  public function getHistory(string $userId, int $perPage = 20): LengthAwarePaginator;

  /**
   * @return SupportCollection<string, float>
   */
  public function getAverageCostsBulk(string $userId): SupportCollection;

  public function getAverageCost(string $userId, string $tickerId): float;

  public function countTransactions(string $userId, string $tickerId): int;

  public function removeAllTransactionsForTicker(string $userId, string $tickerId): int;

  public function deleteTransaction(Transaction $transaction, string $userId): bool;

  /**
   * @return SupportCollection<string, array{buys: float, sells: float}>
   */
  public function getTransactionTotalsBulk(string $userId, ?Carbon $upToDate = null): SupportCollection;

  /**
   * @return SupportCollection<string, float>
   */
  public function getHoldingsAtDateBulk(string $userId, Carbon $date): SupportCollection;
}
