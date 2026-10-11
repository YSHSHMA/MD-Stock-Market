<?php

namespace Database\Factories;

use App\Models\Ticker;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ticker_id' => Ticker::factory(),
            'type' => $this->faker->randomElement(['buy', 'sell']),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'transaction_date' => $this->faker->dateTime(),
        ];
    }
}
