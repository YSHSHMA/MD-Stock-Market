<?php

namespace Database\Factories;

use App\Models\Ticker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Ticker>
 */
class TickerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'symbol' => strtoupper($this->faker->unique()->lexify('????')),
            'name' => $this->faker->company(),
        ];
    }
}
