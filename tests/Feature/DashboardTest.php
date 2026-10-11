<?php

use App\Models\Ticker;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
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
        ->assertSee('id="option-expiry" type="date"', false)
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
    config([
        'services.angel_one.client_local_ip' => '192.168.1.20',
        'services.angel_one.client_public_ip' => '203.0.113.20',
        'services.angel_one.mac_address' => '00:1A:2B:3C:4D:5E',
    ]);

    Http::fake([
        'https://apiconnect.angelone.in/rest/secure/angelbroking/marketData/v1/optionGreek' => Http::response([
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
        ]),
    ]);

    $expiry = now()->addDays(7)->format('Y-m-d');
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
        ->assertJsonPath('data.0.option_type', 'CE')
        ->assertJsonPath('data.0.strike', 25000)
        ->assertJsonPath('data.0.implied_volatility', 14.6)
        ->assertJsonPath('data.1.option_type', 'PE')
        ->assertJsonMissing(['jwt_token' => 'test-jwt-token']);

    Http::assertSent(function ($request) use ($expiry): bool {
        return $request->url() === 'https://apiconnect.angelone.in/rest/secure/angelbroking/marketData/v1/optionGreek'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer test-jwt-token')
            && $request->hasHeader('X-PrivateKey', 'test-private-api-key')
            && $request->hasHeader('X-ClientLocalIP', '192.168.1.20')
            && $request->hasHeader('X-ClientPublicIP', '203.0.113.20')
            && $request->hasHeader('X-MACAddress', '00:1A:2B:3C:4D:5E')
            && $request->data() === [
                'name' => 'NIFTY',
                'expirydate' => strtoupper(now()->parse($expiry)->format('dMY')),
            ];
    });
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
