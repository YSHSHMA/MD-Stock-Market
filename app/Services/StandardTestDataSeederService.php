<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\TestDataSeederServiceInterface;
use App\Models\DailyMetric;
use App\Models\Setting;
use App\Models\Ticker;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use App\Contracts\EloquentTransactionServiceInterface;
use App\Enums\TransactionType;

final readonly class StandardTestDataSeederService implements TestDataSeederServiceInterface
{
    public function __construct(
        private EloquentTransactionServiceInterface $transactionService
    ) {}

    /**
     * {@inheritDoc}
     */
    public function truncate(): void
    {
        Schema::disableForeignKeyConstraints();

        $tables = [
            'transactions',
            'daily_metrics',
            'tickers',
            'settings',
            'users',
            'personal_access_tokens',
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        Schema::enableForeignKeyConstraints();
    }

    /**
     * {@inheritDoc}
     */
    public function seedUsers(): Collection
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        $users = User::factory(5)->create();

        return $users->concat([$admin]);
    }

    /**
     * {@inheritDoc}
     */
    public function seedTickers(): Collection
    {
        return Ticker::factory(10)->create();
    }

    /**
     * {@inheritDoc}
     */
    public function seedDailyMetrics(Collection $tickers, ?callable $onProgress = null): void
    {
        $tickers->each(function (Ticker $ticker) use ($onProgress) {
            $startDate = now()->subDays(30);
            $currentPrice = fake()->randomFloat(2, 20, 500); // Initial random price

            $metrics = [];

            for ($i = 0; $i <= 30; $i++) {
                $date = $startDate->copy()->addDays($i);

                // Random Walk: Price changes by -5% to +5% each day
                $changePercent = fake()->randomFloat(4, -5, 5);
                $change = $currentPrice * ($changePercent / 100);
                $newPrice = max(1, $currentPrice + $change); // Ensure price doesn't go negative

                $metrics[] = [
                    'id' => (string) Str::uuid(),
                    'ticker_id' => $ticker->id,
                    'date' => $date->format('Y-m-d'),
                    'price' => round($newPrice, 2),
                    'change' => round($change, 2),
                    'change_percent' => round($changePercent, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $currentPrice = $newPrice;
            }

            // Insert in chunks to be efficient but keep memory low
            DailyMetric::insert($metrics);

            if ($onProgress) {
                $onProgress();
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function seedTransactions(Collection $users, Collection $tickers, ?callable $onProgress = null): void
    {
        $users->each(function (User $user) use ($tickers, $onProgress) {
            // 💡 SWE Logic: Generate realistic buy/sell sequences.
            // A user can't sell what they don't own.
            $tickers->shuffle()->take(5)->each(function (Ticker $ticker) use ($user) {
                // Initial Buy
                $this->transactionService->createTransaction(
                    userId: $user->id,
                    tickerId: $ticker->id,
                    type: TransactionType::BUY->value,
                    quantity: fake()->randomFloat(2, 5, 15),
                    price: fake()->randomFloat(2, 50, 250),
                    date: now()->subDays(fake()->numberBetween(20, 30))->toIso8601String()
                );

                // Possible Sell
                if (fake()->boolean(40)) {
                    $currentQty = (float) Transaction::where('user_id', $user->id)
                        ->where('ticker_id', $ticker->id)
                        ->where('type', TransactionType::BUY->value)
                        ->sum('quantity');

                    $this->transactionService->createTransaction(
                        userId: $user->id,
                        tickerId: $ticker->id,
                        type: TransactionType::SELL->value,
                        quantity: $currentQty * fake()->randomFloat(2, 0.2, 0.5),
                        price: fake()->randomFloat(2, 50, 300),
                        date: now()->subDays(fake()->numberBetween(2, 10))->toIso8601String()
                    );
                }

                // Periodic topping up
                if (fake()->boolean(30)) {
                    $this->transactionService->createTransaction(
                        userId: $user->id,
                        tickerId: $ticker->id,
                        type: TransactionType::BUY->value,
                        quantity: fake()->randomFloat(2, 2, 8),
                        price: fake()->randomFloat(2, 50, 250),
                        date: now()->subDays(fake()->numberBetween(1, 10))->toIso8601String()
                    );
                }
            });

            if ($onProgress) {
                $onProgress();
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function seedSettings(): void
    {
        Setting::factory()->createMany([
            ['key' => 'app_name', 'value' => 'Stock Market', 'description' => 'The name of the application'],
            ['key' => 'maintenance_mode', 'value' => 'false', 'description' => 'Whether the app is in maintenance mode'],
            ['key' => 'default_currency', 'value' => 'USD', 'description' => 'The default currency for calculations'],
        ]);
    }
}
