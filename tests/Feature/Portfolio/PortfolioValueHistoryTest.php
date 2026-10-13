<?php

use App\Models\Blueprint;
use App\Models\CardPrice;
use App\Models\Portfolio;
use App\Models\User;
use App\Models\UserCard;

/**
 * Premium user owning a portfolio with 2 copies of a card bought at 10.00 EUR
 * ten days ago, with a 15.00 EUR Near Mint market price recorded five days ago.
 */
function createPortfolioWithPricedCard(): Portfolio
{
    $owner = User::factory()->create();
    $owner->subscriptions()->create([
        'type' => 'premium',
        'stripe_id' => 'sub_test_history',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_premium',
        'quantity' => 1,
    ]);

    $portfolio = Portfolio::create(['user_id' => $owner->id, 'name' => 'Main']);
    $blueprint = Blueprint::factory()->createQuietly();

    $userCard = UserCard::create([
        'user_id' => $owner->id,
        'blueprint_id' => $blueprint->id,
        'condition' => 'Near Mint',
        'language' => 'en',
        'purchase_price_cents' => 1000,
        'acquired_at' => now()->subDays(10),
    ]);
    $portfolio->userCards()->attach($userCard->id, ['quantity' => 2]);

    CardPrice::create([
        'blueprint_id' => $blueprint->id,
        'condition' => 'Near Mint',
        'language' => 'en',
        'price_cents' => 1500,
        'currency' => 'EUR',
        'is_foil' => false,
        'source' => 'aggregate_v1',
        'fetched_at' => now()->subDays(5),
    ]);

    return $portfolio;
}

test('the portfolio value history is computed', function () {
    $portfolio = createPortfolioWithPricedCard();

    $response = $this->actingAs($portfolio->user)
        ->getJson("/api/portfolios/{$portfolio->id}/value-history?period=1M");

    $response->assertOk();

    $latestPoint = collect($response->json())->last();
    expect($latestPoint['market_value_cents'])->toBe(3000);
    expect($latestPoint['cost_basis_cents'])->toBe(2000);
});

test('other users cannot read the value history of a portfolio', function () {
    $portfolio = createPortfolioWithPricedCard();
    $otherUser = User::factory()->create();
    $otherUser->subscriptions()->create([
        'type' => 'premium',
        'stripe_id' => 'sub_test_other',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_premium',
        'quantity' => 1,
    ]);

    $this->actingAs($otherUser)
        ->getJson("/api/portfolios/{$portfolio->id}/value-history")
        ->assertForbidden();
});
