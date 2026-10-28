<?php

use App\Models\Blueprint;
use App\Models\CardPrice;
use App\Models\Portfolio;
use App\Models\User;
use App\Models\UserCard;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->portfolio = Portfolio::create(['user_id' => $this->owner->id, 'name' => 'Main']);
    $this->blueprint = Blueprint::factory()->createQuietly();
});

function addToPortfolio($testCase, string $condition, string $language)
{
    return $testCase->actingAs($testCase->owner)->postJson("/api/portfolios/{$testCase->portfolio->id}/blueprints", [
        'blueprint_id' => $testCase->blueprint->id,
        'condition' => $condition,
        'language' => $language,
        'quantity' => 1,
        'purchase_price_cents' => 1000,
    ]);
}

test('the same card added with different spellings ends up in a single holding', function () {
    addToPortfolio($this, 'Near Mint', 'EN')->assertCreated();
    addToPortfolio($this, 'NM', 'en')->assertCreated();

    $userCards = $this->owner->userCards()->get();
    expect($userCards)->toHaveCount(1);
    expect($userCards->first()->condition)->toBe('NM');
    expect($userCards->first()->language)->toBe('en');
    expect($this->portfolio->userCards()->first()->pivot->quantity)->toBe(2);
});

test('unknown conditions and languages are rejected', function () {
    addToPortfolio($this, 'Mint', 'en')->assertUnprocessable()->assertJsonValidationErrors('condition');
    addToPortfolio($this, 'NM', 'klingon')->assertUnprocessable()->assertJsonValidationErrors('language');
});

test('the purchase price defaults to the market price of the same condition', function () {
    // CardTrader calls "Slightly Played" what our forms call "Lightly Played" (LP)
    foreach (['Near Mint' => 1000, 'Slightly Played' => 800] as $marketCondition => $priceCents) {
        CardPrice::create([
            'blueprint_id' => $this->blueprint->id,
            'condition' => $marketCondition,
            'language' => 'en',
            'is_foil' => false,
            'price_cents' => $priceCents,
            'currency' => 'EUR',
            'source' => 'aggregate:cardtrader',
            'fetched_at' => now(),
        ]);
    }

    $this->actingAs($this->owner)->postJson('/api/user-cards', [
        'blueprint_id' => $this->blueprint->id,
        'condition' => 'LP',
        'language' => 'en',
        'quantity' => 1,
    ])->assertCreated();

    expect($this->owner->userCards()->first()->purchase_price_cents)->toBe(800);
});

test('the model stores the canonical vocabulary whatever the spelling', function () {
    $userCard = UserCard::create([
        'user_id' => $this->owner->id,
        'blueprint_id' => $this->blueprint->id,
        'condition' => 'Heavily Played',
        'language' => 'JA',
    ]);

    expect($userCard->fresh()->condition)->toBe('HP');
    expect($userCard->fresh()->language)->toBe('jp');
});
