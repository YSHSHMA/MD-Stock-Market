<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\TestDataSeederServiceInterface;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class SeedTestDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:seed-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with consistent test data with detailed progress reporting';

    /**
     * Execute the console command.
     */
    public function handle(TestDataSeederServiceInterface $seederService): int
    {
        $this->info('🚀 Starting test data seeding...');

        if (!$this->confirm('This will truncate ALL database data (users, tickers, transactions, metrics, settings, tokens). Do you wish to continue?', true)) {
            $this->warn('Seeding cancelled.');
            return self::FAILURE;
        }

        try {
            // Step 1: Truncation
            $this->components->task('Truncating existing tables', function () use ($seederService) {
                $seederService->truncate();
            });

            // Step 2: Users
            $users = new Collection();
            $this->components->task('Seeding users (Admin + 5 samples)', function () use ($seederService, &$users) {
                $users = $seederService->seedUsers();
            });

            // Step 3: Tickers
            $tickers = new Collection();
            $this->components->task('Seeding 10 tickers', function () use ($seederService, &$tickers) {
                $tickers = $seederService->seedTickers();
            });

            // Step 4: Daily Metrics (with progress bar)
            $this->info('Seeding daily metrics (30 days per ticker)...');
            $this->output->progressStart($tickers->count());
            $seederService->seedDailyMetrics($tickers, function () {
                $this->output->progressAdvance();
            });
            $this->output->progressFinish();

            // Step 5: Transactions (with progress bar)
            $this->info('Seeding transactions (15 per user)...');
            $this->output->progressStart($users->count());
            $seederService->seedTransactions($users, $tickers, function () {
                $this->output->progressAdvance();
            });
            $this->output->progressFinish();

            // Step 6: Settings
            $this->components->task('Seeding default application settings', function () use ($seederService) {
                $seederService->seedSettings();
            });

            $this->newLine();
            $this->info('✅ Test data seeded successfully.');

            return self::SUCCESS;
        } catch (Exception $e) {
            $this->newLine();
            $this->error('❌ An error occurred during seeding: ' . $e->getMessage());
            Log::error('Test data seeding failed', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }
}
