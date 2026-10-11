<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\TransactionType;

#[UseFactory(TransactionFactory::class)]
class Transaction extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'ticker_id',
        'type',
        'quantity',
        'price',
        'transaction_date',
    ];


    protected function casts(): array
    {
        return [
            'id' => 'string',
            'user_id' => 'string',
            'ticker_id' => 'string',
            'type' => TransactionType::class,
            'quantity' => 'decimal:8',
            'price' => 'decimal:8',
            'transaction_date' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ticker(): BelongsTo
    {
        return $this->belongsTo(Ticker::class);
    }
}
