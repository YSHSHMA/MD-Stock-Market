<?php

declare(strict_types=1);

namespace App\Traits;

trait ParsesTelegramCommands
{
  /**
   * Parse text into command and arguments.
   * 
   * @return array{command: string, args: array<string>}
   */
  protected function parseCommand(string $text): array
  {
    $parts = preg_split('/\s+/', trim($text));
    $command = $parts[0] ?? '';
    $arguments = array_slice($parts, 1);

    return [
      'command' => $command,
      'args' => $arguments,
    ];
  }

  /**
   * Normalize a ticker symbol.
   */
  protected function normalizeSymbol(string $symbol): string
  {
    return strtoupper(trim($symbol));
  }

  /**
   * Normalize quantity or price strings (e.g. 10,50 -> 10.50).
   */
  protected function normalizeAmount(string|float|int $amount): float
  {
    if (is_string($amount)) {
      $amount = str_replace(',', '.', $amount);
    }

    return (float) $amount;
  }
}
