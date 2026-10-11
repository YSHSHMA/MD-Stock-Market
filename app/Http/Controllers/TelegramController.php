<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\EloquentUserServiceInterface;
use App\Contracts\HttpTelegramServiceInterface;
use App\Exceptions\Domain\ApiRateLimitExceededException;
use App\Exceptions\Domain\CurrencyNotSetException;
use App\Exceptions\Domain\InsufficientHoldingsException;
use App\Exceptions\Domain\TickerNotFoundException;
use App\Telegram\Dispatcher;
use App\Traits\ParsesTelegramCommands;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handle incoming Telegram Webhooks.
 * 
 * Refactored to use a Command Dispatcher pattern for better scalability
 * and adherence to SOLID principles.
 */
final class TelegramController extends Controller
{
    use ParsesTelegramCommands;

    public function __construct(
        private readonly HttpTelegramServiceInterface $telegram,
        private readonly EloquentUserServiceInterface $userService,
        private readonly Dispatcher $dispatcher
    ) {}

    /**
     * Entry point for Telegram Webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        
        if (!isset($payload['message'])) {
            return response()->json(['status' => 'ignored']);
        }

        $logPayload = $payload;
        $this->maskPii($logPayload);
        Log::debug('Telegram Webhook Receipt', ['payload' => $logPayload]);

        $message = $payload['message'];
        $chatId = (int) $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'];

        // Ensure user exists
        $user = $this->userService->getOrCreateTelegramUser(
            telegramId: (int) $from['id'],
            username: $from['username'] ?? "user_{$from['id']}",
            firstName: $from['first_name'] ?? 'User',
            lastName: $from['last_name'] ?? null
        );

        $parsed = $this->parseCommand($text);

        try {
            $this->dispatcher->dispatch(
                command: $parsed['command'],
                chatId: $chatId,
                user: $user,
                args: $parsed['args']
            );
        } catch (InsufficientHoldingsException $e) {
            $this->telegram->sendMessage($chatId, "⚠️ *Insufficient Holdings:* {$e->getMessage()}");
        } catch (ApiRateLimitExceededException $e) {
            $this->telegram->sendMessage($chatId, "⏳ *Rate Limit:* API limit reached. Please wait a moment.");
        } catch (CurrencyNotSetException $e) {
            $this->telegram->sendMessage($chatId, "🏦 *Currency Required:* {$e->getMessage()}");
        } catch (TickerNotFoundException $e) {
            $this->telegram->sendMessage($chatId, "🔍 *Error:* Stock symbol not found. Verify on AlphaVantage.");
        } catch (Throwable $e) {
            Log::critical('Unhandled Telegram Webhook Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->telegram->sendMessage($chatId, "🚨 *System Error:* An unexpected issue occurred. Support has been notified.");
        }

        return response()->json(['status' => 'handled']);
    }

    /**
     * Mask PII in the Telegram payload.
     */
    private function maskPii(array &$payload): void
    {
        if (!isset($payload['message']['from'])) {
            return;
        }

        $from = &$payload['message']['from'];
        $fields = ['first_name', 'last_name', 'username'];

        foreach ($fields as $field) {
            if (isset($from[$field])) {
                $from[$field] = '***';
            }
        }
    }
}
