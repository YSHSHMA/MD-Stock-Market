<?php

declare(strict_types=1);

namespace App\Contracts;

interface HttpTelegramServiceInterface
{
  public function sendMessage(int|string $chatId, string $text, array $extra = []): ?array;

  public function setWebhook(string $url): ?array;
}
