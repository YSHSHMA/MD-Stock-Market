<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TickerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(TickerFactory::class)]
class Ticker extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['symbol', 'name'];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'symbol' => 'string',
            'name' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function dailyMetrics(): HasMany
    {
        return $this->hasMany(DailyMetric::class);
    }
}
