<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface EloquentUserServiceInterface
{
  public function getOrCreateTelegramUser(int $telegramId, string $username, string $firstName, ?string $lastName = null): User;

  public function findByTelegramId(int $telegramId): ?User;

  public function generateLinkingToken(string $userId): string;

  public function updateTelegramId(string $token, int $telegramId): bool;
}
