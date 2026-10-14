<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class AngelOneLiveOptionGreeksService
{
    public function __construct(
        private readonly AngelOneOptionGreeksService $nativeGreeks,
        private readonly AngelOneQuotedOptionGreeksService $quotedGreeks,
        private readonly AngelOneOptionExpiryService $expiryService,
    ) {}

    /**
     * Fetch option Greeks from Angel One, falling back to live quote-based
     * calculations when the native Greeks endpoint fails.
     *
     * @param array{api_key: string, jwt_token: string} $credentials
     * @return array{
     *     source: 'angel_one'|'calculated_from_live_quotes',
     *     data: list<array<string, int|float|string|null>>
     * }
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    public function get(
        string $underlying,
        string $expiry,
        array $credentials,
        ?string $userId = null
    ): array {
        $underlying = strtoupper(trim($underlying));
        $expiry = trim($expiry);

        if (
            empty($credentials['api_key']) ||
            empty($credentials['jwt_token'])
        ) {
            throw new RuntimeException(
                'Angel One API key or JWT token is missing.'
            );
        }

        // Validate and normalize the expiry date.
        try {
            $expiryDate = new DateTimeImmutable($expiry);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Invalid option expiry date: ' . $expiry,
                0,
                $exception
            );
        }

        $expiry = $expiryDate->format('Y-m-d');

        // These underlyings use quote-based Greeks directly.
        if (in_array($underlying, ['SENSEX', 'CRUDEOIL'], true)) {
            return $this->fromQuotes(
                $underlying,
                $expiry,
                $credentials,
                $userId
            );
        }

        try {
            // Fetch native Greeks from Angel One.
            $rows = $this->nativeGreeks->getOptionGreeks(
                $underlying,
                strtoupper($expiryDate->format('dMY')),
                $credentials['jwt_token'],
                $credentials['api_key']
            );

            if (!is_array($rows) || $rows === []) {
                throw new RuntimeException(
                    'Angel One returned no native option Greeks.'
                );
            }

            // Get contracts needed to attach live market data.
            $contracts = $this->expiryService->getQuotedOptionContracts(
                $underlying,
                $expiry
            );

            if (!is_array($contracts) || $contracts === []) {
                throw new RuntimeException(
                    'No option contracts found for the requested expiry.'
                );
            }

            // Enrich native Greeks with LTP, OI and trade volume.
            $rows = $this->quotedGreeks->addMarketData(
                $rows,
                $underlying,
                $contracts,
                $credentials['jwt_token'],
                $credentials['api_key']
            );

            return [
                'source' => 'angel_one',
                'data' => $rows,
            ];
        } catch (ConnectionException | RuntimeException $exception) {
            Log::warning(
                'Angel One native Greeks failed; falling back to live quotes.',
                [
                    'user_id' => $userId,
                    'underlying' => $underlying,
                    'expiry' => $expiry,
                    'exception' => $exception->getMessage(),
                ]
            );

            return $this->fromQuotes(
                $underlying,
                $expiry,
                $credentials,
                $userId
            );
        }
    }

    /**
     * Calculate option Greeks using live market quotes.
     *
     * @param array{api_key: string, jwt_token: string} $credentials
     * @return array{
     *     source: 'calculated_from_live_quotes',
     *     data: list<array<string, int|float|string|null>>
     * }
     *
     * @throws ConnectionException
     * @throws RuntimeException
     */
    private function fromQuotes(
        string $underlying,
        string $expiry,
        array $credentials,
        ?string $userId = null
    ): array {
        try {
            $contracts = $this->expiryService->getQuotedOptionContracts(
                $underlying,
                $expiry
            );

            if (!is_array($contracts) || $contracts === []) {
                throw new RuntimeException(
                    "No option contracts found for {$underlying} expiry {$expiry}."
                );
            }

            $data = $this->quotedGreeks->getOptionGreeks(
                $underlying,
                $expiry,
                $contracts,
                $credentials['jwt_token'],
                $credentials['api_key']
            );

            if (!is_array($data)) {
                throw new RuntimeException(
                    'Quote-based Greeks service returned an invalid response.'
                );
            }

            return [
                'source' => 'calculated_from_live_quotes',
                'data' => array_values($data),
            ];
        } catch (ConnectionException | RuntimeException $exception) {
            Log::error('Unable to fetch option Greeks from live quotes.', [
                'user_id' => $userId,
                'underlying' => $underlying,
                'expiry' => $expiry,
                'exception' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}