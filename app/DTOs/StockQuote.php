<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Casts\StringPercentToFloatCast;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

final class StockQuote extends Data
{
  public function __construct(
    #[MapInputName('01. symbol')]
    public string $symbol,

    #[MapInputName('05. price')]
    public float $price,

    #[MapInputName('09. change')]
    public float $change,

    #[MapInputName('10. change percent')]
    #[WithCast(StringPercentToFloatCast::class)]
    public float $changePercent,

    #[MapInputName('07. latest trading day')]
    #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d')]
    public Carbon $latestTradingDay,
  ) {}

  public static function from(mixed ...$payloads): static
  {
    if (isset($payloads[0]) && is_array($payloads[0]) && isset($payloads[0]['Global Quote'])) {
      return parent::from($payloads[0]['Global Quote']);
    }

    return parent::from(...$payloads);
  }
}
