<?php

declare(strict_types=1);

namespace App\Contracts;

use Carbon\Carbon;

interface StandardMarketHoursServiceInterface
{
  public function isMarketOpen(?Carbon $time = null): bool;

  public function getNextMarketOpen(?Carbon $time = null): Carbon;
}
