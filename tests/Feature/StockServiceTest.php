<?php

use App\Contracts\DefaultStockServiceInterface;
use App\Models\Ticker;
use Illuminate\Support\Facades\Http;

test('updateTickerPrice handles API failure gracefully', function () {
  // Fake the AlphaVantage API response
  Http::fake([
    'alphavantage.co/*' => Http::response(null, 500)
  ]);

  $stockService = app(DefaultStockServiceInterface::class);
  $ticker = Ticker::factory()->create(['symbol' => 'IBM']);

  expect(fn() => $stockService->updateTickerPrice($ticker))->toThrow(\App\Exceptions\Domain\DomainException::class);
});

test('updateTickerPrice creates metric on success', function () {
  // Fake the AlphaVantage API response
  Http::fake([
    'alphavantage.co/*' => Http::response([
      'Global Quote' => [
        '01. symbol' => 'AAPL',
        '05. price' => '150.00',
        '07. latest trading day' => '2024-01-17',
        '09. change' => '1.50',
        '10. change percent' => '1.00%'
      ]
    ], 200)
  ]);

  $stockService = app(DefaultStockServiceInterface::class);
  $ticker = Ticker::factory()->create(['symbol' => 'AAPL']);

  $result = $stockService->updateTickerPrice($ticker);

  expect($result)->not->toBeNull();
  expect((float)$result->price)->toEqual(150.00);
  expect((float)$result->change)->toEqual(1.50);
  expect((float)$result->change_percent)->toEqual(1.00);

  // Verify DB
  $this->assertDatabaseHas('daily_metrics', [
    'ticker_id' => $ticker->id,
    'price' => 150.00
  ]);
});
