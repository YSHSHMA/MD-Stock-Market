<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Contracts\HttpTelegramServiceInterface;
use App\Contracts\StandardReportServiceInterface;
use App\Models\User;
use App\Telegram\Contracts\CommandHandlerInterface;

final readonly class RecapCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private HttpTelegramServiceInterface $telegram,
        private StandardReportServiceInterface $reportService
    ) {}

    public function handle(int $chatId, User $user, array $args): void
    {
        $report = $this->reportService->generateDailyReport($user);

        if (empty($report)) {
            $this->telegram->sendMessage($chatId, "📊 Your portfolio is empty. Use /add to start tracking stocks!");
            return;
        }

        $this->telegram->sendMessage($chatId, $report);
    }
}
