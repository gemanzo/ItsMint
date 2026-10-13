<?php

use App\Models\Blueprint;
use App\Models\CardPrice;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\PricePoint;
use App\Services\Pricing\PriceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Build a provider returning fixed price points for the given source.
 *
 * @param  int[]  $pricesInCents
 */
function fakePriceProvider(string $sourceName, array $pricesInCents): PriceProvider
{
    return new class($sourceName, $pricesInCents) implements PriceProvider
    {
        public function __construct(
            private readonly string $sourceName,
            private readonly array $pricesInCents,
        ) {}

        public function sourceName(): string
        {
            return $this->sourceName;
        }

        public function fetch(Blueprint $blueprint): Collection
        {
            return collect($this->pricesInCents)->map(fn (int $priceCents) => new PricePoint(
                blueprintId: $blueprint->id,
                priceCents: $priceCents,
                currency: 'EUR',
                condition: 'Near Mint',
                language: 'en',
                isFoil: false,
                // Fresh points: no age decay on the source weight
                fetchedAt: CarbonImmutable::now(),
                source: $this->sourceName,
            ));
        }
    };
}

test('each source weight is applied to its own price', function () {
    $blueprint = Blueprint::factory()->createQuietly();

    // Cardmarket guide weighs 9, CardTrader weighs 1:
    // (1000 * 9 + 500 * 1) / 10 = 950 cents
    $aggregator = new PriceAggregatorService([
        fakePriceProvider('cardmarket_guide', [1000]),
        fakePriceProvider('cardtrader', [500]),
    ]);

    $estimates = $aggregator->estimate($blueprint);

    expect($estimates['Near Mint_en']['price_cents'])->toBe(950);
    expect(CardPrice::where('blueprint_id', $blueprint->id)->value('price_cents'))->toBe(950);
});

test('outliers are clamped without misaligning the remaining prices', function () {
    $blueprint = Blueprint::factory()->createQuietly();

    // With 7 points and alpha 0.15 the lowest and the highest value are clamped
    // to their nearest neighbours: 100 -> 1000 and 9000 -> 1000.
    $aggregator = new PriceAggregatorService([
        fakePriceProvider('cardtrader', [9000, 1000, 1000, 100, 1000, 1000, 1000]),
    ]);

    $estimates = $aggregator->estimate($blueprint);

    expect($estimates['Near Mint_en']['price_cents'])->toBe(1000);
});
