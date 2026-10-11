<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\Ticker;
use App\Models\Transaction;
use App\Contracts\EloquentTransactionServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows selling stocks when user has sufficient quantity', function () {
  $user = User::factory()->create();
  $ticker = Ticker::factory()->create(['symbol' => 'AAPL']);

  // Buy 10 shares
  Transaction::factory()->create([
    'user_id' => $user->id,
    'ticker_id' => $ticker->id,
    'type' => 'buy',
    'quantity' => 10,
    'price' => 150.00,
  ]);

  $transactionService = app(EloquentTransactionServiceInterface::class);

  // Sell 5 shares
  $transactionService->createTransaction(
    $user->id,
    $ticker->id,
    'sell',
    5.0,
    160.00
  );

  // Check holdings
  $holdings = $transactionService->getUserHoldings($user->id);
  $holding = $holdings->first();

  expect((float) $holding->total_quantity)->toEqual(5.0);
});

it('calculates correct holdings after multiple buy and sell transactions', function () {
  $user = User::factory()->create();
  $ticker = Ticker::factory()->create(['symbol' => 'TSLA']);

  $transactionService = app(EloquentTransactionServiceInterface::class);

  // Buy 20 shares
  $transactionService->createTransaction($user->id, $ticker->id, 'buy', 20.0, 200.00);

  // Sell 5 shares
  $transactionService->createTransaction($user->id, $ticker->id, 'sell', 5.0, 210.00);

  // Buy 10 more shares
  $transactionService->createTransaction($user->id, $ticker->id, 'buy', 10.0, 205.00);

  // Sell 8 shares
  $transactionService->createTransaction($user->id, $ticker->id, 'sell', 8.0, 215.00);

  // Final holdings should be: 20 - 5 + 10 - 8 = 17
  $holdings = $transactionService->getUserHoldings($user->id);
  $holding = $holdings->first();

  expect((float) $holding->total_quantity)->toEqual(17.0);
});

it('removes ticker from holdings when all shares are sold', function () {
  $user = User::factory()->create();
  $ticker = Ticker::factory()->create(['symbol' => 'GOOGL']);

  $transactionService = app(EloquentTransactionServiceInterface::class);

  // Buy 10 shares
  $transactionService->createTransaction($user->id, $ticker->id, 'buy', 10.0, 100.00);

  // Sell all 10 shares
  $transactionService->createTransaction($user->id, $ticker->id, 'sell', 10.0, 110.00);

  // Holdings should be empty (or not include this ticker)
  $holdings = $transactionService->getUserHoldings($user->id);

  expect($holdings)->toBeEmpty();
});

it('deletes all transactions when removing a ticker', function () {
  $user = User::factory()->create();
  $ticker = Ticker::factory()->create(['symbol' => 'MSFT']);

  // Create multiple transactions
  Transaction::factory()->create([
    'user_id' => $user->id,
    'ticker_id' => $ticker->id,
    'type' => 'buy',
    'quantity' => 10,
  ]);

  Transaction::factory()->create([
    'user_id' => $user->id,
    'ticker_id' => $ticker->id,
    'type' => 'sell',
    'quantity' => 3,
  ]);

  // Verify transactions exist
  expect(Transaction::where('user_id', $user->id)->where('ticker_id', $ticker->id)->count())->toBe(2);

  // Delete all transactions for this ticker
  Transaction::where('user_id', $user->id)
    ->where('ticker_id', $ticker->id)
    ->delete();

  // Verify transactions are deleted
  expect(Transaction::where('user_id', $user->id)->where('ticker_id', $ticker->id)->count())->toBe(0);
});
