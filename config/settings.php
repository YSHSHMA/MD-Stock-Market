<?php

return [
  'cron_secret' => env('CRON_SECRET'),

  /*
    |--------------------------------------------------------------------------
    | Trading & Market Settings
    |--------------------------------------------------------------------------
    */
  'market' => [
    'open' => env('MARKET_OPEN', '09:00'),
    'close' => env('MARKET_CLOSE', '18:00'),
    'timezone' => 'Europe/Rome',
  ],

  /*
    |--------------------------------------------------------------------------
    | AlphaVantage API Settings
    |--------------------------------------------------------------------------
    */
  'alphavantage' => [
    // Free tier allows 5 requests per minute
    'rate_limit_per_minute' => 5,
    // Delay between staggered updates (seconds)
    'stagger_delay' => 12,
    // Request timeouts
    'timeout' => 15,
    'connect_timeout' => 5,
  ],

  /*
    |--------------------------------------------------------------------------
    | Telegram Settings
    |--------------------------------------------------------------------------
    */
  'telegram' => [
    // Max characters per message chunk (Telegram limit is 4096)
    'message_limit' => 4000,
  ],

  /*
    |--------------------------------------------------------------------------
    | Job & Queue Settings
    |--------------------------------------------------------------------------
    */
  'jobs' => [
    'stock_update' => [
      'tries' => 3,
      'backoff' => [60, 120, 240],
    ],
    'daily_report' => [
      'tries' => 2,
      'backoff_seconds' => 300,
    ],
  ],

  /*
    |--------------------------------------------------------------------------
    | Currency API Settings
    |--------------------------------------------------------------------------
    */
  'currency' => [
    'api_url' => env('CURRENCY_API_URL', 'https://api.frankfurter.app/latest'),
  ],

  /*
    |--------------------------------------------------------------------------
    | Report Settings
    |--------------------------------------------------------------------------
    */
  'report_folder' => env('REPORT_STORAGE_FOLDER', 'reports'),
];
