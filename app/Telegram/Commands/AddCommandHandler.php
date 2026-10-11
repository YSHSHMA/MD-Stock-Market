<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Contracts\AlphaVantageServiceInterface;
use App\Contracts\EloquentTickerServiceInterface;
use App\Contracts\EloquentTransactionServiceInterface;
use App\Contracts\HttpTelegramServiceInterface;
use App\Contracts\SettingServiceInterface;
use App\Exceptions\Domain\CurrencyNotSetException;
use App\Models\User;
use App\Telegram\Contracts\CommandHandlerInterface;
use App\Traits\ParsesTelegramCommands;

final readonly class AddCommandHandler implements CommandHandlerInterface
{
    use ParsesTelegramCommands;

    public function __construct(
        private HttpTelegramServiceInterface $telegram,
        private AlphaVantageServiceInterface $stockProvider,
        private EloquentTransactionServiceInterface $transactionService,
        private EloquentTickerServiceInterface $tickerService,
        private SettingServiceInterface $settingService
    ) {}

    public function handle(int $chatId, User $user, array $args): void
    {
        if (!$this->settingService->get('currency_code')) {
            throw new CurrencyNotSetException();
        }

        if (count($args) < 2) {
            $this->telegram->sendMessage($chatId, "📝 Usage: `/add <symbol> <quantity> [avg_price]`\nExample: `/add AAPL 10 150.50`.");
            return;
        }

        $symbol = $this->normalizeSymbol($args[0]);
        $quantity = $this->normalizeAmount($args[1]);
        $avgPrice = isset($args[2]) ? $this->normalizeAmount($args[2]) : null;

        if ($avgPrice === null) {
            $quote = $this->stockProvider->getQuote($symbol);

            if (!$quote) {
                $this->telegram->sendMessage($chatId, "🕵️‍♂️ Could not find symbol '{$symbol}'. To add it manually, provide the price: `/add {$symbol} {$quantity} <price>`");
                return;
            }

            $price = $quote->price;
        } else {
            $price = $avgPrice;
        }

        $ticker = $this->tickerService->getOrCreateTicker($symbol);

        $this->transactionService->createTransaction(
            $user->id,
            $ticker->id,
            'buy',
            $quantity,
            $price
        );

        $escapedSymbol = HttpTelegramService::escapeMarkdown($symbol);
        $this->telegram->sendMessage($chatId, "✅ Added *{$quantity}* shares of *{$escapedSymbol}* at *\${$price}* to your portfolio!");
    }
}
