<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\DailyPerformanceReport;
use App\Models\User;
use App\Services\PortfolioCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

final class SendDailyReport implements ShouldQueue
{
  use InteractsWithQueue, Queueable, SerializesModels;

  public function __construct(
    private User $user,
    private string $reportText,
    private ?string $csvUrl = null
  ) {}

  public function handle(): void
  {
    Log::info("Sending daily report email to user: {$this->user->id}");

    Mail::to($this->user->email)->send(
      new DailyPerformanceReport(
        $this->reportText,
        $this->user->name,
        $this->csvUrl
      )
    );
  }
}
