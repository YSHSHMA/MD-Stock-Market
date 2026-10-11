<?php

namespace App\Console\Commands;


use App\Contracts\StandardMarketHoursServiceInterface;
use App\Contracts\DefaultStockServiceInterface;
use Illuminate\Console\Command;

class FetchStockData extends Command
{
    protected $signature = 'stocks:fetch';
    protected $description = 'Fetch updated stock data for all tickers';

    public function handle(DefaultStockServiceInterface $stockService, StandardMarketHoursServiceInterface $marketHours)
    {
        // Check if market is open
        if (!$marketHours->isMarketOpen()) {
            $nextOpen = $marketHours->getNextMarketOpen();
            $this->info("Market is closed. Next open: {$nextOpen->format('Y-m-d H:i')}");
            return Command::SUCCESS;
        }

        $this->info('Starting stock price update process...');

        $stockService->updateAllTickers(function (string $symbol) {
            $this->info("Queued update for {$symbol}...");
        });

        $this->info('All ticker updates have been queued with a 12s stagger.');
        return 0;
    }
}
