<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Contracts\EloquentTickerServiceInterface;
use App\Contracts\EloquentTransactionServiceInterface;
use App\Contracts\HttpTelegramServiceInterface;
use App\Models\User;
use App\Telegram\Contracts\CommandHandlerInterface;
use App\Traits\ParsesTelegramCommands;

final readonly class RemoveCommandHandler implements CommandHandlerInterface
{
    use ParsesTelegramCommands;

    public function __construct(
        private HttpTelegramServiceInterface $telegram,
        private EloquentTransactionServiceInterface $transactionService,
        private EloquentTickerServiceInterface $tickerService
    ) {}

    public function handle(int $chatId, User $user, array $args): void
    {
        if (count($args) < 1) {
            $this->telegram->sendMessage($chatId, "🗑 Usage: `/remove <symbol>`\nExample: `/remove AAPL`.");
            return;
        }

        $symbol = $this->normalizeSymbol($args[0]);

        $ticker = $this->tickerService->findBySymbol($symbol);
        if (!$ticker) {
            $this->telegram->sendMessage($chatId, "❌ *{$symbol}* is not in your portfolio.");
            return;
        }

        $transactionCount = $this->transactionService->countTransactions($user->id, $ticker->id);

        if ($transactionCount === 0) {
            $this->telegram->sendMessage($chatId, "❌ No transactions found for *{$symbol}*.");
            return;
        }

        $deleted = $this->transactionService->removeAllTransactionsForTicker($user->id, $ticker->id);

        $this->telegram->sendMessage(
            $chatId,
            "✅ Removed *{$symbol}* from your portfolio.\n🗑 Deleted *{$deleted}* transaction(s)."
        );
    }
}
