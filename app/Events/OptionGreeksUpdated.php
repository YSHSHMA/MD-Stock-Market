<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

final class OptionGreeksUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * @param list<array{
     *     strike: float,
     *     option_type: 'CE'|'PE',
     *     delta: float,
     *     gamma: float,
     *     theta: float,
     *     vega: float,
     *     implied_volatility: float,
     *     ltp: float,
     *     open_interest: ?int,
     *     trade_volume: int
     * }> $data
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $underlying,
        public readonly string $expiry,
        public readonly string $greeksSource,
        public readonly array $data,
        public readonly string $updatedAt
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('users.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'option.greeks.updated';
    }

    /**
     * @return array{
     *     underlying: string,
     *     expiry: string,
     *     expiry_date: string,
     *     greeks_source: string,
     *     data: list<array<string, int|float|string|null>>,
     *     updated_at: string
     * }
     */
    public function broadcastWith(): array
    {
        return [
            'underlying' => $this->underlying,
            'expiry' => strtoupper(Carbon::parse($this->expiry)->format('dMY')),
            'expiry_date' => $this->expiry,
            'greeks_source' => $this->greeksSource,
            'data' => $this->data,
            'updated_at' => $this->updatedAt,
        ];
    }
}
