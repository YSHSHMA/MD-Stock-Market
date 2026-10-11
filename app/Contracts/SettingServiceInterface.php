<?php

declare(strict_types=1);

namespace App\Contracts;

interface SettingServiceInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, ?string $description = null): void;

    /**
     * Get currency settings: code, symbol, exchange_rate
     * @return array{code: string, symbol: string, exchange_rate: float}
     */
    public function getCurrencySettings(): array;

    /**
     * @param array{code: string, symbol: string, exchange_rate: float} $settings
     */
    public function updateCurrencySettings(array $settings): void;

    /**
     * Fetch the latest exchange rate from a third-party API.
     */
    public function fetchExchangeRate(string $toCurrency, string $fromCurrency = 'USD'): float;
}
