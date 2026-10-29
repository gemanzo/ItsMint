<?php

namespace Database\Factories;

use App\Models\Blueprint;
use App\Models\Expansion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Note: creating a Blueprint fires BlueprintObserver (Elasticsearch indexing and
 * OCR match string generation). Use createQuietly() in tests that don't need it.
 *
 * @extends Factory<Blueprint>
 */
class BlueprintFactory extends Factory
{
    /**
     * CardTrader category id of One Piece single cards.
     */
    private const ONE_PIECE_SINGLE_CARD_CATEGORY_ID = 192;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expansion_id' => Expansion::factory(),
            'ct_id' => fake()->unique()->numberBetween(1, 10_000_000),
            'name' => fake()->name(),
            'ct_category_id' => self::ONE_PIECE_SINGLE_CARD_CATEGORY_ID,
        ];
    }
}
