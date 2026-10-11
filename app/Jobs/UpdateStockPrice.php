<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\DefaultStockServiceInterface;
use App\Models\Ticker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class UpdateStockPrice implements ShouldQueue
{
  use InteractsWithQueue, Queueable, SerializesModels;

  /**
   * The number of times the job may be attempted.
   */
  public int $tries;

  /**
   * Create a new job instance.
   */
  public function __construct(
    private Ticker $ticker,
    private ?string $date = null
  ) {
    $this->tries = config('settings.jobs.stock_update.tries', 3);
  }

  /**
   * Calculate the number of seconds to wait before retrying the job.
   */
  public function backoff(): array
  {
    return config('settings.jobs.stock_update.backoff', [60, 120, 240]);
  }

  /**
   * Get the middleware the job should pass through.
   */
  public function middleware(): array
  {
    return [new RateLimited('alphavantage')];
  }

  /**
   * Execute the job.
   */
  public function handle(DefaultStockServiceInterface $stockService): void
  {
    Log::info("Updating price for ticker: {$this->ticker->symbol} for date: {$this->date}");
    $stockService->updateTickerPrice($this->ticker, $this->date);
  }
}
