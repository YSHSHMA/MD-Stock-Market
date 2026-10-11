<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

use App\Contracts\StandardMarketHoursServiceInterface;

final readonly class StandardMarketHoursService implements StandardMarketHoursServiceInterface
{
  /**
   * Check if market is currently open (9AM - 6PM Italian time, weekdays only)
   */
  public function isMarketOpen(?Carbon $time = null): bool
  {
    $time = $time ?? Carbon::now(config('settings.market.timezone', 'Europe/Rome'));

    // Weekend check
    if ($time->isWeekend()) {
      return false;
    }

    // Market hours from config
    [$openHour, $openMin] = explode(':', config('settings.market.open', '09:00'));
    [$closeHour, $closeMin] = explode(':', config('settings.market.close', '18:00'));

    $marketOpen = $time->copy()->setTime((int) $openHour, (int) $openMin);
    $marketClose = $time->copy()->setTime((int) $closeHour, (int) $closeMin);

    return $time->between($marketOpen, $marketClose);
  }

  /**
   * Get next market open time
   */
  public function getNextMarketOpen(?Carbon $time = null): Carbon
  {
    $time = $time ?? Carbon::now(config('settings.market.timezone', 'Europe/Rome'));
    [$openHour, $openMin] = explode(':', config('settings.market.open', '09:00'));
    [$closeHour, $closeMin] = explode(':', config('settings.market.close', '18:00'));

    // If weekend, move to Monday
    if ($time->isWeekend()) {
      return $time->next(Carbon::MONDAY)->setTime((int) $openHour, (int) $openMin);
    }

    // If after hours, next day
    $marketClose = $time->copy()->setTime((int) $closeHour, (int) $closeMin);
    if ($time->greaterThanOrEqualTo($marketClose)) {
      if ($time->isFriday()) {
        return $time->next(Carbon::MONDAY)->setTime((int) $openHour, (int) $openMin);
      }
      return $time->addDay()->setTime((int) $openHour, (int) $openMin);
    }

    // If before hours, today
    $marketOpen = $time->copy()->setTime((int) $openHour, (int) $openMin);
    if ($time->lessThan($marketOpen)) {
      return $marketOpen;
    }

    // If currently open, next opening is definitely tomorrow (or Monday)
    if ($this->isMarketOpen($time)) {
      if ($time->isFriday()) {
        return $time->next(Carbon::MONDAY)->setTime((int) $openHour, (int) $openMin);
      }
      return $time->copy()->addDay()->setTime((int) $openHour, (int) $openMin);
    }

    return $time;
  }
}
