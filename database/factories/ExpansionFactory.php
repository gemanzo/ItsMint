<?php

namespace Database\Factories;

use App\Models\Expansion;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expansion>
 */
class ExpansionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'ct_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'code' => 'op'.fake()->unique()->numberBetween(10, 99),
            'name' => fake()->words(3, true),
        ];
    }
}
