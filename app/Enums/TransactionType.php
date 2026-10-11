<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\EnumInterface;
use App\Traits\Enums\EnumHelper;

enum TransactionType: string implements EnumInterface
{
  use EnumHelper;

  case BUY = 'buy';
  case SELL = 'sell';

  public function label(): string
  {
    return match ($this) {
      self::BUY => 'Buy',
      self::SELL => 'Sell',
    };
  }
}
