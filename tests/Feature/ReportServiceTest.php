<?php

use App\Contracts\StandardReportServiceInterface;
use App\Models\User;
use App\Models\Ticker;
use App\Models\DailyMetric;
use App\Models\Transaction;

test('generateDailyReport returns correct report string', function () {
  $user = User::factory()->create();
  $ticker = Ticker::factory()->create(['symbol' => 'TSLA']);

  // Create Holdings
  Transaction::factory()->create([
    'user_id' => $user->id,
    'ticker_id' => $ticker->id,
    'type' => 'buy',
    'quantity' => 10,
    'price' => 100
  ]);

  // Create Metadata
  DailyMetric::factory()->create([
    'ticker_id' => $ticker->id,
    'price' => 200, // Doubled in value
    'change' => 10,
    'change_percent' => 5,
    'date' => now(), // Latest
  ]);

  $reportService = app(StandardReportServiceInterface::class); // Use real service with DB resolving

  $report = $reportService->generateDailyReport($user);

  expect($report)->not->toBeNull();
  expect($report)->toContain('TSLA');
  expect($report)->toContain('$2,000.00'); // 10 * 200
  expect($report)->toContain('📈');
});

test('generateDailyReport returns null for empty portfolio', function () {
  $user = User::factory()->create();
  $reportService = app(StandardReportServiceInterface::class);
  $report = $reportService->generateDailyReport($user);
  expect($report)->toBeNull();
});
