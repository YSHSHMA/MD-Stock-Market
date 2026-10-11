<?php

declare(strict_types=1);

namespace App\DTOs;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class StockSearchMatch extends Data
{
  public function __construct(
    #[MapInputName('1. symbol')]
    public string $symbol,
    #[MapInputName('2. name')]
    public string $name,
    #[MapInputName('3. type')]
    public string $type,
    #[MapInputName('4. region')]
    public string $region,
    #[MapInputName('8. currency')]
    public string $currency,
  ) {}
}
