<?php

use App\Models\Blueprint;
use App\Models\Portfolio;
use App\Models\User;
use App\Models\UserCard;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->blueprint = Blueprint::factory()->createQuietly();
});

function ownedCard($testCase, array $attributes = []): UserCard
{
    return UserCard::create(array_merge([
        'user_id' => $testCase->owner->id,
        'blueprint_id' => $testCase->blueprint->id,
        'condition' => 'NM',
        'language' => 'en',
    ], $attributes));
}

test('euro amounts are rounded to cents, not truncated', function (float $amountInEuros, int $expectedCents) {
    $userCard = ownedCard($this, ['purchase_price' => $amountInEuros]);

    expect($userCard->fresh()->purchase_price_cents)->toBe($expectedCents);
})->with([
    // (int) (19.99 * 100) was 1998
    [19.99, 1999],
    [4.35, 435],
    [0.29, 29],
    [10.0, 1000],
]);

test('a zero purchase price is kept, only null means unknown', function () {
    $freeCard = ownedCard($this, ['purchase_price' => 0.0]);
    $unknownCostCard = ownedCard($this, ['purchase_price' => null]);

    expect($freeCard->fresh()->purchase_price_cents)->toBe(0);
    expect($freeCard->fresh()->purchase_price)->toBe(0.0);
    expect($unknownCostCard->fresh()->purchase_price_cents)->toBeNull();
    expect($unknownCostCard->fresh()->purchase_price)->toBeNull();
});

test('the purchase price can be updated in integer cents', function () {
    $userCard = ownedCard($this, ['purchase_price_cents' => 1000]);

    $this->actingAs($this->owner)
        ->putJson("/api/user-cards/{$userCard->id}", ['purchase_price_cents' => 1999])
        ->assertOk();
    expect($userCard->fresh()->purchase_price_cents)->toBe(1999);

    $this->actingAs($this->owner)
        ->putJson("/api/user-cards/{$userCard->id}", ['purchase_price_cents' => null])
        ->assertOk();
    expect($userCard->fresh()->purchase_price_cents)->toBeNull();
});

test('cards bought for free or with an unknown cost can be sold', function (?int $purchasePriceCents) {
    $portfolio = Portfolio::create(['user_id' => $this->owner->id, 'name' => 'Main']);
    $userCard = ownedCard($this, ['purchase_price_cents' => $purchasePriceCents]);
    $portfolio->userCards()->attach($userCard->id, ['quantity' => 2]);

    $this->actingAs($this->owner)->postJson("/api/portfolios/{$portfolio->id}/sell", [
        'user_card_id' => $userCard->id,
        'quantity' => 1,
        'unit_sale_price_cents' => 500,
        'platform' => 'private',
    ])->assertSuccessful();

    expect($portfolio->userCards()->first()->pivot->quantity)->toBe(1);
})->with([
    'free card' => [0],
    'unknown cost' => [null],
]);
