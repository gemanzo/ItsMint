<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ct_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'name' => 'onepiece',
            'display_name' => 'One Piece',
        ];
    }
}
