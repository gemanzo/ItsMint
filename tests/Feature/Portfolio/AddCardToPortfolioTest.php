<?php

use App\Models\Blueprint;
use App\Models\Portfolio;
use App\Models\User;

/**
 * Request payload as sent by the frontend add-card forms.
 */
function addCardPayload(Blueprint $blueprint, array $overrides = []): array
{
    return array_merge([
        'blueprint_id' => $blueprint->id,
        'condition' => 'Near Mint',
        'language' => 'en',
        'quantity' => 2,
        'purchase_price_cents' => 1250,
        'is_foil' => false,
    ], $overrides);
}

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->portfolio = Portfolio::create(['user_id' => $this->owner->id, 'name' => 'Main']);
    $this->blueprint = Blueprint::factory()->createQuietly();
});

test('a card can be added without an acquisition date, which defaults to today', function () {
    $response = $this->actingAs($this->owner)->postJson(
        "/api/portfolios/{$this->portfolio->id}/blueprints",
        addCardPayload($this->blueprint)
    );

    $response->assertCreated();

    $addedUserCard = $this->owner->userCards()->first();
    expect($addedUserCard->acquired_at->isToday())->toBeTrue();
    expect($this->portfolio->userCards()->first()->pivot->quantity)->toBe(2);
});

test('a card can be added with a zero purchase price', function () {
    $response = $this->actingAs($this->owner)->postJson(
        "/api/portfolios/{$this->portfolio->id}/blueprints",
        addCardPayload($this->blueprint, ['purchase_price_cents' => 0])
    );

    $response->assertCreated();
    expect($this->owner->userCards()->first()->purchase_price_cents)->toBe(0);
});

test('a card cannot be acquired in the future', function () {
    $response = $this->actingAs($this->owner)->postJson(
        "/api/portfolios/{$this->portfolio->id}/blueprints",
        addCardPayload($this->blueprint, ['acquired_at' => now()->addDay()->toDateString()])
    );

    $response->assertUnprocessable()->assertJsonValidationErrors('acquired_at');
});
