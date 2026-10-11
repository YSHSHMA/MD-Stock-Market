<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTOs\PortfolioSummary;
use App\Models\User;
use Carbon\Carbon;

interface StandardPortfolioCalculatorInterface
{
  public function calculate(User $user, ?Carbon $date = null): ?PortfolioSummary;
}
