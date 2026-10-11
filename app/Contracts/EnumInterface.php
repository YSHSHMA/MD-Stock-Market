<?php

declare(strict_types=1);

namespace App\Contracts;

interface EnumInterface
{
    public function label(): string;
}