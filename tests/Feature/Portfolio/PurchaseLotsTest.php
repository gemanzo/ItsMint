<?php

use App\Models\Blueprint;
use App\Models\Portfolio;
use App\Models\PortfolioSale;
use App\Models\PortfolioTransaction;
use App\Models\User;
use App\Models\UserCard;

beforeEach(function () {
    $this->owner = User::factory()->create();
    // Premium: the free plan limits are not under test here
    $this->owner->subscriptions()->create([
        'type' => 'premium',
        'stripe_id' => 'sub_test_lots',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_premium',
        'quantity' => 1,
    ]);
    $this->portfolio = Portfolio::create(['user_id' => $this->owner->id, 'name' => 'Main']);
    $this->blueprint = Blueprint::factory()->createQuietly();
});

/**
 * Buy copies of the test card (Near Mint, English) through the API.
 */
function buyCopies($testCase, int $quantity, int $unitPriceCents, string $acquiredAt): UserCard
{
    $testCase->actingAs($testCase->owner)->postJson("/api/portfolios/{$testCase->portfolio->id}/blueprints", [
        'blueprint_id' => $testCase->blueprint->id,
        'condition' => 'NM',
        'language' => 'en',
        'quantity' => $quantity,
        'purchase_price_cents' => $unitPriceCents,
        'acquired_at' => $acquiredAt,
    ])->assertCreated();

    return UserCard::latest('id')->first();
}

function sellCopies($testCase, UserCard $anyLotOfTheCard, int $quantity, int $unitSalePriceCents)
{
    return $testCase->actingAs($testCase->owner)->postJson("/api/portfolios/{$testCase->portfolio->id}/sell", [
        'user_card_id' => $anyLotOfTheCard->id,
        'quantity' => $quantity,
        'unit_sale_price_cents' => $unitSalePriceCents,
        'platform' => 'private',
    ]);
}

/**
 * Quantity still owned of each lot in the test portfolio, keyed by lot id.
 */
function ownedQuantities($testCase): array
{
    return $testCase->portfolio->userCards()->get()
        ->mapWithKeys(fn (UserCard $lot) => [$lot->id => $lot->pivot->quantity])
        ->all();
}

test('buying the same card again keeps the cost of the copies already owned', function () {
    $firstLot = buyCopies($this, 3, 1000, now()->subDays(10)->toDateString());
    $secondLot = buyCopies($this, 1, 2000, now()->toDateString());

    expect($firstLot->id)->not->toBe($secondLot->id);
    expect($firstLot->fresh()->purchase_price_cents)->toBe(1000);

    // 3 x 10.00 + 1 x 20.00 (it used to become 4 x 20.00)
    $costBasisCents = $this->portfolio->userCards()->get()
        ->sum(fn (UserCard $lot) => $lot->purchase_price_cents * $lot->pivot->quantity);
    expect($costBasisCents)->toBe(5000);
});

test('sales consume the oldest lots first', function () {
    $oldestLot = buyCopies($this, 3, 1000, now()->subDays(10)->toDateString());
    $newestLot = buyCopies($this, 1, 2000, now()->toDateString());

    // Sold from the newest lot's row: the oldest copies still go first (FIFO)
    sellCopies($this, $newestLot, 2, 2500)->assertSuccessful();

    expect(ownedQuantities($this))->toBe([$oldestLot->id => 1, $newestLot->id => 1]);

    $sale = PortfolioSale::with('lots')->first();
    expect($sale->lots)->toHaveCount(1);
    expect($sale->lots->first()->user_card_id)->toBe($oldestLot->id);
    // (25.00 - 10.00) x 2
    expect($sale->realized_profit_cents)->toBe(3000);
});

test('a sale spanning several lots records each lot and its exact profit', function () {
    $oldestLot = buyCopies($this, 3, 1000, now()->subDays(10)->toDateString());
    $newestLot = buyCopies($this, 1, 2000, now()->toDateString());

    sellCopies($this, $oldestLot, 4, 2500)->assertSuccessful();

    $sale = PortfolioSale::with('lots')->first();
    expect($sale->lots->pluck('quantity', 'user_card_id')->all())->toBe([$oldestLot->id => 3, $newestLot->id => 1]);
    // Average unit cost: (3 x 10.00 + 1 x 20.00) / 4 = 12.50
    expect($sale->unit_purchase_price_cents)->toBe(1250);
    // 4 x 25.00 - 50.00
    expect($sale->realized_profit_cents)->toBe(5000);
    expect(ownedQuantities($this))->toBe([]);
});

test('selling more copies than owned is refused and changes nothing', function () {
    $lot = buyCopies($this, 2, 1000, now()->toDateString());

    sellCopies($this, $lot, 3, 2500)->assertUnprocessable()->assertJsonValidationErrors('quantity');

    expect(ownedQuantities($this))->toBe([$lot->id => 2]);
    expect(PortfolioSale::count())->toBe(0);
});

test('removing the remaining copies keeps the sales history', function () {
    $lot = buyCopies($this, 3, 1000, now()->toDateString());
    sellCopies($this, $lot, 1, 2500)->assertSuccessful();

    $this->actingAs($this->owner)->deleteJson("/api/portfolios/{$this->portfolio->id}/blueprints", [
        'blueprint_id' => $this->blueprint->id,
        'condition' => 'NM',
        'language' => 'en',
        'quantity' => 2,
    ])->assertOk();

    $sale = PortfolioSale::first();
    expect($sale)->not->toBeNull();
    expect($sale->blueprint_id)->toBe($this->blueprint->id);
    expect($sale->realized_profit_cents)->toBe(1500);
});

test('removing copies takes the most recent lots first, or the chosen lot', function () {
    $oldestLot = buyCopies($this, 2, 1000, now()->subDays(10)->toDateString());
    $newestLot = buyCopies($this, 2, 2000, now()->toDateString());
    $removalPayload = [
        'blueprint_id' => $this->blueprint->id,
        'condition' => 'NM',
        'language' => 'en',
        'quantity' => 1,
    ];

    $this->actingAs($this->owner)->deleteJson("/api/portfolios/{$this->portfolio->id}/blueprints", $removalPayload)->assertOk();
    expect(ownedQuantities($this))->toBe([$oldestLot->id => 2, $newestLot->id => 1]);

    $this->actingAs($this->owner)
        ->deleteJson("/api/portfolios/{$this->portfolio->id}/blueprints", $removalPayload + ['user_card_id' => $oldestLot->id])
        ->assertOk();
    expect(ownedQuantities($this))->toBe([$oldestLot->id => 1, $newestLot->id => 1]);
});

test('cancelling a sale gives the copies back to their lots', function () {
    $oldestLot = buyCopies($this, 1, 1000, now()->subDays(10)->toDateString());
    $newestLot = buyCopies($this, 1, 2000, now()->toDateString());
    sellCopies($this, $oldestLot, 2, 2500)->assertSuccessful();
    $sale = PortfolioSale::first();

    $this->actingAs($this->owner)
        ->deleteJson("/api/portfolios/{$this->portfolio->id}/sales/{$sale->id}")
        ->assertNoContent();

    expect(ownedQuantities($this))->toBe([$oldestLot->id => 1, $newestLot->id => 1]);
    expect(PortfolioTransaction::where('type', PortfolioTransaction::TYPE_SALE)->count())->toBe(0);
});

test('the quantity of a sale cannot be edited', function () {
    $lot = buyCopies($this, 2, 1000, now()->toDateString());
    sellCopies($this, $lot, 1, 2500)->assertSuccessful();
    $sale = PortfolioSale::first();

    // It used to add phantom copies back when the sale was later deleted
    $this->actingAs($this->owner)
        ->putJson("/api/portfolios/{$this->portfolio->id}/sales/{$sale->id}", ['quantity' => 10])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    expect($sale->fresh()->quantity)->toBe(1);
});

test('each lot has one purchase transaction that follows its corrections', function () {
    $lot = buyCopies($this, 3, 1000, now()->toDateString());
    $purchaseAmount = fn () => PortfolioTransaction::where('type', PortfolioTransaction::TYPE_PURCHASE)
        ->where('user_card_id', $lot->id)
        ->sole()
        ->amount_cents;

    expect($purchaseAmount())->toBe(-3000);

    // Quantity corrected from 3 to 2
    $this->actingAs($this->owner)
        ->putJson("/api/portfolios/{$this->portfolio->id}/user-cards/{$lot->id}", ['quantity' => 2])
        ->assertOk();
    expect($purchaseAmount())->toBe(-2000);

    // Selling does not change what was paid
    sellCopies($this, $lot, 1, 2500)->assertSuccessful();
    expect($purchaseAmount())->toBe(-2000);

    // Price corrected from 10.00 to 12.00: ledger and sale follow
    $this->actingAs($this->owner)
        ->putJson("/api/user-cards/{$lot->id}", ['purchase_price_cents' => 1200])
        ->assertOk();
    expect($purchaseAmount())->toBe(-2400);
    expect(PortfolioSale::first()->realized_profit_cents)->toBe(1300);
});

test('the quantity correction only changes the chosen lot', function () {
    $oldestLot = buyCopies($this, 2, 1000, now()->subDays(10)->toDateString());
    $newestLot = buyCopies($this, 2, 1000, now()->toDateString());

    // Same price: the old code merged both lots into the edited one
    $this->actingAs($this->owner)
        ->putJson("/api/portfolios/{$this->portfolio->id}/user-cards/{$newestLot->id}", ['quantity' => 5])
        ->assertOk();

    expect(ownedQuantities($this))->toBe([$oldestLot->id => 2, $newestLot->id => 5]);
});

test('the profit of a sale from a lot with unknown cost is unknown, not counted as zero cost', function () {
    $lot = UserCard::create([
        'user_id' => $this->owner->id,
        'blueprint_id' => $this->blueprint->id,
        'condition' => 'NM',
        'language' => 'en',
    ]);
    $this->portfolio->userCards()->attach($lot->id, ['quantity' => 1]);

    sellCopies($this, $lot, 1, 2500)->assertSuccessful();

    $sale = PortfolioSale::first();
    expect($sale->unit_purchase_price_cents)->toBeNull();
    expect($sale->realized_profit_cents)->toBeNull();
});
