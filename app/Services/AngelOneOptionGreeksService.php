<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AngelOneOptionGreeksService
{
    private const OPTION_GREEKS_URL = 'https://apiconnect.angelone.in/rest/secure/angelbroking/marketData/v1/optionGreek';

    /**
     * @return list<array{
     *     strike: float,
     *     option_type: 'CE'|'PE',
     *     delta: float,
     *     gamma: float,
     *     theta: float,
     *     vega: float,
     *     implied_volatility: float,
     *     trade_volume: int
     * }>
     *
     * @throws ConnectionException
     */
    public function getOptionGreeks(
        string $underlying,
        string $expiry,
        string $jwtToken,
        string $apiKey
    ): array {
        $identity = [
            'client_local_ip' => config('services.angel_one.client_local_ip'),
            'client_public_ip' => config('services.angel_one.client_public_ip'),
            'mac_address' => config('services.angel_one.mac_address'),
        ];

        if (
            ! filter_var($identity['client_local_ip'], FILTER_VALIDATE_IP)
            || ! filter_var($identity['client_public_ip'], FILTER_VALIDATE_IP)
            || ! is_string($identity['mac_address'])
            || ! preg_match('/^[0-9a-fA-F]{2}([:-][0-9a-fA-F]{2}){5}$/', $identity['mac_address'])
        ) {
            throw new RuntimeException('Angel One network identity is incomplete. Check the server environment configuration.');
        }

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($jwtToken)
            ->withHeaders([
                'X-UserType' => 'USER',
                'X-SourceID' => 'WEB',
                'X-ClientLocalIP' => $identity['client_local_ip'],
                'X-ClientPublicIP' => $identity['client_public_ip'],
                'X-MACAddress' => $identity['mac_address'],
                'X-PrivateKey' => $apiKey,
            ])
            ->connectTimeout(5)
            ->timeout(15)
            ->post(self::OPTION_GREEKS_URL, [
                'name' => $underlying,
                'expirydate' => $expiry,
            ]);

        $payload = $response->json();

        if (
            ! $response->successful()
            || ! is_array($payload)
            || ($payload['status'] ?? false) !== true
            || ! is_array($payload['data'] ?? null)
        ) {
            throw new RuntimeException(
                is_array($payload) && is_string($payload['message'] ?? null)
                    ? $payload['message']
                    : 'Angel One did not return option Greeks for this underlying and expiry.'
            );
        }

        $rows = [];

        foreach ($payload['data'] as $row) {
            if (! is_array($row)) {
                throw new RuntimeException('Angel One returned an invalid option Greeks record.');
            }

            $optionType = strtoupper((string) ($row['optionType'] ?? ''));
            if (! in_array($optionType, ['CE', 'PE'], true)) {
                throw new RuntimeException('Angel One returned an unsupported option type.');
            }

            $volume = $row['tradeVolume'] ?? null;
            if (! is_numeric($volume) || (float) $volume < 0) {
                throw new RuntimeException('Angel One returned an invalid option volume.');
            }

            $rows[] = [
                'strike' => $this->numericField($row, 'strikePrice'),
                'option_type' => $optionType,
                'delta' => $this->numericField($row, 'delta'),
                'gamma' => $this->numericField($row, 'gamma'),
                'theta' => $this->numericField($row, 'theta'),
                'vega' => $this->numericField($row, 'vega'),
                'implied_volatility' => $this->numericField($row, 'impliedVolatility'),
                'trade_volume' => (int) $volume,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$left['strike'], $left['option_type']] <=> [$right['strike'], $right['option_type']]
        );

        if ($rows === []) {
            throw new RuntimeException('Angel One returned no option Greeks for this underlying and expiry.');
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function numericField(array $row, string $field): float
    {
        if (! is_numeric($row[$field] ?? null)) {
            throw new RuntimeException("Angel One returned an invalid {$field} value.");
        }

        return (float) $row[$field];
    }
}
