<?php

use App\Models\Blueprint;
use App\Models\CardPrice;
use App\Models\User;
use App\Models\UserCard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Store an English non-foil price of the card for the given day.
 */
function storePrice(Blueprint $blueprint, string $condition, int $priceCents, int $daysAgo): CardPrice
{
    return CardPrice::create([
        'blueprint_id' => $blueprint->id,
        'condition' => $condition,
        'language' => 'en',
        'is_foil' => false,
        'price_cents' => $priceCents,
        'currency' => 'EUR',
        'source' => 'aggregate:cardtrader',
        'fetched_at' => now()->subDays($daysAgo),
    ]);
}

test('a recent price wins over an older price in a better condition', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    storePrice($blueprint, 'Near Mint', 5000, daysAgo: 10);
    storePrice($blueprint, 'Slightly Played', 2000, daysAgo: 0);

    $latestPrice = $blueprint->latestPrice()->first();

    expect($latestPrice->condition)->toBe('Slightly Played');
    expect($latestPrice->price_cents)->toBe(2000);
});

test('within the same day the best condition wins', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    storePrice($blueprint, 'Slightly Played', 2000, daysAgo: 0);
    storePrice($blueprint, 'Near Mint', 2500, daysAgo: 0);

    expect($blueprint->latestPriceForLanguage('en')->first()->condition)->toBe('Near Mint');
});

test('prices older than the freshness window are not current anymore', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    storePrice($blueprint, 'Near Mint', 5000, daysAgo: CardPrice::CURRENT_PRICE_MAX_AGE_DAYS + 1);

    expect($blueprint->latestPrice()->first())->toBeNull();
});

test('the latest prices view follows the same rules', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    storePrice($blueprint, 'Near Mint', 5000, daysAgo: 10);
    storePrice($blueprint, 'Slightly Played', 2000, daysAgo: 0);
    $staleBlueprint = Blueprint::factory()->createQuietly();
    storePrice($staleBlueprint, 'Near Mint', 9000, daysAgo: 60);

    Artisan::call('itsmint:refresh-latest');

    $viewRows = DB::table('card_latest_prices_mv')->get()->keyBy('blueprint_id');
    expect($viewRows[$blueprint->id]->price_cents)->toBe(2000);
    expect($viewRows->has($staleBlueprint->id))->toBeFalse();
});

test('the card valuation uses the most recent price', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    storePrice($blueprint, 'Near Mint', 5000, daysAgo: 10);
    storePrice($blueprint, 'Slightly Played', 2000, daysAgo: 0);
    $userCard = UserCard::create([
        'user_id' => User::factory()->create()->id,
        'blueprint_id' => $blueprint->id,
        'condition' => 'Near Mint',
        'language' => 'en',
    ]);

    // Same result whether the prices are eagerly loaded or queried
    $userCardWithLoadedPrices = UserCard::with('blueprint.cardPrices')->find($userCard->id);
    $userCardWithoutLoadedPrices = UserCard::with('blueprint')->find($userCard->id);

    expect($userCardWithLoadedPrices->estimated_value_cents)->toBe(2000);
    expect($userCardWithoutLoadedPrices->estimated_value_cents)->toBe(2000);
});
