<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Contracts\HttpTelegramServiceInterface;
use Exception;

final class HttpTelegramService implements HttpTelegramServiceInterface
{
  private string $token;
  private string $baseUrl;

  public function __construct()
  {
    $this->token = (string) config('services.telegram.token');
    $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
  }

  public function sendMessage(int|string $chatId, string $text, array $extra = []): ?array
  {
    $limit = config('settings.telegram.message_limit', 4000);

    if (strlen($text) <= $limit) {
      return $this->sendSingleMessage($chatId, $text, $extra);
    }

    // Smart chunking: split by double newline first to preserve blocks
    $chunks = [];
    $currentChunk = '';
    $paragraphs = explode("\n", $text);

    foreach ($paragraphs as $paragraph) {
      if (strlen($currentChunk . "\n" . $paragraph) > $limit) {
        if (!empty($currentChunk)) {
          $chunks[] = $currentChunk;
          $currentChunk = $paragraph;
        } else {
          // Single paragraph too long, forced split
          $chunks[] = substr($paragraph, 0, $limit);
          $currentChunk = substr($paragraph, $limit);
        }
      } else {
        $currentChunk = empty($currentChunk) ? $paragraph : $currentChunk . "\n" . $paragraph;
      }
    }

    if (!empty($currentChunk)) {
      $chunks[] = $currentChunk;
    }

    $lastResponse = null;
    foreach ($chunks as $chunk) {
      $lastResponse = $this->sendSingleMessage($chatId, $chunk, $extra);
    }

    return $lastResponse;
  }

  private function sendSingleMessage(int|string $chatId, string $text, array $extra = []): ?array
  {
    return $this->request('sendMessage', array_merge([
      'chat_id' => $chatId,
      'text' => $text,
      'parse_mode' => 'Markdown',
    ], $extra));
  }

  public function setWebhook(string $url): ?array
  {
    return $this->request('setWebhook', [
      'url' => $url,
    ]);
  }

  public static function escapeMarkdown(string $text): string
  {
    return str_replace(
      ['_', '*', '`', '['],
      ['\_', '\*', '\`', '\['],
      $text
    );
  }

  private function request(string $method, array $params): ?array
  {
    try {
      $response = Http::post("{$this->baseUrl}/{$method}", $params);

      if ($response->failed()) {
        Log::error("Telegram API Request Failed", [
          'method' => $method,
          'status' => $response->status(),
          'body' => $response->body(),
        ]);
        return null;
      }

      return $response->json();
    } catch (Exception $e) {
      Log::error("Telegram Service Exception", ['error' => $e->getMessage()]);
      return null;
    }
  }
}
