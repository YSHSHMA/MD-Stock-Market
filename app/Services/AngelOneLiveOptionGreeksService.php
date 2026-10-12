<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class AngelOneLiveOptionGreeksService
{
    public function __construct(
        private readonly AngelOneOptionGreeksService $nativeGreeks,
        private readonly AngelOneQuotedOptionGreeksService $quotedGreeks,
        private readonly AngelOneOptionExpiryService $expiryService,
    ) {}

    /**
     * @param  array{api_key: string, jwt_token: string}  $credentials
     * @return array{
     *     source: 'angel_one'|'calculated_from_live_quotes',
     *     data: list<array{
     *         strike: float,
     *         option_type: 'CE'|'PE',
     *         delta: float,
     *         gamma: float,
     *         theta: float,
     *         vega: float,
     *         implied_volatility: float,
     *         trade_volume: int
     *     }>
     * }
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function get(string $underlying, string $expiry, array $credentials, ?string $userId = null): array
    {
        if (in_array($underlying, ['SENSEX', 'CRUDEOIL'], true)) {
            return $this->fromQuotes($underlying, $expiry, $credentials);
        }

        try {
            $rows = $this->nativeGreeks->getOptionGreeks(
                $underlying,
                strtoupper((new DateTimeImmutable($expiry))->format('dMY')),
                $credentials['jwt_token'],
                $credentials['api_key']
            );

            return ['source' => 'angel_one', 'data' => $rows];
        } catch (ConnectionException|RuntimeException $exception) {
            Log::warning('Angel One native Greeks failed; trying quote-based Greeks.', [
                'user_id' => $userId,
                'underlying' => $underlying,
                'expiry' => $expiry,
                'exception' => $exception->getMessage(),
            ]);

            return $this->fromQuotes($underlying, $expiry, $credentials);
        }
    }

    /**
     * @param  array{api_key: string, jwt_token: string}  $credentials
     * @return array{source: 'calculated_from_live_quotes', data: list<array<string, int|float|string>>}
     *
     * @throws ConnectionException
     */
    private function fromQuotes(string $underlying, string $expiry, array $credentials): array
    {
        $contracts = $this->expiryService->getQuotedOptionContracts($underlying, $expiry);

        return [
            'source' => 'calculated_from_live_quotes',
            'data' => $this->quotedGreeks->getOptionGreeks(
                $underlying,
                $expiry,
                $contracts,
                $credentials['jwt_token'],
                $credentials['api_key']
            ),
        ];
    }
}
