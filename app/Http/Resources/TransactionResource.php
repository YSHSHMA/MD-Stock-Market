<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
final class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->ticker->symbol,
            'type' => new EnumResource($this->type),
            'quantity' => (float) $this->quantity,
            'price' => (float) $this->price,
            'total' => (float) $this->quantity * (float) $this->price,
            'date' => $this->transaction_date->toDateTimeString(),
        ];
    }
}
