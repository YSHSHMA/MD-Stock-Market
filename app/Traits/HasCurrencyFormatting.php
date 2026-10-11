<?php

declare(strict_types=1);

namespace App\Traits;

trait HasCurrencyFormatting
{
  /**
   * Format a float as a currency string.
   */
  protected function formatCurrency(float $amount, string $symbol = '$'): string
  {
    $prefix = $amount >= 0 ? '+' : '';
    return $prefix . $symbol . number_format($amount, 2);
  }
}
