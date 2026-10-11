<?php

use App\Contracts\SettingServiceInterface;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->service = app(SettingServiceInterface::class);
    Cache::flush();
});

test('it can set and get a setting', function () {
    $this->service->set('test_key', 'test_value', 'A test description');

    expect($this->service->get('test_key'))->toBe('test_value');

    $this->assertDatabaseHas('settings', [
        'key' => 'test_key',
        'value' => 'test_value',
        'description' => 'A test description'
    ]);
});

test('it returns default value if setting does not exist', function () {
    expect($this->service->get('non_existent', 'default'))
        ->toBe('default');
});

test('it caches the settings forever', function () {
    $this->service->set('cached_key', 'original_value');

    // First call to get() will cache it
    expect($this->service->get('cached_key'))->toBe('original_value');

    // Manually change DB value without using the service (avoids Cache::forget)
    Setting::where('key', 'cached_key')->update(['value' => 'changed_value']);

    // Should still return cached value
    expect($this->service->get('cached_key'))->toBe('original_value');
});

test('it clears cache when setting is updated', function () {
    $this->service->set('update_key', 'version_1');
    expect($this->service->get('update_key'))->toBe('version_1');

    // Update setting using service
    $this->service->set('update_key', 'version_2');

    // Cache should be cleared and new value returned
    expect($this->service->get('update_key'))->toBe('version_2');
});

test('it can get currency settings with defaults', function () {
    $settings = $this->service->getCurrencySettings();

    expect($settings)->toBe([
        'code' => 'USD',
        'symbol' => '$',
        'exchange_rate' => 1.0,
    ]);
});

test('it can update and retrieve currency settings', function () {
    $this->service->updateCurrencySettings([
        'code' => 'EUR',
        'symbol' => '€',
        'exchange_rate' => 0.92,
    ]);

    $settings = $this->service->getCurrencySettings();

    expect($settings['code'])->toBe('EUR')
        ->and($settings['symbol'])->toBe('€')
        ->and($settings['exchange_rate'])->toBe(0.92);
});

test('it can partial update currency settings', function () {
    $this->service->updateCurrencySettings(['code' => 'GBP']);

    $settings = $this->service->getCurrencySettings();

    expect($settings['code'])->toBe('GBP')
        ->and($settings['symbol'])->toBe('$'); // Remained default
});

test('it can fetch exchange rate from external api', function () {
    Http::fake([
        'api.frankfurter.app/*' => Http::response([
            'amount' => 1.0,
            'base' => 'USD',
            'date' => '2024-01-26',
            'rates' => ['EUR' => 0.92]
        ], 200)
    ]);

    $rate = $this->service->fetchExchangeRate('EUR', 'USD');

    expect($rate)->toBe(0.92);
});
