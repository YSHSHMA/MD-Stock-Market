<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Support\Collection;

interface TestDataSeederServiceInterface
{
    /**
     * Truncate all relevant tables.
     *
     * @return void
     */
    public function truncate(): void;

    /**
     * Seed users and return the created users.
     *
     * @return Collection
     */
    public function seedUsers(): Collection;

    /**
     * Seed tickers and return the created tickers.
     *
     * @return Collection
     */
    public function seedTickers(): Collection;

    /**
     * Seed daily metrics for given tickers.
     *
     * @param Collection $tickers
     * @param callable|null $onProgress
     * @return void
     */
    public function seedDailyMetrics(Collection $tickers, ?callable $onProgress = null): void;

    /**
     * Seed transactions for given users and tickers.
     *
     * @param Collection $users
     * @param Collection $tickers
     * @param callable|null $onProgress
     * @return void
     */
    public function seedTransactions(Collection $users, Collection $tickers, ?callable $onProgress = null): void;

    /**
     * Seed default settings.
     *
     * @return void
     */
    public function seedSettings(): void;
}
