<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Contracts\HttpTelegramServiceInterface;
use App\Models\User;
use App\Telegram\Commands\AddCommandHandler;
use App\Telegram\Commands\CurrencyCommandHandler;
use App\Telegram\Commands\RecapCommandHandler;
use App\Telegram\Commands\RemoveCommandHandler;
use App\Telegram\Commands\SearchCommandHandler;
use App\Telegram\Commands\SellCommandHandler;
use App\Telegram\Commands\StartCommandHandler;
use App\Telegram\Contracts\CommandHandlerInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class Dispatcher
{
    /** @var array<string, class-string<CommandHandlerInterface>> */
    private const COMMAND_MAP = [
        '/start' => StartCommandHandler::class,
        '/search' => SearchCommandHandler::class,
        '/add' => AddCommandHandler::class,
        '/sell' => SellCommandHandler::class,
        '/remove' => RemoveCommandHandler::class,
        '/recap' => RecapCommandHandler::class,
        '/currency' => CurrencyCommandHandler::class,
    ];

    public function __construct(
        private Container $container
    ) {}

    /**
     * @param array<int, string> $args
     */
    public function dispatch(string $command, int $chatId, User $user, array $args): void
    {
        $handlerClass = self::COMMAND_MAP[$command] ?? null;

        if (!$handlerClass) {
            $this->handleUnknownCommand($chatId);
            return;
        }

        try {
            /** @var CommandHandlerInterface $handler */
            $handler = $this->container->make($handlerClass);
            $handler->handle($chatId, $user, $args);
        } catch (Throwable $e) {
            Log::error("Failed to dispatch command: {$command}", [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);
            throw $e;
        }
    }

    private function handleUnknownCommand(int $chatId): void
    {
        $message = "🤔 I don't understand that command.\n\n" .
            "Available commands:\n" .
            "/search <symbol>\n" .
            "/add <symbol> <quantity> [price]\n" .
            "/sell <symbol> <quantity>\n" .
            "/remove <symbol>\n" .
            "/recap\n" .
            "/currency [code] [symbol]";

        /** @var HttpTelegramServiceInterface $telegram */
        $telegram = $this->container->make(HttpTelegramServiceInterface::class);
        $telegram->sendMessage($chatId, $message);
    }
}
