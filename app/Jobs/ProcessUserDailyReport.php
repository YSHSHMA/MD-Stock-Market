<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Contracts\StandardReportServiceInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ProcessUserDailyReport implements ShouldQueue
{
  use InteractsWithQueue, Queueable, SerializesModels;

  /**
   * The number of times the job may be attempted.
   */
  public int $tries;

  /**
   * Calculate the number of seconds to wait before retrying the job.
   */
  public function backoff(): int
  {
    return config('settings.jobs.daily_report.backoff_seconds', 300);
  }

  public function __construct(
    private User $user
  ) {
    $this->tries = config('settings.jobs.daily_report.tries', 2);
  }

  public function handle(StandardReportServiceInterface $reportService): void
  {
    Log::info("Processing daily report for user: {$this->user->id}");
    $reportService->generateAndSendReport($this->user);
  }
}
