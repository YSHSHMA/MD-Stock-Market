<?php

declare(strict_types=1);

namespace App\Telegram\Commands;

use App\Contracts\HttpTelegramServiceInterface;
use App\Contracts\SettingServiceInterface;
use App\Models\User;
use App\Telegram\Contracts\CommandHandlerInterface;
use Exception;

final readonly class CurrencyCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private HttpTelegramServiceInterface $telegram,
        private SettingServiceInterface $settingService
    ) {}

    public function handle(int $chatId, User $user, array $args): void
    {
        if (empty($args)) {
            $current = $this->settingService->getCurrencySettings();
            $this->telegram->sendMessage($chatId, "🏦 *Current Currency Settings:*\n🔹 Code: `{$current['code']}`\n🔹 Symbol: `{$current['symbol']}`\n🔹 Exchange Rate: `{$current['exchange_rate']}`\n\n💡 To change: `/currency <ISO_CODE> <GRAPHIC_SYMBOL>`");
            return;
        }

        $code = strtoupper($args[0]);
        $symbol = $args[1] ?? ($code === 'EUR' ? '€' : '$');

        try {
            $this->telegram->sendMessage($chatId, "⏳ Fetching latest exchange rate for *{$code}*...");
            $rate = $this->settingService->fetchExchangeRate($code);

            $this->settingService->updateCurrencySettings([
                'code' => $code,
                'symbol' => $symbol,
                'exchange_rate' => $rate
            ]);

            $this->telegram->sendMessage($chatId, "✅ Currency updated!\n🔹 Code: *{$code}*\n🔹 Symbol: *{$symbol}*\n🔹 New Rate: *{$rate}*");
        } catch (Exception $e) {
            $errorMessage = "❌ Failed to update currency: " . $e->getMessage();

            if (count($args) >= 3) {
                $manualRate = (float) $args[2];
                $this->settingService->updateCurrencySettings([
                    'code' => $code,
                    'symbol' => $symbol,
                    'exchange_rate' => $manualRate
                ]);
                $this->telegram->sendMessage($chatId, "✅ (Manual) Currency updated to *{$code}* (*{$symbol}*) at rate *{$manualRate}*.");
            } else {
                $this->telegram->sendMessage($chatId, $errorMessage . "\n\n💡 You can set it manually: `/currency <CODE> <SYMBOL> <RATE>`");
            }
        }
    }
}
