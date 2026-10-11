<?php

namespace Database\Factories;

use App\Models\DailyMetric;
use App\Models\Ticker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<DailyMetric>
 */
class DailyMetricFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticker_id' => Ticker::factory(),
            'date' => $this->faker->date(),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'change' => $this->faker->randomFloat(2, -10, 10),
            'change_percent' => $this->faker->randomFloat(2, -5, 5),
        ];
    }
}
