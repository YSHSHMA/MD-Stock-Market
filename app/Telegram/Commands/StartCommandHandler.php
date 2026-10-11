<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Contracts\EloquentUserServiceInterface;
use App\Contracts\HttpTelegramServiceInterface;
use App\Models\User;
use App\Telegram\Contracts\CommandHandlerInterface;
use Illuminate\Support\Str;

final readonly class StartCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private HttpTelegramServiceInterface $telegram,
        private EloquentUserServiceInterface $userService
    ) {}

    public function handle(int $chatId, User $user, array $args): void
    {
        $token = $args[0] ?? null;

        if ($token) {
            $success = $this->userService->updateTelegramId($token, $chatId);

            if ($success) {
                // Fetch the updated user since their telegram_id just changed
                $user = $this->userService->findByTelegramId($chatId) ?? $user;
                $this->telegram->sendMessage($chatId, "✅ Account successfully linked to Telegram!");
            } else {
                $this->telegram->sendMessage($chatId, "❌ Failed to link account. Invalid or expired token.");
            }
        }

        $escapedName = HttpTelegramService::escapeMarkdown($user->name);
        $message = "Welcome {$escapedName}! 📈\n\n" .
            "Available commands:\n" .
            "/search <symbol> - Search for stocks\n" .
            "/add <symbol> <quantity> [price] - Buy stocks\n" .
            "/sell <symbol> <quantity> - Sell stocks\n" .
            "/remove <symbol> - Remove ticker from portfolio\n" .
            "/recap - Get current portfolio summary\n" .
            "/currency [code] [symbol] - View or set currency";

        $this->telegram->sendMessage($chatId, $message);
    }
}
