<?php

use App\Http\Middleware\CronAuthMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        channels: __DIR__.'/../routes/channels.php',
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->validateCsrfTokens(except: [
            'api/telegram/webhook/*',
        ]);

        // Register cron authentication middleware
        $middleware->alias([
            'cron.auth' => CronAuthMiddleware::class,
        ]);
    })
    ->withSchedule(function ($schedule): void {
        $schedule->command('option-chain:broadcast-live')
            ->everyTenSeconds()
            ->withoutOverlapping(1);

        $schedule->command('stocks:fetch')
            ->everyThirtyMinutes()
            ->between('09:00', '18:00')
            ->timezone('Europe/Rome');

        $schedule->command('report:daily')
            ->dailyAt('19:00')
            ->timezone('Europe/Rome');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\App\Exceptions\Domain\CurrencyNotSetException $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'error' => 'CURRENCY_NOT_SET',
                ], 400);
            }
        });
    })->create();
