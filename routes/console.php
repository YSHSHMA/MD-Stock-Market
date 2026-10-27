<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Live Market Data Broadcast
|--------------------------------------------------------------------------
|
| Fetch fresh option-chain data from Angel One every 5 seconds during
| market hours and broadcast changes to subscribed users over WebSockets.
| The command compares a payload hash before broadcasting, so no-ops are
| skipped and only genuine data changes reach the frontend.
|
*/

Schedule::command('option-chain:broadcast-live')
    ->everyFiveSeconds()
    ->between(config('app.market_open', '09:15'), config('app.market_close', '15:30'))
    ->timezone('Asia/Kolkata')
    ->weekdays()
    ->withoutOverlapping()
    ->runInBackground();
