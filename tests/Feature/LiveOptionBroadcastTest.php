<?php

use App\Events\OptionGreeksUpdated;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

test('active subscriptions broadcast only changed option data to the owning private channel', function () {
    config([
        'services.angel_one.client_local_ip' => '192.168.1.20',
        'services.angel_one.client_public_ip' => '203.0.113.20',
        'services.angel_one.mac_address' => '00:1A:2B:3C:4D:5E',
    ]);

    $user = User::factory()->create();
    $expiry = now()->addDays(30)->format('Y-m-d');
    $providerExpiry = strtoupper(now()->parse($expiry)->format('dMY'));
    $master = [
        ['name' => 'SENSEX', 'token' => '99919000', 'instrumenttype' => 'AMXIDX', 'exch_seg' => 'BSE'],
        [
            'name' => 'SENSEX',
            'symbol' => "SENSEX{$providerExpiry}72000CE",
            'token' => '301',
            'expiry' => $providerExpiry,
            'strike' => '7200000',
            'instrumenttype' => 'OPTIDX',
            'exch_seg' => 'BFO',
        ],
        [
            'name' => 'SENSEX',
            'symbol' => "SENSEX{$providerExpiry}72000PE",
            'token' => '302',
            'expiry' => $providerExpiry,
            'strike' => '7200000',
            'instrumenttype' => 'OPTIDX',
            'exch_seg' => 'BFO',
        ],
    ];
    Http::fake(function ($request) use ($master) {
        if (str_contains($request->url(), 'OpenAPIScripMaster.json')) {
            return Http::response($master);
        }

        $exchangeTokens = $request->data()['exchangeTokens'] ?? [];
        $exchange = array_key_first($exchangeTokens);
        $tokens = $exchangeTokens[$exchange] ?? [];
        $quotes = $exchange === 'BSE'
            ? [['symbolToken' => '99919000', 'ltp' => 72000]]
            : [
                ['symbolToken' => '301', 'ltp' => 1200, 'tradeVolume' => 800],
                ['symbolToken' => '302', 'ltp' => 1100, 'tradeVolume' => 700],
            ];

        return Http::response([
            'status' => true,
            'data' => ['fetched' => array_values(array_filter(
                $quotes,
                static fn (array $quote): bool => in_array($quote['symbolToken'], $tokens, true)
            ))],
        ]);
    });

    DB::table('angel_one_connections')->insert([
        'user_id' => $user->id,
        'client_code' => 'CLIENT',
        'encrypted_secrets' => Crypt::encryptString(json_encode([
            'api_key' => 'private-key',
            'jwt_token' => 'access-token',
        ], JSON_THROW_ON_ERROR)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('angel_one_market_subscriptions')->insert([
        'user_id' => $user->id,
        'underlying' => 'SENSEX',
        'expiry' => $expiry,
        'last_seen_at' => now(),
        'payload_hash' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Cache::forget('angel_one.option_catalog.v3');
    $this->travelTo(now());
    Event::fake();

    Artisan::call('option-chain:broadcast-live');

    Event::assertDispatched(OptionGreeksUpdated::class, function (OptionGreeksUpdated $event) use ($user): bool {
        return $event->userId === $user->id
            && $event->underlying === 'SENSEX'
            && $event->data[0]['trade_volume'] === 800
            && $event->broadcastOn()[0]->name === "private-users.{$user->id}";
    });

    expect(DB::table('angel_one_market_subscriptions')->where('user_id', $user->id)->value('payload_hash'))
        ->not->toBeNull();

    Event::fake();
    Artisan::call('option-chain:broadcast-live');
    Event::assertNotDispatched(OptionGreeksUpdated::class);
    Http::assertSentCount(5);
});

test('authenticated users can register and renew a live market subscription', function () {
    Cache::forget('angel_one.option_catalog.v3');
    $user = User::factory()->create();
    DB::table('angel_one_connections')->insert([
        'user_id' => $user->id,
        'client_code' => 'CLIENT',
        'encrypted_secrets' => Crypt::encryptString(json_encode([
            'api_key' => 'private-key',
            'jwt_token' => 'access-token',
        ], JSON_THROW_ON_ERROR)),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $expiry = now()->addDays(7)->format('Y-m-d');
    $providerExpiry = strtoupper(now()->parse($expiry)->format('dMY'));
    Http::fake([
        'https://margincalculator.angelone.in/OpenAPI_File/files/OpenAPIScripMaster.json' => Http::response([
            [
                'name' => 'NIFTY',
                'expiry' => $providerExpiry,
                'instrumenttype' => 'OPTIDX',
                'exch_seg' => 'NFO',
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->postJson(route('option-chain.angel-one.subscribe'), [
            'underlying' => 'NIFTY',
            'expiry' => $expiry,
        ])
        ->assertOk()
        ->assertJsonPath('underlying', 'NIFTY')
        ->assertJsonPath('expiry', $expiry);

    DB::table('angel_one_market_subscriptions')
        ->where('user_id', $user->id)
        ->update(['payload_hash' => str_repeat('a', 64)]);

    $this->postJson(route('option-chain.angel-one.subscribe'), [
        'underlying' => 'NIFTY',
        'expiry' => $expiry,
    ])->assertOk();

    expect(DB::table('angel_one_market_subscriptions')
        ->where('user_id', $user->id)
        ->value('payload_hash'))->toBe(str_repeat('a', 64));

    Http::assertSentCount(1);
});

test('an existing Angel One session is persisted when the dashboard is reopened', function () {
    $user = User::factory()->create();
    $encryptedSecrets = Crypt::encryptString(json_encode([
        'api_key' => 'private-key',
        'jwt_token' => 'access-token',
    ], JSON_THROW_ON_ERROR));

    $this->actingAs($user)
        ->withSession([
            'angel_one.connection' => [
                'client_code' => 'CLIENT',
                'secrets' => $encryptedSecrets,
            ],
        ])
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('connected: true', false);

    expect(DB::table('angel_one_connections')->where('user_id', $user->id)->value('encrypted_secrets'))
        ->toBe($encryptedSecrets);
});
