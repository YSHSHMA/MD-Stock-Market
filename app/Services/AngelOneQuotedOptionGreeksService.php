<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class AngelOneQuotedOptionGreeksService
{
    private const QUOTE_URL = 'https://apiconnect.angelone.in/rest/secure/angelbroking/market/v1/quote/';

    private const RISK_FREE_RATE = 0.065;

    /**
     * @param array{
     *     options: list<array{token: string, symbol: string, strike: float, option_type: 'CE'|'PE'}>,
     *     reference: array{exchange: string, token: string}
     * } $contracts
     * @return list<array{
     *     strike: float,
     *     option_type: 'CE'|'PE',
     *     delta: float,
     *     gamma: float,
     *     theta: float,
     *     vega: float,
     *     implied_volatility: float,
     *     ltp: float,
     *     open_interest: ?int,
     *     trade_volume: int
     * }>
     *
     * @throws ConnectionException
     */
    public function getOptionGreeks(
        string $underlying,
        string $expiry,
        array $contracts,
        string $jwtToken,
        string $apiKey
    ): array {
        $identity = $this->networkIdentity();

        $referenceQuotes = $this->fetchQuotes(
            [$contracts['reference']['exchange'] => [$contracts['reference']['token']]],
            $jwtToken,
            $apiKey,
            $identity
        );
        $reference = $this->quoteForToken($referenceQuotes, $contracts['reference']['token']);
        $underlyingPrice = $this->positiveNumber($reference['ltp'] ?? null);

        if ($underlyingPrice === null) {
            throw new RuntimeException("Angel One returned no live reference price for {$underlying}.");
        }

        $nearbyStrikes = array_values(array_unique(array_column($contracts['options'], 'strike')));
        usort($nearbyStrikes, static fn (float $left, float $right): int => abs($left - $underlyingPrice) <=> abs($right - $underlyingPrice)
        );
        $nearbyStrikes = array_slice($nearbyStrikes, 0, 24);
        $nearbyStrikeLookup = array_fill_keys(array_map('strval', $nearbyStrikes), true);
        $options = array_values(array_filter(
            $contracts['options'],
            static fn (array $option): bool => isset($nearbyStrikeLookup[(string) $option['strike']])
        ));

        if ($options === []) {
            throw new RuntimeException("Angel One lists no quoted option strikes for {$underlying} on {$expiry}.");
        }

        usleep(1_100_000);
        $optionExchange = match ($underlying) {
            'NIFTY', 'BANKNIFTY' => 'NFO',
            'SENSEX' => 'BFO',
            'CRUDEOIL' => 'MCX',
        };
        $optionQuotes = $this->fetchQuotes(
            [$optionExchange => array_column($options, 'token')],
            $jwtToken,
            $apiKey,
            $identity
        );

        $expiryDate = new DateTimeImmutable(
            $expiry.($underlying === 'CRUDEOIL' ? ' 23:30:00' : ' 15:30:00'),
            new DateTimeZone('Asia/Kolkata')
        );
        $yearsToExpiry = max(
            0,
            ($expiryDate->getTimestamp() - now()->timestamp) / (365 * 24 * 60 * 60)
        );
        $model = $underlying === 'CRUDEOIL' ? 'black76' : 'black-scholes';
        $rows = [];

        foreach ($options as $option) {
            $quote = $this->quoteForToken($optionQuotes, $option['token']);
            $price = $this->positiveNumber($quote['ltp'] ?? null);
            if ($price === null) {
                continue;
            }

            $impliedVolatility = $this->impliedVolatility(
                $price,
                $underlyingPrice,
                $option['strike'],
                $yearsToExpiry,
                $option['option_type'],
                $model
            );
            if ($impliedVolatility === null) {
                continue;
            }

            $greeks = $this->calculateGreeks(
                $underlyingPrice,
                $option['strike'],
                $yearsToExpiry,
                $impliedVolatility,
                $option['option_type'],
                $model
            );

            $rows[] = [
                'strike' => $option['strike'],
                'option_type' => $option['option_type'],
                'delta' => $greeks['delta'],
                'gamma' => $greeks['gamma'],
                'theta' => $greeks['theta'],
                'vega' => $greeks['vega'],
                'implied_volatility' => $impliedVolatility * 100,
                'ltp' => $price,
                'open_interest' => $this->openInterest($quote),
                'trade_volume' => max(0, (int) ($quote['tradeVolume'] ?? 0)),
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$left['strike'], $left['option_type']] <=> [$right['strike'], $right['option_type']]
        );

        if ($rows === []) {
            throw new RuntimeException(
                "Angel One returned no priceable {$underlying} options for {$expiry}. Check market hours, quotes, and contract liquidity."
            );
        }

        return $rows;
    }

    /**
     * Add quote fields to native Greeks, limiting the quote request to the closest 24 strikes.
     *
     * @param  list<array<string, int|float|string>>  $rows
     * @param array{
     *     options: list<array{token: string, symbol: string, strike: float, option_type: 'CE'|'PE'}>,
     *     reference: array{exchange: string, token: string}
     * } $contracts
     * @param  array<string, string>  $credentials
     * @return list<array<string, int|float|string|null>>
     *
     * @throws ConnectionException
     */
    public function addMarketData(
        array $rows,
        string $underlying,
        array $contracts,
        string $jwtToken,
        string $apiKey
    ): array {
        $identity = $this->networkIdentity();
        $referenceQuotes = $this->fetchQuotes(
            [$contracts['reference']['exchange'] => [$contracts['reference']['token']]],
            $jwtToken,
            $apiKey,
            $identity
        );
        $reference = $this->quoteForToken($referenceQuotes, $contracts['reference']['token']);
        $underlyingPrice = $this->positiveNumber($reference['ltp'] ?? null);

        if ($underlyingPrice === null) {
            throw new RuntimeException("Angel One returned no live reference price for {$underlying}.");
        }

        $strikes = array_values(array_unique(array_map(
            static fn (array $row): float => (float) $row['strike'],
            $rows
        )));
        usort($strikes, static fn (float $left, float $right): int => abs($left - $underlyingPrice) <=> abs($right - $underlyingPrice));
        $nearbyStrikes = array_fill_keys(
            array_map(static fn (float $strike): string => number_format($strike, 8, '.', ''), array_slice($strikes, 0, 24)),
            true
        );
        $rowTypes = [];
        foreach ($rows as $row) {
            $rowTypes[number_format((float) $row['strike'], 8, '.', '').':'.$row['option_type']] = true;
        }

        $options = array_values(array_filter(
            $contracts['options'],
            static fn (array $option): bool => isset($nearbyStrikes[number_format($option['strike'], 8, '.', '')])
                && isset($rowTypes[number_format($option['strike'], 8, '.', '').':'.$option['option_type']])
        ));
        if ($options === []) {
            throw new RuntimeException("Angel One has no contract tokens matching {$underlying} option Greeks.");
        }

        usleep(1_100_000);
        $optionExchange = match ($underlying) {
            'NIFTY', 'BANKNIFTY' => 'NFO',
            'SENSEX' => 'BFO',
            'CRUDEOIL' => 'MCX',
        };
        $optionQuotes = $this->fetchQuotes(
            [$optionExchange => array_column($options, 'token')],
            $jwtToken,
            $apiKey,
            $identity
        );
        $quotesByContract = [];

        foreach ($options as $option) {
            $quote = $this->quoteForToken($optionQuotes, $option['token']);
            $ltp = $this->positiveNumber($quote['ltp'] ?? null);
            if ($ltp === null) {
                continue;
            }

            $quotesByContract[number_format($option['strike'], 8, '.', '').':'.$option['option_type']] = [
                'ltp' => $ltp,
                'open_interest' => $this->openInterest($quote),
                'trade_volume' => is_numeric($quote['tradeVolume'] ?? null)
                    ? max(0, (int) $quote['tradeVolume'])
                    : null,
            ];
        }

        $enrichedRows = [];
        foreach ($rows as $row) {
            $key = number_format((float) $row['strike'], 8, '.', '').':'.$row['option_type'];
            if (! isset($quotesByContract[$key])) {
                continue;
            }

            $quote = $quotesByContract[$key];
            $enrichedRows[] = [
                ...$row,
                'ltp' => $quote['ltp'],
                'open_interest' => $quote['open_interest'],
                'trade_volume' => $quote['trade_volume'] ?? $row['trade_volume'],
            ];
        }

        if ($enrichedRows === []) {
            throw new RuntimeException("Angel One returned no live option quotes for {$underlying}.");
        }

        return $enrichedRows;
    }

    /**
     * @param  array<string, list<string>>  $exchangeTokens
     * @param  array<string, string>  $identity
     * @return list<array<string, mixed>>
     *
     * @throws ConnectionException
     */
    private function fetchQuotes(
        array $exchangeTokens,
        string $jwtToken,
        string $apiKey,
        array $identity
    ): array {
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
            ->post(self::QUOTE_URL, [
                'mode' => 'FULL',
                'exchangeTokens' => $exchangeTokens,
            ]);

        $payload = $response->json();
        if (
            ! $response->successful()
            || ! is_array($payload)
            || ($payload['status'] ?? false) !== true
            || ! is_array($payload['data']['fetched'] ?? null)
        ) {
            throw new RuntimeException(
                is_array($payload) && is_string($payload['message'] ?? null)
                    ? $payload['message']
                    : 'Angel One did not return option market quotes.'
            );
        }

        return array_values(array_filter($payload['data']['fetched'], 'is_array'));
    }

    /**
     * @param  list<array<string, mixed>>  $quotes
     * @return array<string, mixed>
     */
    private function quoteForToken(array $quotes, string $token): array
    {
        foreach ($quotes as $quote) {
            if ((string) ($quote['symbolToken'] ?? '') === $token) {
                return $quote;
            }
        }

        return [];
    }

    private function positiveNumber(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /**
     * @return array{client_local_ip: string, client_public_ip: string, mac_address: string}
     */
    private function networkIdentity(): array
    {
        $identity = [
            'client_local_ip' => config('services.angel_one.client_local_ip'),
            'client_public_ip' => config('services.angel_one.client_public_ip'),
            'mac_address' => config('services.angel_one.mac_address'),
        ];

        if (
            ! is_string($identity['client_local_ip'])
            || ! filter_var($identity['client_local_ip'], FILTER_VALIDATE_IP)
            || ! is_string($identity['client_public_ip'])
            || ! filter_var($identity['client_public_ip'], FILTER_VALIDATE_IP)
            || ! is_string($identity['mac_address'])
            || ! preg_match('/^[0-9a-fA-F]{2}([:-][0-9a-fA-F]{2}){5}$/', $identity['mac_address'])
        ) {
            throw new RuntimeException('Angel One network identity is incomplete. Check the server environment configuration.');
        }

        return $identity;
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function openInterest(array $quote): ?int
    {
        $value = $quote['opnInterest'] ?? $quote['openInterest'] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_numeric($value) || (float) $value < 0) {
            throw new RuntimeException('Angel One returned an invalid option open-interest value.');
        }

        return (int) $value;
    }

    private function impliedVolatility(
        float $price,
        float $underlying,
        float $strike,
        float $time,
        string $optionType,
        string $model
    ): ?float {
        if ($time <= 0) {
            return null;
        }

        $intrinsic = $model === 'black76'
            ? exp(-self::RISK_FREE_RATE * $time) * ($optionType === 'CE' ? max($underlying - $strike, 0) : max($strike - $underlying, 0))
            : ($optionType === 'CE'
                ? max($underlying - $strike * exp(-self::RISK_FREE_RATE * $time), 0)
                : max($strike * exp(-self::RISK_FREE_RATE * $time) - $underlying, 0));

        if ($price <= $intrinsic) {
            return null;
        }

        $lower = 0.0001;
        $upper = 5.0;
        if ($this->optionPrice($underlying, $strike, $time, $upper, $optionType, $model) < $price) {
            return null;
        }

        for ($iteration = 0; $iteration < 64; $iteration++) {
            $middle = ($lower + $upper) / 2;
            if ($this->optionPrice($underlying, $strike, $time, $middle, $optionType, $model) < $price) {
                $lower = $middle;
            } else {
                $upper = $middle;
            }
        }

        return ($lower + $upper) / 2;
    }

    private function optionPrice(
        float $underlying,
        float $strike,
        float $time,
        float $volatility,
        string $optionType,
        string $model
    ): float {
        $rootTime = sqrt($time);
        $volatilityTime = $volatility * $rootTime;
        $discount = exp(-self::RISK_FREE_RATE * $time);
        $d1 = (log($underlying / $strike) + ($model === 'black76' ? 0.5 * $volatility ** 2 : self::RISK_FREE_RATE + 0.5 * $volatility ** 2) * $time) / $volatilityTime;
        $d2 = $d1 - $volatilityTime;

        if ($model === 'black76') {
            return $optionType === 'CE'
                ? $discount * ($underlying * $this->normalCdf($d1) - $strike * $this->normalCdf($d2))
                : $discount * ($strike * $this->normalCdf(-$d2) - $underlying * $this->normalCdf(-$d1));
        }

        return $optionType === 'CE'
            ? $underlying * $this->normalCdf($d1) - $strike * $discount * $this->normalCdf($d2)
            : $strike * $discount * $this->normalCdf(-$d2) - $underlying * $this->normalCdf(-$d1);
    }

    /**
     * @return array{delta: float, gamma: float, theta: float, vega: float}
     */
    private function calculateGreeks(
        float $underlying,
        float $strike,
        float $time,
        float $volatility,
        string $optionType,
        string $model
    ): array {
        $rootTime = sqrt($time);
        $volatilityTime = $volatility * $rootTime;
        $discount = exp(-self::RISK_FREE_RATE * $time);
        $d1 = (log($underlying / $strike) + ($model === 'black76' ? 0.5 * $volatility ** 2 : self::RISK_FREE_RATE + 0.5 * $volatility ** 2) * $time) / $volatilityTime;
        $d2 = $d1 - $volatilityTime;
        $density = exp(-0.5 * $d1 ** 2) / sqrt(2 * M_PI);
        $call = $optionType === 'CE';

        if ($model === 'black76') {
            $price = $this->optionPrice($underlying, $strike, $time, $volatility, $optionType, $model);

            return [
                'delta' => $discount * ($call ? $this->normalCdf($d1) : -$this->normalCdf(-$d1)),
                'gamma' => $discount * $density / ($underlying * $volatilityTime),
                'theta' => (-$discount * $underlying * $density * $volatility / (2 * $rootTime) + self::RISK_FREE_RATE * $price) / 365,
                'vega' => $underlying * $discount * $density * $rootTime / 100,
            ];
        }

        return [
            'delta' => $call ? $this->normalCdf($d1) : $this->normalCdf($d1) - 1,
            'gamma' => $density / ($underlying * $volatilityTime),
            'theta' => (
                -$underlying * $density * $volatility / (2 * $rootTime)
                + ($call ? -1 : 1) * self::RISK_FREE_RATE * $strike * $discount * $this->normalCdf($call ? $d2 : -$d2)
            ) / 365,
            'vega' => $underlying * $density * $rootTime / 100,
        ];
    }

    private function normalCdf(float $value): float
    {
        $absolute = abs($value);
        $t = 1 / (1 + 0.2316419 * $absolute);
        $density = 0.3989422804014327 * exp(-0.5 * $absolute ** 2);
        $tail = $density * $t * (
            0.3193815
            + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274)))
        );

        return $value >= 0 ? 1 - $tail : $tail;
    }
}
