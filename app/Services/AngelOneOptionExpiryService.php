<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

final class AngelOneOptionExpiryService
{
    private const SCRIPT_MASTER_URL = 'https://margincalculator.angelone.in/OpenAPI_File/files/OpenAPIScripMaster.json';

    /**
     * @var array<string, array{exchange: string, name: string}>
     */
    private const UNDERLYINGS = [
        'NIFTY' => ['exchange' => 'NFO', 'name' => 'NIFTY'],
        'BANKNIFTY' => ['exchange' => 'NFO', 'name' => 'BANKNIFTY'],
        'SENSEX' => ['exchange' => 'BFO', 'name' => 'SENSEX'],
        'CRUDEOIL' => ['exchange' => 'MCX', 'name' => 'CRUDEOIL'],
    ];

    /**
     * @return array{
     *     options: list<array{token: string, symbol: string, strike: float, option_type: 'CE'|'PE'}>,
     *     reference: array{exchange: string, token: string}
     * }
     *
     * @throws ConnectionException
     */
    public function getQuotedOptionContracts(string $underlying, string $expiry): array
    {
        if (! isset(self::UNDERLYINGS[$underlying])) {
            throw new RuntimeException('Quoted option Greeks are not available for this underlying.');
        }

        $catalog = $this->catalog();
        $isoExpiry = (new DateTimeImmutable($expiry))->format('Y-m-d');
        $options = array_values(array_filter(
            $catalog['options'][$underlying] ?? [],
            static fn (array $option): bool => $option['date'] === $isoExpiry
        ));

        if ($options === []) {
            throw new RuntimeException("Angel One has no option contracts for {$underlying} on {$expiry}.");
        }

        if ($underlying !== 'CRUDEOIL') {
            $reference = $catalog['index_tokens'][$underlying] ?? null;
            if (! is_array($reference)) {
                throw new RuntimeException("Angel One did not list a {$underlying} index quote token.");
            }

            return [
                'options' => array_map(
                    static fn (array $option): array => array_diff_key($option, ['date' => true]),
                    $options
                ),
                'reference' => [
                    'exchange' => $underlying === 'SENSEX' ? 'BSE' : 'NSE',
                    'token' => $reference['token'],
                ],
            ];
        }

        $expiryTimestamp = (new DateTimeImmutable($isoExpiry))->getTimestamp();
        $futures = array_values(array_filter(
            $catalog['crudeoil_futures'] ?? [],
            static fn (array $future): bool => $future['timestamp'] >= $expiryTimestamp
        ));
        usort($futures, static fn (array $left, array $right): int => $left['timestamp'] <=> $right['timestamp']);
        $future = $futures[0] ?? null;

        if (! is_array($future)) {
            throw new RuntimeException("Angel One did not list a CRUDEOIL future covering the {$expiry} option expiry.");
        }

        return [
            'options' => array_map(
                static fn (array $option): array => array_diff_key($option, ['date' => true]),
                $options
            ),
            'reference' => ['exchange' => 'MCX', 'token' => $future['token']],
        ];
    }

    /**
     * @return list<array{date: string, label: string}>
     *
     * @throws ConnectionException
     */
    public function getExpiries(string $underlying): array
    {
        if (! isset(self::UNDERLYINGS[$underlying])) {
            throw new RuntimeException('Choose a supported option underlying.');
        }

        $allExpiries = $this->catalog()['expiries'];

        $expiries = $allExpiries[$underlying] ?? [];
        if ($expiries === []) {
            throw new RuntimeException("Angel One currently lists no option expiries for {$underlying}.");
        }

        return $expiries;
    }

    /**
     * @return array<string, list<array{date: string, label: string}>>
     *
     * @throws ConnectionException
     */
    private function catalog(): array
    {
        return Cache::remember(
            'angel_one.option_catalog.v3',
            now()->addHours(12),
            fn (): array => $this->loadCatalog()
        );
    }

    /**
     * @return array{
     *     expiries: array<string, list<array{date: string, label: string}>>,
     *     options: array<string, list<array{date: string, token: string, symbol: string, strike: float, option_type: 'CE'|'PE'}>>,
     *     crudeoil_futures: list<array{timestamp: int, token: string}>,
     *     index_tokens: array<string, array{token: string}>
     * }
     *
     * @throws ConnectionException
     */
    private function loadCatalog(): array
    {
        $response = Http::acceptJson()
            ->connectTimeout(10)
            ->timeout(60)
            ->get(self::SCRIPT_MASTER_URL);

        if (! $response->successful()) {
            throw new RuntimeException('Could not load Angel One contract expiries. Try again later.');
        }

        $master = $response->body();
        if ((ltrim(substr($master, 0, 16))[0] ?? '') !== '[') {
            throw new RuntimeException('Angel One returned an invalid contract master.');
        }

        $datesByUnderlying = array_fill_keys(array_keys(self::UNDERLYINGS), []);
        $optionsByUnderlying = array_fill_keys(array_keys(self::UNDERLYINGS), []);
        $crudeOilFutures = [];
        $indexTokens = [];
        $today = now()->startOfDay();

        foreach ($this->instrumentRows($master) as $instrument) {
            $exchange = strtoupper((string) ($instrument['exch_seg'] ?? ''));
            $name = strtoupper((string) ($instrument['name'] ?? ''));
            $instrumentType = strtoupper((string) ($instrument['instrumenttype'] ?? ''));
            $expiry = strtoupper((string) ($instrument['expiry'] ?? ''));

            if (
                $instrumentType === 'AMXIDX'
                && in_array($exchange, ['NSE', 'BSE'], true)
                && in_array($name, ['NIFTY', 'BANKNIFTY', 'SENSEX'], true)
            ) {
                $token = (string) ($instrument['token'] ?? '');
                if ($token !== '') {
                    $indexTokens[$name] = ['token' => $token];
                }

                continue;
            }

            if ($instrumentType === 'FUTCOM' && $exchange === 'MCX' && $name === 'CRUDEOIL') {
                $futureDate = DateTimeImmutable::createFromFormat('!dMY', $expiry);
                $token = (string) ($instrument['token'] ?? '');
                if ($futureDate && $token !== '') {
                    $crudeOilFutures[] = [
                        'timestamp' => $futureDate->getTimestamp(),
                        'token' => $token,
                    ];
                }

                continue;
            }

            if (! str_starts_with($instrumentType, 'OPT') || ! preg_match('/^\d{2}[A-Z]{3}\d{4}$/', $expiry)) {
                continue;
            }

            $date = DateTimeImmutable::createFromFormat('!dMY', $expiry);
            if (
                ! $date
                || strtoupper($date->format('dMY')) !== $expiry
                || $date->format('Y-m-d') < $today->format('Y-m-d')
            ) {
                continue;
            }

            foreach (self::UNDERLYINGS as $symbol => $details) {
                if ($exchange !== $details['exchange'] || $name !== $details['name']) {
                    continue;
                }

                $isoDate = $date->format('Y-m-d');
                $datesByUnderlying[$symbol][$isoDate] = [
                    'date' => $isoDate,
                    'label' => $date->format('d M Y'),
                ];

                if (isset($optionsByUnderlying[$symbol])) {
                    $tradingSymbol = strtoupper((string) ($instrument['symbol'] ?? ''));
                    $optionType = str_ends_with($tradingSymbol, 'CE')
                        ? 'CE'
                        : (str_ends_with($tradingSymbol, 'PE') ? 'PE' : null);
                    $strikeValue = $instrument['strike'] ?? null;
                    $token = (string) ($instrument['token'] ?? '');

                    if ($optionType !== null && is_numeric($strikeValue) && (float) $strikeValue > 0 && $token !== '') {
                        $optionsByUnderlying[$symbol][] = [
                            'date' => $isoDate,
                            'token' => $token,
                            'symbol' => $tradingSymbol,
                            'strike' => (float) $strikeValue / 100,
                            'option_type' => $optionType,
                        ];
                    }
                }
            }
        }

        foreach ($datesByUnderlying as $underlying => $expiries) {
            ksort($expiries);
            $datesByUnderlying[$underlying] = array_values($expiries);
        }

        return [
            'expiries' => $datesByUnderlying,
            'options' => $optionsByUnderlying,
            'crudeoil_futures' => $crudeOilFutures,
            'index_tokens' => $indexTokens,
        ];
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function instrumentRows(string $master): Generator
    {
        $length = strlen($master);
        $start = null;
        $depth = 0;
        $insideString = false;
        $escaped = false;

        for ($index = 0; $index < $length; $index++) {
            $character = $master[$index];

            if ($depth === 0) {
                if ($character === '{') {
                    $start = $index;
                    $depth = 1;
                }

                continue;
            }

            if ($insideString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $insideString = false;
                }

                continue;
            }

            if ($character === '"') {
                $insideString = true;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth--;

                if ($depth === 0 && $start !== null) {
                    try {
                        $instrument = json_decode(
                            substr($master, $start, $index - $start + 1),
                            true,
                            flags: JSON_THROW_ON_ERROR
                        );
                    } catch (JsonException $exception) {
                        throw new RuntimeException('Angel One returned an invalid contract master record.', previous: $exception);
                    }

                    if (! is_array($instrument)) {
                        throw new RuntimeException('Angel One returned an invalid contract master record.');
                    }

                    yield $instrument;
                    $start = null;
                }
            }
        }

        if ($depth !== 0 || $insideString) {
            throw new RuntimeException('Angel One returned an incomplete contract master.');
        }
    }
}
