<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Events\OptionGreeksUpdated;
use App\Services\AngelOneLiveOptionGreeksService;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

final class BroadcastLiveOptionGreeks extends Command
{
    protected $signature = 'option-chain:broadcast-live';

    protected $description = 'Fetch active Angel One option data and publish changes over WebSockets';

    public function handle(AngelOneLiveOptionGreeksService $greeksService): int
    {
        $subscriptions = DB::table('angel_one_market_subscriptions')
            ->join('angel_one_connections', 'angel_one_connections.user_id', '=', 'angel_one_market_subscriptions.user_id')
            ->where('angel_one_market_subscriptions.last_seen_at', '>=', now()->subSeconds(45))
            ->select([
                'angel_one_market_subscriptions.user_id',
                'angel_one_market_subscriptions.underlying',
                'angel_one_market_subscriptions.expiry',
                'angel_one_market_subscriptions.payload_hash',
                'angel_one_connections.encrypted_secrets',
            ])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $credentials = json_decode(
                    Crypt::decryptString($subscription->encrypted_secrets),
                    true,
                    flags: JSON_THROW_ON_ERROR
                );

                if (
                    ! is_array($credentials)
                    || ! is_string($credentials['api_key'] ?? null)
                    || ! is_string($credentials['jwt_token'] ?? null)
                    || $credentials['api_key'] === ''
                    || $credentials['jwt_token'] === ''
                ) {
                    throw new RuntimeException('Stored Angel One credentials are incomplete.');
                }

                $result = $greeksService->get(
                    $subscription->underlying,
                    $subscription->expiry,
                    $credentials,
                    $subscription->user_id
                );
                $payloadHash = hash('sha256', json_encode([
                    $result['source'],
                    $result['data'],
                ], JSON_THROW_ON_ERROR));

                if (
                    is_string($subscription->payload_hash)
                    && hash_equals($subscription->payload_hash, $payloadHash)
                ) {
                    continue;
                }

                try {
                    event(new OptionGreeksUpdated(
                        (string) $subscription->user_id,
                        $subscription->underlying,
                        $subscription->expiry,
                        $result['source'],
                        $result['data'],
                        now()->toIso8601String()
                    ));
                } catch (BroadcastException $exception) {
                    Log::warning('Could not publish live Greeks to the WebSocket server.', [
                        'user_id' => $subscription->user_id,
                        'underlying' => $subscription->underlying,
                        'exception' => $exception->getMessage(),
                    ]);

                    continue;
                }

                DB::table('angel_one_market_subscriptions')
                    ->where('user_id', $subscription->user_id)
                    ->update([
                        'payload_hash' => $payloadHash,
                        'updated_at' => now(),
                    ]);
            } catch (ConnectionException|DecryptException|JsonException|RuntimeException $exception) {
                Log::warning('Live Angel One market refresh failed.', [
                    'user_id' => $subscription->user_id,
                    'underlying' => $subscription->underlying,
                    'expiry' => $subscription->expiry,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
