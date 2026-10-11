<?php

declare(strict_types=1);

namespace App\Casts;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class StringPercentToFloatCast implements Cast
{
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return (float) str_replace('%', '', $value);
    }
}
