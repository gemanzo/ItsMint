<?php

use App\Models\Blueprint;
use App\Models\Portfolio;
use App\Models\PortfolioTransaction;
use App\Models\User;
use App\Models\UserCard;
use App\Support\PortfolioInputRules;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->subscriptions()->create([
        'type' => 'premium',
        'stripe_id' => 'sub_test_validation',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_premium',
        'quantity' => 1,
    ]);
    $this->portfolio = Portfolio::create(['user_id' => $this->owner->id, 'name' => 'Main']);
    $this->blueprint = Blueprint::factory()->createQuietly();
});

function addCardRequest($testCase, array $overrides = [])
{
    return $testCase->actingAs($testCase->owner)->postJson("/api/portfolios/{$testCase->portfolio->id}/blueprints", array_merge([
        'blueprint_id' => $testCase->blueprint->id,
        'condition' => 'NM',
        'language' => 'en',
        'quantity' => 1,
        'purchase_price_cents' => 1000,
    ], $overrides));
}

test('today in the user local timezone is accepted even when it is already tomorrow in UTC', function () {
    // Just after midnight in Italy the local date is one day ahead of UTC
    addCardRequest($this, ['acquired_at' => now()->addDay()->toDateString()])->assertCreated();

    addCardRequest($this, ['acquired_at' => now()->addDays(2)->toDateString()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('acquired_at');
});

test('quantities, prices and notes above the limits are refused', function (array $invalidField, string $errorField) {
    addCardRequest($this, $invalidField)->assertUnprocessable()->assertJsonValidationErrors($errorField);
})->with([
    'quantity' => [['quantity' => PortfolioInputRules::MAX_QUANTITY + 1], 'quantity'],
    'price' => [['purchase_price_cents' => PortfolioInputRules::MAX_UNIT_PRICE_CENTS + 1], 'purchase_price_cents'],
    'notes' => [['notes' => str_repeat('a', PortfolioInputRules::MAX_NOTES_LENGTH + 1)], 'notes'],
]);

test('large amounts do not overflow the cash ledger', function () {
    addCardRequest($this, [
        'quantity' => PortfolioInputRules::MAX_QUANTITY,
        'purchase_price_cents' => PortfolioInputRules::MAX_UNIT_PRICE_CENTS,
    ])->assertCreated();

    // 10,000 x 1,000,000.00 EUR: far beyond a 32 bit integer
    expect(PortfolioTransaction::sole()->amount_cents)->toBe(-1_000_000_000_000);
});

test('a copy cannot be sold before it was acquired', function () {
    addCardRequest($this, ['acquired_at' => now()->subDays(2)->toDateString()])->assertCreated();
    $lot = UserCard::sole();

    $this->actingAs($this->owner)->postJson("/api/portfolios/{$this->portfolio->id}/sell", [
        'user_card_id' => $lot->id,
        'quantity' => 1,
        'unit_sale_price_cents' => 2000,
        'platform' => 'private',
        'sold_at' => now()->subDays(5)->toDateString(),
    ])->assertUnprocessable()->assertJsonValidationErrors('sold_at');

    // The refused sale changed nothing
    expect($this->portfolio->userCards()->first()->pivot->quantity)->toBe(1);
});

test('the page size of the sales list is clamped', function (string $requestedPerPage, int $expectedPerPage) {
    $this->actingAs($this->owner)
        ->getJson("/api/portfolios/{$this->portfolio->id}/sales?per_page={$requestedPerPage}")
        ->assertOk()
        ->assertJsonPath('per_page', $expectedPerPage);
})->with([
    'too large' => ['100000', PortfolioInputRules::MAX_PER_PAGE],
    'negative' => ['-5', 1],
]);

test('the sales export neutralizes spreadsheet formulas', function () {
    addCardRequest($this)->assertCreated();
    $this->actingAs($this->owner)->postJson("/api/portfolios/{$this->portfolio->id}/sell", [
        'user_card_id' => UserCard::sole()->id,
        'quantity' => 1,
        'unit_sale_price_cents' => 2000,
        'platform' => 'private',
        'notes' => '=HYPERLINK("https://evil.example","click")',
    ])->assertSuccessful();

    $exportedCsv = $this->actingAs($this->owner)
        ->get("/api/portfolios/{$this->portfolio->id}/sales/export")
        ->streamedContent();

    expect($exportedCsv)->toContain("'=HYPERLINK");
});
