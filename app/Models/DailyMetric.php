<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DailyMetricFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(DailyMetricFactory::class)]
class DailyMetric extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ticker_id',
        'date',
        'price',
        'change',
        'change_percent',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'ticker_id' => 'string',
            'date' => 'date',
            'price' => 'decimal:8',
            'change' => 'decimal:8',
            'change_percent' => 'decimal:8',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function ticker(): BelongsTo
    {
        return $this->belongsTo(Ticker::class);
    }
}
