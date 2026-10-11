<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\EloquentTransactionServiceInterface;
use App\Exceptions\Domain\InsufficientHoldingsException;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

use App\Enums\TransactionType;

final readonly class EloquentTransactionService implements EloquentTransactionServiceInterface
{
  private const SQL_QUANTITY_CALC = "SUM(CASE WHEN type = ? THEN quantity ELSE -quantity END)";

  /**
   * @return Collection<int, Transaction>
   */
  public function getUserHoldings(string $userId): Collection
  {
    $calc = str_replace('?', "'" . TransactionType::BUY->value . "'", self::SQL_QUANTITY_CALC);

    return Transaction::where('user_id', $userId)
      ->select('ticker_id')
      ->selectRaw("ROUND($calc, 4) as total_quantity")
      ->groupBy('ticker_id')
      ->havingRaw("ROUND($calc, 4) > 0")
      ->with(['ticker'])
      ->get();
  }

  public function createTransaction(string $userId, string $tickerId, string $type, float|int $quantity, float|int $price, ?string $date = null): Transaction
  {
    $date = $date ? Carbon::parse($date) : now();

    return DB::transaction(function () use ($userId, $tickerId, $type, $quantity, $price, $date) {
      if ($type === TransactionType::SELL->value) {
        // 🔒 Granular Lock: lock only transactions for THIS specific ticker to allow parallel trading on other stocks
        Transaction::where('user_id', $userId)
          ->where('ticker_id', $tickerId)
          ->lockForUpdate()
          ->get();

        $calc = str_replace('?', "'" . TransactionType::BUY->value . "'", self::SQL_QUANTITY_CALC);

        $currentQuantity = Transaction::where('user_id', $userId)
          ->where('ticker_id', $tickerId)
          ->selectRaw("$calc as total")
          ->value('total') ?? 0.0;

        if ($quantity > (float) $currentQuantity) {
          throw new InsufficientHoldingsException("Insufficient holdings. You only have {$currentQuantity} units.");
        }
      }

      return Transaction::create([
        'user_id' => $userId,
        'ticker_id' => $tickerId,
        'type' => $type,
        'quantity' => $quantity,
        'price' => $price,
        'transaction_date' => $date,
      ]);
    });
  }

  /**
   * @return LengthAwarePaginator
   */
  public function getHistory(string $userId, int $perPage = 20): LengthAwarePaginator
  {
    return Transaction::where('user_id', $userId)
      ->with('ticker')
      ->orderBy('transaction_date', 'desc')
      ->paginate($perPage);
  }

  /**
   * Calculate weighted average cost for all tickers of a user in a single query.
   * Eliminates N+1 problem in PortfolioCalculator.
   * @return SupportCollection<string, float>
   */
  public function getAverageCostsBulk(string $userId): SupportCollection
  {
    return Transaction::where('user_id', $userId)
      ->where('type', TransactionType::BUY->value)
      ->select('ticker_id')
      ->selectRaw('ROUND(SUM(price * quantity) / SUM(quantity), 4) as avg_cost')
      ->groupBy('ticker_id')
      ->get()
      ->pluck('avg_cost', 'ticker_id');
  }

  public function getAverageCost(string $userId, string $tickerId): float
  {
    return (float) (Transaction::where('user_id', $userId)
      ->where('ticker_id', $tickerId)
      ->where('type', TransactionType::BUY->value)
      ->selectRaw('ROUND(SUM(price * quantity) / SUM(quantity), 4) as avg_cost')
      ->value('avg_cost') ?? 0.0);
  }

  public function countTransactions(string $userId, string $tickerId): int
  {
    return Transaction::where('user_id', $userId)
      ->where('ticker_id', $tickerId)
      ->count();
  }

  public function removeAllTransactionsForTicker(string $userId, string $tickerId): int
  {
    return (int) Transaction::where('user_id', $userId)
      ->where('ticker_id', $tickerId)
      ->delete();
  }

  public function deleteTransaction(Transaction $transaction, string $userId): bool
  {
    if ($transaction->user_id !== $userId) {
      return false;
    }

    return $transaction->delete() ?? false;
  }

  public function getTransactionTotalsBulk(string $userId, ?Carbon $upToDate = null): SupportCollection
  {
    $query = Transaction::where('user_id', $userId);

    if ($upToDate) {
      $query->where('transaction_date', '<', $upToDate);
    }

    $results = $query->select('ticker_id', 'type')
      ->selectRaw('SUM(price * quantity) as total_value')
      ->groupBy('ticker_id', 'type')
      ->get();

    $totals = collect();

    foreach ($results as $row) {
      if (!$totals->has($row->ticker_id)) {
        $totals->put($row->ticker_id, ['buys' => 0.0, 'sells' => 0.0]);
      }

      $current = $totals->get($row->ticker_id);
      if ($row->type === TransactionType::BUY->value) {
        $current['buys'] = (float) $row->total_value;
      } else {
        $current['sells'] = (float) $row->total_value;
      }
      $totals->put($row->ticker_id, $current);
    }

    return $totals;
  }

  public function getHoldingsAtDateBulk(string $userId, Carbon $date): SupportCollection
  {
    return Transaction::where('user_id', $userId)
      ->where('transaction_date', '<', $date)
      ->select('ticker_id')
      ->selectRaw("SUM(CASE WHEN type = ? THEN quantity ELSE -quantity END) as total", [TransactionType::BUY->value])
      ->groupBy('ticker_id')
      ->get()
      ->pluck('total', 'ticker_id');
  }
}
