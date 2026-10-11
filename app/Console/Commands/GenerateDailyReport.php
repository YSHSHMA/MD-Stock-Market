<?php

namespace App\Console\Commands;

use App\Contracts\StandardReportServiceInterface;
use Illuminate\Console\Command;

class GenerateDailyReport extends Command
{
    protected $signature = 'report:daily';
    protected $description = 'Generate and send daily performance reports to users';

    public function handle(StandardReportServiceInterface $reportService)
    {
        $this->info("Starting daily report generation...");

        $reportService->sendDailyReports(function (string $userName) {
            $this->info("Report sent to {$userName}");
        });

        $this->info("Finished sending daily reports.");
    }
}
