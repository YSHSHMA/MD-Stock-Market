<?php

declare(strict_types=1);

namespace App\Telegram\Contracts;

use App\Models\User;

interface CommandHandlerInterface
{
    /**
     * @param array<int, string> $args
     */
    public function handle(int $chatId, User $user, array $args): void;
}
