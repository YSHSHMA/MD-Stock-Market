<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Contracts\AlphaVantageServiceInterface;
use App\Contracts\HttpTelegramServiceInterface;
use App\Models\User;
use App\Telegram\Contracts\CommandHandlerInterface;

final readonly class SearchCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private HttpTelegramServiceInterface $telegram,
        private AlphaVantageServiceInterface $stockProvider
    ) {}

    public function handle(int $chatId, User $user, array $args): void
    {
        $keywords = $args[0] ?? '';

        if (empty($keywords)) {
            $this->telegram->sendMessage($chatId, "🔍 Please provide a symbol. Example: /search AAPL");
            return;
        }

        $bestMatches = $this->stockProvider->searchTicker($keywords);

        if ($bestMatches->isEmpty()) {
            $this->telegram->sendMessage($chatId, "🚫 No stocks found for '{$keywords}'.");
            return;
        }

        $message = "🔍 Search Results for '{$keywords}':\n\n";
        foreach ($bestMatches->take(5) as $match) {
            $escapedSymbol = HttpTelegramService::escapeMarkdown($match->symbol);
            $escapedName = HttpTelegramService::escapeMarkdown($match->name);
            $message .= "🔹 *{$escapedSymbol}* - {$escapedName} ({$match->region})\n";
        }
        $message .= "\n💡 Use `/add <symbol> <quantity> [price]` to track one.";

        $this->telegram->sendMessage($chatId, $message);
    }
}
