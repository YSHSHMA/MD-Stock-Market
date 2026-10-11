<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SettingServiceInterface;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final readonly class EloquentSettingService implements SettingServiceInterface
{
    private const CACHE_PREFIX = 'setting_';

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever(self::CACHE_PREFIX . $key, function () use ($key, $default) {
            $setting = Setting::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public function set(string $key, mixed $value, ?string $description = null): void
    {
        DB::transaction(function () use ($key, $value, $description) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'description' => $description]
            );

            Cache::forget(self::CACHE_PREFIX . $key);
        });
    }

    public function getCurrencySettings(): array
    {
        return [
            'code' => $this->get('currency_code', 'USD'),
            'symbol' => $this->get('currency_symbol', '$'),
            'exchange_rate' => (float) $this->get('exchange_rate', 1.0),
        ];
    }

    public function updateCurrencySettings(array $settings): void
    {
        DB::transaction(function () use ($settings) {
            if (isset($settings['code'])) {
                $this->set('currency_code', $settings['code'], 'Active currency ISO code');
            }
            if (isset($settings['symbol'])) {
                $this->set('currency_symbol', $settings['symbol'], 'Active currency graphic symbol');
            }
            if (isset($settings['exchange_rate'])) {
                $this->set('exchange_rate', $settings['exchange_rate'], 'Exchange rate relative to base (USD)');
            }
        });
    }

    public function fetchExchangeRate(string $toCurrency, string $fromCurrency = 'USD'): float
    {
        if ($toCurrency === $fromCurrency) {
            return 1.0;
        }

        try {
            $response = Http::timeout(10)
                ->get(config('settings.currency.api_url'), [
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                ]);

            if ($response->failed()) {
                throw new RuntimeException("Failed to fetch exchange rate from Frankfurter API: " . $response->status());
            }

            $data = $response->json();

            return (float) ($data['rates'][$toCurrency] ?? throw new RuntimeException("Rate for {$toCurrency} not found in API response"));
        } catch (Exception $e) {
            Log::error("Currency API Error", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
