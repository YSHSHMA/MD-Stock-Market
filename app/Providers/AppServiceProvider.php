<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;

use App\Contracts\AlphaVantageServiceInterface;
use App\Contracts\CloudStorageServiceInterface;
use App\Services\AlphaVantageService;
use App\Contracts\EloquentTransactionServiceInterface;
use App\Services\EloquentTransactionService;
use App\Contracts\StandardReportServiceInterface;
use App\Services\StandardReportService;
use App\Contracts\EloquentTickerServiceInterface;
use App\Services\EloquentTickerService;
use App\Contracts\EloquentMetricServiceInterface;
use App\Services\EloquentMetricService;
use App\Contracts\EloquentUserServiceInterface;
use App\Services\EloquentUserService;
use App\Contracts\HttpTelegramServiceInterface;
use App\Services\HttpTelegramService;
use App\Contracts\StandardPortfolioCalculatorInterface;
use App\Services\StandardPortfolioCalculator;
use App\Contracts\StandardMarketHoursServiceInterface;
use App\Services\StandardMarketHoursService;
use App\Contracts\DefaultStockServiceInterface;
use App\Services\DefaultStockService;
use App\Contracts\SettingServiceInterface;
use App\Services\EloquentSettingService;
use App\Contracts\TestDataSeederServiceInterface;
use App\Services\CloudStorageService;
use App\Services\StandardTestDataSeederService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AlphaVantageServiceInterface::class, AlphaVantageService::class);
        $this->app->bind(EloquentTransactionServiceInterface::class, EloquentTransactionService::class);
        $this->app->bind(StandardReportServiceInterface::class, StandardReportService::class);
        $this->app->bind(EloquentTickerServiceInterface::class, EloquentTickerService::class);
        $this->app->bind(EloquentMetricServiceInterface::class, EloquentMetricService::class);
        $this->app->bind(EloquentUserServiceInterface::class, EloquentUserService::class);
        $this->app->bind(HttpTelegramServiceInterface::class, HttpTelegramService::class);
        $this->app->bind(StandardPortfolioCalculatorInterface::class, StandardPortfolioCalculator::class);
        $this->app->bind(StandardMarketHoursServiceInterface::class, StandardMarketHoursService::class);
        $this->app->bind(DefaultStockServiceInterface::class, DefaultStockService::class);
        $this->app->bind(SettingServiceInterface::class, EloquentSettingService::class);
        $this->app->bind(TestDataSeederServiceInterface::class, StandardTestDataSeederService::class);
        $this->app->bind(CloudStorageServiceInterface::class, CloudStorageService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('alphavantage', function (object $job) {
            return Limit::perMinute(config('settings.alphavantage.rate_limit_per_minute', 5));
        });
    }
}
