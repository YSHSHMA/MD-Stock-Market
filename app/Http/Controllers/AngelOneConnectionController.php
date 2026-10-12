<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AngelOneAuthService;
use App\Services\AngelOneLiveOptionGreeksService;
use App\Services\AngelOneOptionExpiryService;
use DateTimeImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final class AngelOneConnectionController extends Controller
{
    public function connect(Request $request, AngelOneAuthService $authService): JsonResponse
    {
        $credentials = $request->validate([
            'clientcode' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:128'],
            'totp' => ['required', 'regex:/^[0-9]{6}$/'],
            'api_key' => ['required', 'string', 'max:255'],
        ]);

        $identity = [
            'client_local_ip' => config('services.angel_one.client_local_ip'),
            'client_public_ip' => config('services.angel_one.client_public_ip'),
            'mac_address' => config('services.angel_one.mac_address'),
        ];

        $missingIdentity = [];

        if (! filter_var($identity['client_local_ip'], FILTER_VALIDATE_IP)) {
            $missingIdentity[] = 'ANGEL_ONE_CLIENT_LOCAL_IP';
        }

        if (! filter_var($identity['client_public_ip'], FILTER_VALIDATE_IP)) {
            $missingIdentity[] = 'ANGEL_ONE_CLIENT_PUBLIC_IP';
        }

        if (
            ! is_string($identity['mac_address'])
            || ! preg_match('/^[0-9a-fA-F]{2}([:-][0-9a-fA-F]{2}){5}$/', $identity['mac_address'])
        ) {
            $missingIdentity[] = 'ANGEL_ONE_MAC_ADDRESS';
        }

        if ($missingIdentity !== []) {
            return response()->json([
                'message' => 'Angel One network identity is incomplete. Configure these values in the server environment: '.implode(', ', $missingIdentity).'.',
            ], 503);
        }

        $credentials = [
            ...$credentials,
            ...$identity,
            'state' => (string) Str::uuid(),
        ];

        try {
            $tokens = $authService->login($credentials);
        } catch (ConnectionException $exception) {
            Log::warning('Angel One login request could not reach the provider.', [
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'Could not reach Angel One. Check the server connection and try again.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $request->session()->put('angel_one.connection', [
            'client_code' => $credentials['clientcode'],
            'secrets' => Crypt::encryptString(json_encode([
                'api_key' => $credentials['api_key'],
                ...$tokens,
            ], JSON_THROW_ON_ERROR)),
            'connected_at' => now()->toIso8601String(),
        ]);

        DB::table('angel_one_connections')->upsert(
            [[
                'user_id' => $request->user()->id,
                'client_code' => $credentials['clientcode'],
                'encrypted_secrets' => Crypt::encryptString(json_encode([
                    'api_key' => $credentials['api_key'],
                    ...$tokens,
                ], JSON_THROW_ON_ERROR)),
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['user_id'],
            ['client_code', 'encrypted_secrets', 'updated_at']
        );

        return response()->json([
            'message' => 'Angel One login succeeded. Credentials are encrypted for your account and live subscriptions.',
            'connection' => [
                'client_code' => $credentials['clientcode'],
                'connected_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function subscribe(Request $request, AngelOneOptionExpiryService $expiryService): JsonResponse
    {
        $input = $request->validate([
            'underlying' => ['required', 'string', 'in:NIFTY,BANKNIFTY,SENSEX,CRUDEOIL'],
            'expiry' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $connection = DB::table('angel_one_connections')
            ->where('user_id', $request->user()->id)
            ->first();

        if ($connection === null) {
            return response()->json([
                'message' => 'Connect your Angel One account before starting live updates.',
            ], 409);
        }

        try {
            $expiries = $expiryService->getExpiries($input['underlying']);
            if (! in_array($input['expiry'], array_column($expiries, 'date'), true)) {
                return response()->json([
                    'message' => 'That expiry is not listed for this underlying.',
                ], 422);
            }
        } catch (ConnectionException $exception) {
            Log::warning('Could not validate live market subscription expiry.', [
                'user_id' => $request->user()->id,
                'underlying' => $input['underlying'],
            ]);

            return response()->json([
                'message' => 'Could not validate this expiry with Angel One. Try again shortly.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        $existingSubscription = DB::table('angel_one_market_subscriptions')
            ->where('user_id', $request->user()->id)
            ->first();
        $sameSelection = $existingSubscription !== null
            && $existingSubscription->underlying === $input['underlying']
            && $existingSubscription->expiry === $input['expiry'];

        DB::table('angel_one_market_subscriptions')->upsert(
            [[
                'user_id' => $request->user()->id,
                'underlying' => $input['underlying'],
                'expiry' => $input['expiry'],
                'last_seen_at' => now(),
                'payload_hash' => $sameSelection ? $existingSubscription->payload_hash : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['user_id'],
            ['underlying', 'expiry', 'last_seen_at', 'payload_hash', 'updated_at']
        );

        return response()->json([
            'message' => 'Live option updates enabled.',
            'underlying' => $input['underlying'],
            'expiry' => $input['expiry'],
        ]);
    }

    public function expiries(Request $request, AngelOneOptionExpiryService $expiryService): JsonResponse
    {
        $input = $request->validate([
            'underlying' => ['required', 'string', 'in:NIFTY,BANKNIFTY,SENSEX,CRUDEOIL'],
        ]);

        try {
            $expiries = $expiryService->getExpiries($input['underlying']);
        } catch (ConnectionException $exception) {
            Log::warning('Angel One contract master request could not reach the provider.', [
                'user_id' => $request->user()->id,
                'underlying' => $input['underlying'],
            ]);

            return response()->json([
                'message' => 'Could not reach Angel One contract data. Try again later.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 503);
        }

        return response()->json([
            'underlying' => $input['underlying'],
            'expiries' => $expiries,
        ]);
    }

    public function greeks(
        Request $request,
        AngelOneLiveOptionGreeksService $greeksService
    ): JsonResponse {
        $input = $request->validate([
            'underlying' => ['required', 'string', 'in:NIFTY,BANKNIFTY,SENSEX,CRUDEOIL'],
            'expiry' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        $connection = $request->session()->get('angel_one.connection');
        if (! is_array($connection) || ! is_string($connection['secrets'] ?? null)) {
            return response()->json([
                'message' => 'Connect your Angel One account before requesting live option Greeks.',
            ], 409);
        }

        try {
            $secrets = json_decode(
                Crypt::decryptString($connection['secrets']),
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (DecryptException|JsonException $exception) {
            Log::warning('Stored Angel One credentials could not be decrypted.', [
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'message' => 'The saved Angel One session is no longer readable. Disconnect and connect again.',
            ], 409);
        }

        if (
            ! is_array($secrets)
            || ! is_string($secrets['api_key'] ?? null)
            || ! is_string($secrets['jwt_token'] ?? null)
            || $secrets['api_key'] === ''
            || $secrets['jwt_token'] === ''
        ) {
            return response()->json([
                'message' => 'The saved Angel One session is incomplete. Disconnect and connect again.',
            ], 409);
        }

        try {
            $result = $greeksService->get(
                $input['underlying'],
                $input['expiry'],
                $secrets,
                (string) $request->user()->id
            );
        } catch (ConnectionException $exception) {
            Log::warning('Angel One option Greeks request could not reach the provider.', [
                'user_id' => $request->user()->id,
                'underlying' => $input['underlying'],
            ]);

            return response()->json([
                'message' => 'Could not reach Angel One. Check the server connection and try again.',
            ], 502);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'underlying' => $input['underlying'],
            'expiry' => strtoupper((new DateTimeImmutable($input['expiry']))->format('dMY')),
            'expiry_date' => (new DateTimeImmutable($input['expiry']))->format('Y-m-d'),
            'greeks_source' => $result['source'],
            'data' => $result['data'],
        ]);
    }

    public function disconnect(Request $request): JsonResponse
    {
        $request->session()->forget('angel_one.connection');
        DB::table('angel_one_market_subscriptions')->where('user_id', $request->user()->id)->delete();
        DB::table('angel_one_connections')->where('user_id', $request->user()->id)->delete();

        return response()->json([
            'message' => 'Angel One session disconnected.',
        ]);
    }
}
