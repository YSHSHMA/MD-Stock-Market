<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface StandardReportServiceInterface
{
  /**
   * @param callable(string): void $onSent
   */
  public function sendDailyReports(callable $onSent): void;

  public function generateAndSendReport(User $user): void;

  public function generateDailyReport(User $user): ?string;
}
