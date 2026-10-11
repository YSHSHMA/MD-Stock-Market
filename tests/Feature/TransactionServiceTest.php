<?php

use App\Contracts\EloquentTransactionServiceInterface;
use App\Models\User;
use App\Models\Ticker;
use App\Models\Transaction;

test('createTransaction creates a transaction record', function () {
  $user = User::factory()->create();
  $ticker = Ticker::factory()->create();
  $service = app(EloquentTransactionServiceInterface::class);

  $transaction = $service->createTransaction(
    $user->id,
    $ticker->id,
    'buy',
    5,
    100.50
  );

  expect($transaction)->toBeInstanceOf(Transaction::class);
  $this->assertDatabaseHas('transactions', [
    'user_id' => $user->id,
    'quantity' => 5,
    'price' => 100.50
  ]);
});

test('getUserHoldings calculates totals correctly', function () {
  $user = User::factory()->create();
  $ticker = Ticker::factory()->create();
  $service = app(EloquentTransactionServiceInterface::class);

  // Buy 10
  Transaction::factory()->create(['user_id' => $user->id, 'ticker_id' => $ticker->id, 'type' => 'buy', 'quantity' => 10]);
  // Buy 5
  Transaction::factory()->create(['user_id' => $user->id, 'ticker_id' => $ticker->id, 'type' => 'buy', 'quantity' => 5]);
  // Sell 3
  Transaction::factory()->create(['user_id' => $user->id, 'ticker_id' => $ticker->id, 'type' => 'sell', 'quantity' => 3]);

  $holdings = $service->getUserHoldings($user->id);

  expect($holdings->first()->ticker_id)->toEqual($ticker->id);
  expect($holdings->first()->total_quantity)->toEqual(12); // 10 + 5 - 3
});
