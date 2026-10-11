<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Exception;

final class CurrencyNotSetException extends Exception
{
    protected $message = 'Currency is not set in settings. Please use /currency command first.';
}
