<?php

use App\Models\Ticker;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('guests are sent to the sign-in page before viewing the dashboard', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/login')->assertOk();
});

test('a visitor can register, reach the dashboard, and use the session-authenticated API', function () {
    $this->post('/register', [
        'name' => 'Alex Morgan',
        'email' => 'alex@example.com',
        'password' => 'a-secure-password',
        'password_confirmation' => 'a-secure-password',
    ])->assertRedirect('/dashboard');

    $this->assertAuthenticated();
    $dashboardResponse = $this->get('/dashboard');
    $dashboardResponse
        ->assertOk()
        ->assertSee('Your positions')
        ->assertSee('Record a trade')
        ->assertSee('Option chain')
        ->assertSee('SIMULATED DATA')
        ->assertSee('id="option-chain" hidden', false)
        ->assertSee('value="NIFTY" selected', false)
        ->assertSee('value="BANKNIFTY"', false)
        ->assertSee('value="CRUDEOIL"', false)
        ->assertSee('id="option-expiry" aria-label="Option expiry date"', false)
        ->assertSee('<details class="angel-one-connection" id="angel-one-connection">', false)
        ->assertSee('id="option-live-content" hidden', false)
        ->assertSee('id="option-market-controls" hidden', false)
        ->assertSee('id="angel-one-summary-text">Connect Angel One</span>', false);
    $dashboardResponse
        ->assertDontSee('name="state"')
        ->assertDontSee('name="client_local_ip"')
        ->assertDontSee('name="client_public_ip"')
        ->assertDontSee('name="mac_address"');

    $dashboard = $this->get('/dashboard')->getContent();
    expect(strpos($dashboard, 'id="activity"'))
        ->toBeLessThan(strpos($dashboard, 'id="option-chain"'));
    expect(strpos($dashboard, 'id="angel-one-connection"'))
        ->toBeLessThan(strpos($dashboard, 'id="option-market-controls"'))
        ->and(strpos($dashboard, 'id="option-market-controls"'))
        ->toBeLessThan(strpos($dashboard, 'id="option-chain-body"'));

    $this->withHeader('Origin', 'http://localhost:8081')
        ->getJson('/api/portfolio/summary')
        ->assertOk()
        ->assertJsonPath('data.totals.value', 0);
});

test('authenticated users can load a summary and record a buy without preconfigured currency', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->getJson('/api/portfolio/summary')
        ->assertOk()
        ->assertJsonPath('data.currency.code', 'USD')
        ->assertJsonPath('data.totals.value', 0);

    $this->postJson('/api/portfolio', [
        'symbol' => 'ACME',
        'type' => 'buy',
        'quantity' => 3,
        'price' => 25.50,
    ])->assertCreated()
        ->assertJsonPath('data.symbol', 'ACME')
        ->assertJsonPath('data.type.value', 'buy');

    $ticker = Ticker::where('symbol', 'ACME')->firstOrFail();
    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'ticker_id' => $ticker->id,
        'type' => 'buy',
        'quantity' => 3,
    ]);
});

test('authenticated users can review history and delete their own transaction', function () {
    $user = User::factory()->create();
    $ticker = Ticker::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $user->id,
        'ticker_id' => $ticker->id,
        'type' => 'buy',
        'quantity' => 2,
        'price' => 18,
    ]);

    $this->actingAs($user)
        ->getJson('/api/portfolio')
        ->assertOk()
        ->assertJsonPath('data.0.symbol', $ticker->symbol);

    $this->deleteJson("/api/portfolio/{$transaction->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Transaction deleted');

    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
});

test('a user cannot delete another users transaction', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ticker = Ticker::factory()->create();
    $transaction = Transaction::factory()->create([
        'user_id' => $owner->id,
        'ticker_id' => $ticker->id,
    ]);

    $this->actingAs($otherUser)
        ->deleteJson("/api/portfolio/{$transaction->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
});

test('Angel One login stores encrypted tokens in the signed-in session without returning them', function () {
    config([
        'services.angel_one.client_local_ip' => '192.168.1.20',
        'services.angel_one.client_public_ip' => '203.0.113.20',
        'services.angel_one.mac_address' => '00:1A:2B:3C:4D:5E',
    ]);

    Http::fake([
        'https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword' => Http::response([
            'status' => true,
            'data' => [
                'jwtToken' => 'test-jwt-token',
                'refreshToken' => 'test-refresh-token',
                'feedToken' => 'test-feed-token',
            ],
        ]),
    ]);

    $response = $this->actingAs(User::factory()->create())
        ->postJson(route('option-chain.angel-one.connect'), [
            'clientcode' => 'TESTCLIENT',
            'password' => '1234',
            'totp' => '123456',
            'api_key' => 'test-private-api-key',
        ]);

    $response->assertOk()
        ->assertJsonPath('connection.client_code', 'TESTCLIENT')
        ->assertJsonMissing(['jwt_token' => 'test-jwt-token'])
        ->assertDontSee('test-jwt-token');

    $connection = session('angel_one.connection');
    expect($connection['client_code'])->toBe('TESTCLIENT');
    $secrets = json_decode(Crypt::decryptString($connection['secrets']), true, flags: JSON_THROW_ON_ERROR);
    expect($secrets)->toMatchArray([
        'api_key' => 'test-private-api-key',
        'jwt_token' => 'test-jwt-token',
        'refresh_token' => 'test-refresh-token',
        'feed_token' => 'test-feed-token',
    ])->not->toHaveKey('password')->not->toHaveKey('totp');
    $storedConnection = DB::table('angel_one_connections')->where('user_id', auth()->id())->first();
    expect($storedConnection->client_code)->toBe('TESTCLIENT')
        ->and($storedConnection->encrypted_secrets)->not->toContain('test-jwt-token')
        ->and(json_decode(Crypt::decryptString($storedConnection->encrypted_secrets), true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'api_key' => 'test-private-api-key',
            'jwt_token' => 'test-jwt-token',
        ]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('connected: true', false)
        ->assertSee('clientCode: "TESTCLIENT"', false)
        ->assertDontSee('test-jwt-token')
        ->assertDontSee('test-private-api-key');

    Http::assertSent(function ($request): bool {
        $expectedPayloadKeys = ['clientcode', 'password', 'totp', 'state'];

        return $request->url() === 'https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword'
            && $request->method() === 'POST'
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request->hasHeader('X-UserType', 'USER')
            && $request->hasHeader('X-SourceID', 'WEB')
            && $request->hasHeader('X-PrivateKey', 'test-private-api-key')
            && $request->hasHeader('X-ClientLocalIP', '192.168.1.20')
            && $request->hasHeader('X-ClientPublicIP', '203.0.113.20')
            && $request->hasHeader('X-MACAddress', '00:1A:2B:3C:4D:5E')
            && array_keys($request->data()) === $expectedPayloadKeys
            && $request['clientcode'] === 'TESTCLIENT'
            && $request['password'] === '1234'
            && $request['totp'] === '123456'
            && Str::isUuid((string) $request['state']);
    });

    $this->postJson(route('option-chain.angel-one.disconnect'))
        ->assertOk()
        ->assertJsonPath('message', 'Angel One session disconnected.');

    expect(session()->has('angel_one.connection'))->toBeFalse();
});

test('Angel One login failures do not create an authenticated broker session', function () {
    config([
        'services.angel_one.client_local_ip' => '192.168.1.20',
        'services.angel_one.client_public_ip' => '203.0.113.20',
        'services.angel_one.mac_address' => '00:1A:2B:3C:4D:5E',
    ]);

    Http::fake([
        'https://apiconnect.angelone.in/rest/auth/angelbroking/user/v1/loginByPassword' => Http::response([
            'status' => false,
            'message' => 'Invalid credentials',
            'data' => null,
        ], 401),
    ]);

    $this->actingAs(User::factory()->create())
        ->postJson(route('option-chain.angel-one.connect'), [
            'clientcode' => 'TESTCLIENT',
            'password' => '1234',
            'totp' => '123456',
            'api_key' => 'test-private-api-key',
        ])
        ->assertUnprocessable()
        ->assertJsonMissing(['password' => '1234'])
        ->assertJsonMissing(['totp' => '123456']);

    expect(session()->has('angel_one.connection'))->toBeFalse();
});

test('Angel One login fails explicitly when server network identity is not configured', function () {
    config([
        'services.angel_one.client_local_ip' => null,
        'services.angel_one.client_public_ip' => null,
        'services.angel_one.mac_address' => null,
    ]);

    Http::fake();

    $this->actingAs(User::factory()->create())
        ->postJson(route('option-chain.angel-one.connect'), [
            'clientcode' => 'TESTCLIENT',
            'password' => '1234',
            'totp' => '123456',
            'api_key' => 'test-private-api-key',
        ])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Angel One network identity is incomplete. Configure these values in the server environment: ANGEL_ONE_CLIENT_LOCAL_IP, ANGEL_ONE_CLIENT_PUBLIC_IP, ANGEL_ONE_MAC_ADDRESS.');

    Http::assertNothingSent();
});

test('connected Angel One users can fetch live option Greeks for a supported underlying and expiry', function () {
    Cache::forget('angel_one.option_catalog.v3');
    config([
        'services.angel_one.client_local_ip' => '192.168.1.20',
        'services.angel_one.client_public_ip' => '203.0.113.20',
        'services.angel_one.mac_address' => '00:1A:2B:3C:4D:5E',
    ]);

    $expiry = now()->addDays(7)->format('Y-m-d');
    $providerExpiry = strtoupper(now()->parse($expiry)->format('dMY'));
    $master = [
        [
            'name' => 'NIFTY',
            'expiry' => $providerExpiry,
            'instrumenttype' => 'OPTIDX',
            'exch_seg' => 'NFO',
        ],
        ['name' => 'NIFTY', 'token' => '99926000', 'instrumenttype' => 'AMXIDX', 'exch_seg' => 'NSE'],
        ['name' => 'NIFTY', 'symbol' => "NIFTY{$providerExpiry}25000CE", 'token' => '101', 'expiry' => $providerExpiry, 'strike' => '2500000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'NIFTY', 'symbol' => "NIFTY{$providerExpiry}25000PE", 'token' => '102', 'expiry' => $providerExpiry, 'strike' => '2500000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
    ];
    Http::fake(function ($request) use ($master) {
        if (str_contains($request->url(), 'OpenAPIScripMaster.json')) {
            return Http::response($master);
        }
        if (str_contains($request->url(), 'optionGreek')) {
            return Http::response([
                'status' => true,
                'data' => [
                    [
                        'strikePrice' => '25000.000000',
                        'optionType' => 'CE',
                        'delta' => '0.52',
                        'gamma' => '0.0003',
                        'theta' => '-12.4',
                        'vega' => '18.1',
                        'impliedVolatility' => '14.6',
                        'tradeVolume' => '2450',
                    ],
                    [
                        'strikePrice' => '25000.000000',
                        'optionType' => 'PE',
                        'delta' => '-0.48',
                        'gamma' => '0.0003',
                        'theta' => '-11.9',
                        'vega' => '17.7',
                        'impliedVolatility' => '14.8',
                        'tradeVolume' => '1830',
                    ],
                ],
            ]);
        }

        $exchangeTokens = $request->data()['exchangeTokens'] ?? [];
        $exchange = array_key_first($exchangeTokens);
        $tokens = $exchangeTokens[$exchange] ?? [];

        return Http::response([
            'status' => true,
            'data' => ['fetched' => array_map(
                static fn (string $token): array => $exchange === 'NSE'
                    ? ['symbolToken' => $token, 'ltp' => 25000]
                    : [
                        'symbolToken' => $token,
                        'ltp' => $token === '101' ? 145.5 : 132.25,
                        'opnInterest' => $token === '101' ? 12000 : 15000,
                        'tradeVolume' => 2450,
                    ],
                $tokens
            )],
        ]);
    });

    $this->actingAs(User::factory()->create())
        ->withSession([
            'angel_one.connection' => [
                'client_code' => 'TESTCLIENT',
                'secrets' => Crypt::encryptString(json_encode([
                    'api_key' => 'test-private-api-key',
                    'jwt_token' => 'test-jwt-token',
                    'refresh_token' => 'test-refresh-token',
                    'feed_token' => 'test-feed-token',
                ], JSON_THROW_ON_ERROR)),
            ],
        ])
        ->postJson(route('option-chain.angel-one.greeks'), [
            'underlying' => 'NIFTY',
            'expiry' => $expiry,
        ])
        ->assertOk()
        ->assertJsonPath('underlying', 'NIFTY')
        ->assertJsonPath('expiry', strtoupper(now()->parse($expiry)->format('dMY')))
        ->assertJsonPath('expiry_date', $expiry)
        ->assertJsonPath('data.0.option_type', 'CE')
        ->assertJsonPath('greeks_source', 'angel_one')
        ->assertJsonPath('data.0.strike', 25000)
        ->assertJsonPath('data.0.implied_volatility', 14.6)
        ->assertJsonPath('data.0.ltp', 145.5)
        ->assertJsonPath('data.0.open_interest', 12000)
        ->assertJsonPath('data.1.option_type', 'PE')
        ->assertJsonMissing(['jwt_token' => 'test-jwt-token']);

    Http::assertSent(function ($request) use ($providerExpiry): bool {
        return $request->url() === 'https://apiconnect.angelone.in/rest/secure/angelbroking/marketData/v1/optionGreek'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-jwt-token')
            && $request->hasHeader('X-PrivateKey', 'test-private-api-key')
            && $request->hasHeader('X-ClientLocalIP', '192.168.1.20')
            && $request->hasHeader('X-ClientPublicIP', '203.0.113.20')
            && $request->hasHeader('X-MACAddress', '00:1A:2B:3C:4D:5E')
            && $request->data() === [
                'name' => 'NIFTY',
                'expirydate' => $providerExpiry,
            ];
    });
});

test('Angel One returns nearest expiries separately for supported underlyings', function () {
    Cache::forget('angel_one.option_catalog.v3');
    $dates = [
        'NIFTY' => strtoupper(now()->addDays(7)->format('dMY')),
        'BANKNIFTY' => strtoupper(now()->addDays(14)->format('dMY')),
        'SENSEX' => strtoupper(now()->addDays(21)->format('dMY')),
        'CRUDEOIL' => strtoupper(now()->addDays(28)->format('dMY')),
    ];

    Http::fake([
        'https://margincalculator.angelone.in/OpenAPI_File/files/OpenAPIScripMaster.json' => Http::response([
            ['name' => 'NIFTY', 'expiry' => $dates['NIFTY'], 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
            ['name' => 'BANKNIFTY', 'expiry' => $dates['BANKNIFTY'], 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
            ['name' => 'SENSEX', 'expiry' => $dates['SENSEX'], 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'BFO'],
            ['name' => 'CRUDEOIL', 'expiry' => $dates['CRUDEOIL'], 'instrumenttype' => 'OPTFUT', 'exch_seg' => 'MCX'],
            ['name' => 'SENSEX', 'expiry' => $dates['SENSEX'], 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
            ['name' => 'BANKNIFTY', 'expiry' => $dates['BANKNIFTY'], 'instrumenttype' => 'FUTIDX', 'exch_seg' => 'NFO'],
        ]),
    ]);

    $user = User::factory()->create();
    foreach ($dates as $underlying => $date) {
        $this->actingAs($user)
            ->getJson(route('option-chain.angel-one.expiries', ['underlying' => $underlying]))
            ->assertOk()
            ->assertJsonPath('underlying', $underlying)
            ->assertJsonPath('expiries.0.date', now()->parse($date)->format('Y-m-d'));
    }

    Http::assertSentCount(1);
});

test('Angel One expiry discovery excludes index expiries after the India market close', function () {
    Cache::forget('angel_one.option_catalog.v3');
    $today = now('Asia/Kolkata');
    $todayExpiry = strtoupper($today->format('dMY'));
    $nextExpiry = strtoupper($today->copy()->addDay()->format('dMY'));
    $nextDate = $today->copy()->addDay()->format('Y-m-d');

    Http::fake([
        'https://margincalculator.angelone.in/OpenAPI_File/files/OpenAPIScripMaster.json' => Http::response([
            ['name' => 'NIFTY', 'expiry' => $todayExpiry, 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
            ['name' => 'NIFTY', 'expiry' => $nextExpiry, 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ]),
    ]);

    $this->travelTo(Carbon::parse($today->format('Y-m-d').' 16:00:00', 'Asia/Kolkata'));

    $this->actingAs(User::factory()->create())
        ->getJson(route('option-chain.angel-one.expiries', ['underlying' => 'NIFTY']))
        ->assertOk()
        ->assertJsonPath('expiries.0.date', $nextDate)
        ->assertJsonCount(1, 'expiries');
});

test('Angel One requests Greeks for the selected underlying and exact upcoming expiry', function () {
    Cache::forget('angel_one.option_catalog.v3');
    $expiry = now()->addDays(14)->format('Y-m-d');
    $providerExpiry = strtoupper(now()->parse($expiry)->format('dMY'));
    $master = [
        ['name' => 'BANKNIFTY', 'expiry' => $providerExpiry, 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'BANKNIFTY', 'token' => '99926009', 'instrumenttype' => 'AMXIDX', 'exch_seg' => 'NSE'],
        ['name' => 'BANKNIFTY', 'symbol' => "BANKNIFTY{$providerExpiry}50000CE", 'token' => '501', 'expiry' => $providerExpiry, 'strike' => '5000000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'BANKNIFTY', 'symbol' => "BANKNIFTY{$providerExpiry}50000PE", 'token' => '502', 'expiry' => $providerExpiry, 'strike' => '5000000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
    ];
    Http::fake(function ($request) use ($master) {
        if (str_contains($request->url(), 'OpenAPIScripMaster.json')) {
            return Http::response($master);
        }
        if (str_contains($request->url(), 'optionGreek')) {
            return Http::response([
                'status' => true,
                'data' => [
                    [
                        'strikePrice' => '50000.000000',
                        'optionType' => 'CE',
                        'delta' => '0.62',
                        'gamma' => '0.0004',
                        'theta' => '-14.1',
                        'vega' => '20.5',
                        'impliedVolatility' => '15.3',
                        'tradeVolume' => '3200',
                    ],
                ],
            ]);
        }

        $exchangeTokens = $request->data()['exchangeTokens'] ?? [];
        $exchange = array_key_first($exchangeTokens);

        return Http::response([
            'status' => true,
            'data' => ['fetched' => array_map(
                static fn (string $token): array => $exchange === 'NSE'
                    ? ['symbolToken' => $token, 'ltp' => 50000]
                    : ['symbolToken' => $token, 'ltp' => 250, 'opnInterest' => 5200, 'tradeVolume' => 3200],
                $exchangeTokens[$exchange] ?? []
            )],
        ]);
    });

    $this->actingAs(User::factory()->create())
        ->withSession([
            'angel_one.connection' => [
                'client_code' => 'TESTCLIENT',
                'secrets' => Crypt::encryptString(json_encode([
                    'api_key' => 'test-private-api-key',
                    'jwt_token' => 'test-jwt-token',
                ], JSON_THROW_ON_ERROR)),
            ],
        ])
        ->postJson(route('option-chain.angel-one.greeks'), [
            'underlying' => 'BANKNIFTY',
            'expiry' => $expiry,
        ])
        ->assertOk()
        ->assertJsonPath('underlying', 'BANKNIFTY')
        ->assertJsonPath('expiry_date', $expiry)
        ->assertJsonPath('greeks_source', 'angel_one')
        ->assertJsonPath('data.0.option_type', 'CE')
        ->assertJsonPath('data.0.ltp', 250)
        ->assertJsonPath('data.0.open_interest', 5200);

    Http::assertSent(function ($request) use ($providerExpiry): bool {
        return $request->url() === 'https://apiconnect.angelone.in/rest/secure/angelbroking/marketData/v1/optionGreek'
            && $request->data() === [
                'name' => 'BANKNIFTY',
                'expirydate' => $providerExpiry,
            ];
    });
});

test('NIFTY and BANKNIFTY native Greeks include quote-based LTP and open interest', function () {
    Cache::forget('angel_one.option_catalog.v3');
    config([
        'services.angel_one.client_local_ip' => '192.168.1.20',
        'services.angel_one.client_public_ip' => '203.0.113.20',
        'services.angel_one.mac_address' => '00:1A:2B:3C:4D:5E',
    ]);

    $expiry = now()->addDays(14)->format('Y-m-d');
    $providerExpiry = strtoupper(now()->parse($expiry)->format('dMY'));
    $master = [
        ['name' => 'NIFTY', 'token' => '99926000', 'instrumenttype' => 'AMXIDX', 'exch_seg' => 'NSE'],
        ['name' => 'BANKNIFTY', 'token' => '99926009', 'instrumenttype' => 'AMXIDX', 'exch_seg' => 'NSE'],
        ['name' => 'NIFTY', 'symbol' => "NIFTY{$providerExpiry}50000CE", 'token' => '101', 'expiry' => $providerExpiry, 'strike' => '5000000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'NIFTY', 'symbol' => "NIFTY{$providerExpiry}50000PE", 'token' => '102', 'expiry' => $providerExpiry, 'strike' => '5000000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'BANKNIFTY', 'symbol' => "BANKNIFTY{$providerExpiry}50000CE", 'token' => '201', 'expiry' => $providerExpiry, 'strike' => '5000000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'BANKNIFTY', 'symbol' => "BANKNIFTY{$providerExpiry}50000PE", 'token' => '202', 'expiry' => $providerExpiry, 'strike' => '5000000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
    ];
    Http::fake(function ($request) use ($master) {
        if (str_contains($request->url(), 'OpenAPIScripMaster.json')) {
            return Http::response($master);
        }
        if (str_contains($request->url(), 'optionGreek')) {
            return Http::response([
                'status' => true,
                'data' => [[
                    'strikePrice' => '50000.000000',
                    'optionType' => 'CE',
                    'delta' => '0.62',
                    'gamma' => '0.0004',
                    'theta' => '-14.1',
                    'vega' => '20.5',
                    'impliedVolatility' => '15.3',
                    'tradeVolume' => '3200',
                ]],
            ]);
        }

        $exchangeTokens = $request->data()['exchangeTokens'] ?? [];
        $exchange = array_key_first($exchangeTokens);

        return Http::response([
            'status' => true,
            'data' => ['fetched' => array_map(
                static fn (string $token): array => $exchange === 'NSE'
                    ? ['symbolToken' => $token, 'ltp' => 50000]
                    : ['symbolToken' => $token, 'ltp' => 180, 'opnInterest' => 8000, 'tradeVolume' => 3200],
                $exchangeTokens[$exchange] ?? []
            )],
        ]);
    });

    $user = User::factory()->create();
    foreach (['NIFTY', 'BANKNIFTY'] as $underlying) {
        $this->actingAs($user)
            ->withSession([
                'angel_one.connection' => [
                    'client_code' => 'TESTCLIENT',
                    'secrets' => Crypt::encryptString(json_encode([
                        'api_key' => 'test-private-api-key',
                        'jwt_token' => 'test-jwt-token',
                    ], JSON_THROW_ON_ERROR)),
                ],
            ])
            ->postJson(route('option-chain.angel-one.greeks'), [
                'underlying' => $underlying,
                'expiry' => $expiry,
            ])
            ->assertOk()
            ->assertJsonPath('underlying', $underlying)
            ->assertJsonPath('greeks_source', 'angel_one')
            ->assertJsonPath('data.0.strike', 50000)
            ->assertJsonPath('data.0.ltp', 180)
            ->assertJsonPath('data.0.open_interest', 8000);
    }

    Http::assertSentCount(7);
});

test('NIFTY and BANKNIFTY fall back to live quotes when native Greeks are unavailable', function () {
    Cache::forget('angel_one.option_catalog.v3');
    config([
        'services.angel_one.client_local_ip' => '192.168.1.20',
        'services.angel_one.client_public_ip' => '203.0.113.20',
        'services.angel_one.mac_address' => '00:1A:2B:3C:4D:5E',
    ]);

    $expiry = now()->addDays(30)->format('Y-m-d');
    $providerExpiry = strtoupper(now()->parse($expiry)->format('dMY'));
    $master = [
        ['name' => 'NIFTY', 'token' => '99926000', 'instrumenttype' => 'AMXIDX', 'exch_seg' => 'NSE'],
        ['name' => 'BANKNIFTY', 'token' => '99926009', 'instrumenttype' => 'AMXIDX', 'exch_seg' => 'NSE'],
        ['name' => 'NIFTY', 'symbol' => "NIFTY{$providerExpiry}25000CE", 'token' => '101', 'expiry' => $providerExpiry, 'strike' => '2500000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'NIFTY', 'symbol' => "NIFTY{$providerExpiry}25000PE", 'token' => '102', 'expiry' => $providerExpiry, 'strike' => '2500000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'BANKNIFTY', 'symbol' => "BANKNIFTY{$providerExpiry}50000CE", 'token' => '201', 'expiry' => $providerExpiry, 'strike' => '5000000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
        ['name' => 'BANKNIFTY', 'symbol' => "BANKNIFTY{$providerExpiry}50000PE", 'token' => '202', 'expiry' => $providerExpiry, 'strike' => '5000000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'NFO'],
    ];

    Http::fake(function ($request) use ($master) {
        if (str_contains($request->url(), 'optionGreek')) {
            return Http::response(['status' => false, 'message' => 'Native Greeks unavailable.'], 422);
        }

        if (str_contains($request->url(), 'OpenAPIScripMaster.json')) {
            return Http::response($master);
        }

        $exchangeTokens = $request->data()['exchangeTokens'] ?? [];
        $exchange = array_key_first($exchangeTokens);
        $tokens = $exchangeTokens[$exchange] ?? [];
        $underlyingPrices = ['99926000' => 25000, '99926009' => 50000];
        if ($exchange === 'NSE') {
            return Http::response([
                'status' => true,
                'data' => ['fetched' => array_map(
                    static fn (string $token): array => [
                        'symbolToken' => $token,
                        'ltp' => $underlyingPrices[$token],
                    ],
                    $tokens
                )],
            ]);
        }

        return Http::response([
            'status' => true,
            'data' => ['fetched' => array_map(
                static fn (string $token): array => [
                    'symbolToken' => $token,
                    'ltp' => in_array($token, ['101', '201'], true) ? 400 : 350,
                    'tradeVolume' => 125,
                ],
                $tokens
            )],
        ]);
    });

    $user = User::factory()->create();
    foreach (['NIFTY', 'BANKNIFTY'] as $underlying) {
        $this->actingAs($user)
            ->withSession([
                'angel_one.connection' => [
                    'client_code' => 'TESTCLIENT',
                    'secrets' => Crypt::encryptString(json_encode([
                        'api_key' => 'test-private-api-key',
                        'jwt_token' => 'test-jwt-token',
                    ], JSON_THROW_ON_ERROR)),
                ],
            ])
            ->postJson(route('option-chain.angel-one.greeks'), [
                'underlying' => $underlying,
                'expiry' => $expiry,
            ])
            ->assertOk()
            ->assertJsonPath('greeks_source', 'calculated_from_live_quotes')
            ->assertJsonPath('data.0.trade_volume', 125);
    }

    Http::assertSentCount(7);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/quote/')
        && (
            ($request['exchangeTokens'] ?? []) === ['NSE' => ['99926000']]
            || ($request['exchangeTokens'] ?? []) === ['NSE' => ['99926009']]
            || ($request['exchangeTokens'] ?? []) === ['NFO' => ['101', '102']]
            || ($request['exchangeTokens'] ?? []) === ['NFO' => ['201', '202']]
        )
    );
});

test('SENSEX and CRUDEOIL use quotes to calculate Greeks from their correct market segments', function () {
    Cache::forget('angel_one.option_catalog.v3');
    config([
        'services.angel_one.client_local_ip' => '192.168.1.20',
        'services.angel_one.client_public_ip' => '203.0.113.20',
        'services.angel_one.mac_address' => '00:1A:2B:3C:4D:5E',
    ]);

    $expiry = now()->addDays(30)->format('Y-m-d');
    $providerExpiry = strtoupper(now()->parse($expiry)->format('dMY'));
    $futureExpiry = strtoupper(now()->addDays(35)->format('dMY'));
    $master = [
        ['name' => 'SENSEX', 'token' => '99919000', 'instrumenttype' => 'AMXIDX', 'exch_seg' => 'BSE'],
        ['name' => 'SENSEX', 'symbol' => "SENSEX{$providerExpiry}72000CE", 'token' => '301', 'expiry' => $providerExpiry, 'strike' => '7200000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'BFO'],
        ['name' => 'SENSEX', 'symbol' => "SENSEX{$providerExpiry}72000PE", 'token' => '302', 'expiry' => $providerExpiry, 'strike' => '7200000', 'instrumenttype' => 'OPTIDX', 'exch_seg' => 'BFO'],
        ['name' => 'CRUDEOIL', 'symbol' => "CRUDEOIL{$providerExpiry}7000CE", 'token' => '401', 'expiry' => $providerExpiry, 'strike' => '700000', 'instrumenttype' => 'OPTFUT', 'exch_seg' => 'MCX'],
        ['name' => 'CRUDEOIL', 'symbol' => "CRUDEOIL{$providerExpiry}7000PE", 'token' => '402', 'expiry' => $providerExpiry, 'strike' => '700000', 'instrumenttype' => 'OPTFUT', 'exch_seg' => 'MCX'],
        ['name' => 'CRUDEOIL', 'symbol' => "CRUDEOIL{$futureExpiry}FUT", 'token' => '501', 'expiry' => $futureExpiry, 'instrumenttype' => 'FUTCOM', 'exch_seg' => 'MCX'],
    ];

    Http::fake(function ($request) use ($master) {
        if (str_contains($request->url(), 'OpenAPIScripMaster.json')) {
            return Http::response($master);
        }

        $tokensByExchange = $request->data()['exchangeTokens'] ?? [];
        $exchange = array_key_first($tokensByExchange);
        $tokens = $tokensByExchange[$exchange] ?? [];
        $quotes = match ([$exchange, $tokens]) {
            ['BSE', ['99919000']] => [['symbolToken' => '99919000', 'ltp' => 72000]],
            ['BFO', ['301', '302']] => [
                ['symbolToken' => '301', 'ltp' => 1200, 'opnInterest' => 14000, 'tradeVolume' => 800],
                ['symbolToken' => '302', 'ltp' => 1100, 'opnInterest' => 16000, 'tradeVolume' => 700],
            ],
            ['MCX', ['501']] => [['symbolToken' => '501', 'ltp' => 7000]],
            ['MCX', ['401', '402']] => [
                ['symbolToken' => '401', 'ltp' => 180, 'opnInterest' => 4000, 'tradeVolume' => 500],
                ['symbolToken' => '402', 'ltp' => 170, 'opnInterest' => 5000, 'tradeVolume' => 450],
            ],
            default => [],
        };

        return Http::response(['status' => true, 'data' => ['fetched' => $quotes]]);
    });

    $user = User::factory()->create();
    foreach (['SENSEX', 'CRUDEOIL'] as $underlying) {
        $this->actingAs($user)
            ->withSession([
                'angel_one.connection' => [
                    'client_code' => 'TESTCLIENT',
                    'secrets' => Crypt::encryptString(json_encode([
                        'api_key' => 'test-private-api-key',
                        'jwt_token' => 'test-jwt-token',
                    ], JSON_THROW_ON_ERROR)),
                ],
            ])
            ->postJson(route('option-chain.angel-one.greeks'), [
                'underlying' => $underlying,
                'expiry' => $expiry,
            ])
            ->assertOk()
            ->assertJsonPath('greeks_source', 'calculated_from_live_quotes')
            ->assertJsonPath('data.0.option_type', 'CE')
            ->assertJsonPath('data.0.trade_volume', $underlying === 'SENSEX' ? 800 : 500)
            ->assertJsonPath('data.0.ltp', $underlying === 'SENSEX' ? 1200 : 180)
            ->assertJsonPath('data.0.open_interest', $underlying === 'SENSEX' ? 14000 : 4000)
            ->assertJsonStructure([
                'data' => [['strike', 'option_type', 'delta', 'gamma', 'theta', 'vega', 'implied_volatility', 'ltp', 'open_interest', 'trade_volume']],
            ]);
    }

    Http::assertSent(function ($request): bool {
        if (! str_ends_with($request->url(), '/quote/')) {
            return false;
        }

        $exchangeTokens = $request->data()['exchangeTokens'] ?? [];

        return $request['mode'] === 'FULL'
            && (
                $exchangeTokens === ['BSE' => ['99919000']]
                || $exchangeTokens === ['BFO' => ['301', '302']]
                || $exchangeTokens === ['MCX' => ['501']]
                || $exchangeTokens === ['MCX' => ['401', '402']]
            );
    });
    Http::assertSentCount(5);
});

test('Angel One rejects a date not listed for the selected underlying', function () {
    Cache::forget('angel_one.option_catalog.v3');
    $listedExpiry = now()->addDays(14)->format('Y-m-d');
    Http::fake([
        'https://margincalculator.angelone.in/OpenAPI_File/files/OpenAPIScripMaster.json' => Http::response([
            [
                'name' => 'SENSEX',
                'expiry' => strtoupper(now()->parse($listedExpiry)->format('dMY')),
                'instrumenttype' => 'OPTIDX',
                'exch_seg' => 'BFO',
            ],
        ]),
    ]);

    $this->actingAs(User::factory()->create())
        ->withSession([
            'angel_one.connection' => [
                'secrets' => Crypt::encryptString(json_encode([
                    'api_key' => 'test-private-api-key',
                    'jwt_token' => 'test-jwt-token',
                ], JSON_THROW_ON_ERROR)),
            ],
        ])
        ->postJson(route('option-chain.angel-one.greeks'), [
            'underlying' => 'SENSEX',
            'expiry' => now()->addDays(7)->format('Y-m-d'),
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Angel One has no option contracts for SENSEX on '.now()->addDays(7)->format('Y-m-d').'.');

    Http::assertSentCount(1);
});

test('option Greeks endpoint requires an Angel One connection and a valid market selection', function () {
    Http::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('option-chain.angel-one.greeks'), [
            'underlying' => 'NIFTY',
            'expiry' => now()->addDays(7)->format('Y-m-d'),
        ])
        ->assertConflict();

    $this->withSession([
        'angel_one.connection' => [
            'secrets' => Crypt::encryptString(json_encode([
                'api_key' => 'test-private-api-key',
                'jwt_token' => 'test-jwt-token',
            ], JSON_THROW_ON_ERROR)),
        ],
    ])->postJson(route('option-chain.angel-one.greeks'), [
        'underlying' => 'NOT-AN-UNDERLYING',
        'expiry' => '2020-01-01',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['underlying', 'expiry']);

    Http::assertNothingSent();
});
