<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Contracts\AlphaVantageServiceInterface;
use App\Contracts\EloquentTickerServiceInterface;
use App\Contracts\EloquentTransactionServiceInterface;
use App\Contracts\HttpTelegramServiceInterface;
use App\Models\User;
use App\Telegram\Contracts\CommandHandlerInterface;
use App\Traits\ParsesTelegramCommands;

final readonly class SellCommandHandler implements CommandHandlerInterface
{
    use ParsesTelegramCommands;

    public function __construct(
        private HttpTelegramServiceInterface $telegram,
        private AlphaVantageServiceInterface $stockProvider,
        private EloquentTransactionServiceInterface $transactionService,
        private EloquentTickerServiceInterface $tickerService
    ) {}

    public function handle(int $chatId, User $user, array $args): void
    {
        if (count($args) < 2) {
            $this->telegram->sendMessage($chatId, "📝 Usage: `/sell <symbol> <quantity>`\nExample: `/sell AAPL 5`.");
            return;
        }

        $symbol = $this->normalizeSymbol($args[0]);
        $quantity = $this->normalizeAmount($args[1]);

        $ticker = $this->tickerService->findBySymbol($symbol);
        if (!$ticker) {
            $this->telegram->sendMessage($chatId, "❌ You don't have *{$symbol}* in your portfolio.");
            return;
        }

        $quote = $this->stockProvider->getQuote($symbol);
        if (!$quote) {
            $this->telegram->sendMessage($chatId, "❌ Could not fetch current price for *{$symbol}*. Try again later.");
            return;
        }

        $this->transactionService->createTransaction(
            $user->id,
            $ticker->id,
            'sell',
            $quantity,
            $quote->price
        );

        $holdings = $this->transactionService->getUserHoldings($user->id);
        $holding = $holdings->firstWhere('ticker_id', $ticker->id);
        $remaining = $holding ? $holding->total_quantity : 0;
        $totalValue = $quantity * $quote->price;

        $escapedSymbol = HttpTelegramService::escapeMarkdown($symbol);
        $this->telegram->sendMessage(
            $chatId,
            "✅ Sold *{$quantity}* shares of *{$escapedSymbol}* at *\${$quote->price}*\n💰 Total: *\${$totalValue}*\n📊 Remaining: *{$remaining}* shares"
        );
    }
}
