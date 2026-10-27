<?php

use App\Models\Blueprint;
use App\Models\CardPrice;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\PricePoint;
use App\Services\Pricing\PriceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A Near Mint English price point of the given source.
 */
function pricePoint(Blueprint $blueprint, string $sourceName, int $priceCents, ?CarbonImmutable $fetchedAt = null, string $language = 'en'): PricePoint
{
    return new PricePoint(
        blueprintId: $blueprint->id,
        priceCents: $priceCents,
        currency: 'EUR',
        condition: 'Near Mint',
        language: $language,
        isFoil: false,
        // Fresh points by default: no age decay on the source weight
        fetchedAt: $fetchedAt ?? CarbonImmutable::now(),
        source: $sourceName,
    );
}

/**
 * Build a provider returning one fixed price for the given source.
 */
function fakePriceProvider(string $sourceName, int $priceCents): PriceProvider
{
    return new class($sourceName, $priceCents) implements PriceProvider
    {
        public function __construct(
            private readonly string $sourceName,
            private readonly int $priceCents,
        ) {}

        public function sourceName(): string
        {
            return $this->sourceName;
        }

        public function fetch(Blueprint $blueprint): Collection
        {
            return collect([pricePoint($blueprint, $this->sourceName, $this->priceCents)]);
        }
    };
}

test('each source weight is applied to its own price', function () {
    $blueprint = Blueprint::factory()->createQuietly();

    // Cardmarket guide weighs 9, CardTrader weighs 1:
    // (1000 * 9 + 500 * 1) / 10 = 950 cents
    $aggregator = new PriceAggregatorService([
        fakePriceProvider('cardmarket_guide', 1000),
        fakePriceProvider('cardtrader', 500),
    ]);

    $estimates = $aggregator->estimate($blueprint);

    expect($estimates['Near Mint_en']['price_cents'])->toBe(950);
    expect(CardPrice::where('blueprint_id', $blueprint->id)->value('price_cents'))->toBe(950);
});

test('outliers are clamped without misaligning the remaining prices', function () {
    $blueprint = Blueprint::factory()->createQuietly();

    // 7 sources with the same weight: with alpha 0.15 the lowest and the highest
    // values are clamped to their nearest neighbours (100 -> 1000, 9000 -> 1000)
    $aggregator = new PriceAggregatorService([
        fakePriceProvider('source_a', 9000),
        fakePriceProvider('source_b', 1000),
        fakePriceProvider('source_c', 1000),
        fakePriceProvider('source_d', 100),
        fakePriceProvider('source_e', 1000),
        fakePriceProvider('source_f', 1000),
        fakePriceProvider('source_g', 1000),
    ]);

    $estimates = $aggregator->estimate($blueprint);

    expect($estimates['Near Mint_en']['price_cents'])->toBe(1000);
});

test('running the aggregation twice on the same day keeps one price per day', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    $aggregator = app(PriceAggregatorService::class);
    $guideCreatedAt = CarbonImmutable::parse('today 03:00');

    // Same Cardmarket guide ingested twice (e.g. a retried run)
    $aggregator->recordPricePoints(collect([pricePoint($blueprint, 'cardmarket_guide', 1000, $guideCreatedAt)]));
    $aggregator->aggregate($blueprint);
    $aggregator->recordPricePoints(collect([pricePoint($blueprint, 'cardmarket_guide', 1000, $guideCreatedAt)]));
    $aggregator->aggregate($blueprint);

    expect(DB::table('card_price_points')->where('blueprint_id', $blueprint->id)->count())->toBe(1);
    expect(CardPrice::where('blueprint_id', $blueprint->id)->count())->toBe(1);
});

test('a later aggregation on the same day updates the price of the day', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    $aggregator = app(PriceAggregatorService::class);

    $aggregator->recordPricePoints(collect([pricePoint($blueprint, 'cardtrader', 1000, CarbonImmutable::now()->subHour())]));
    $aggregator->aggregate($blueprint);
    $aggregator->recordPricePoints(collect([pricePoint($blueprint, 'cardtrader', 1200)]));
    $aggregator->aggregate($blueprint);

    $pricesOfTheCard = CardPrice::where('blueprint_id', $blueprint->id)->get();
    expect($pricesOfTheCard)->toHaveCount(1);
    expect($pricesOfTheCard->first()->price_cents)->toBe(1200);
    expect($pricesOfTheCard->first()->price_date->isToday())->toBeTrue();
});

test('invalid points are never stored', function () {
    $blueprint = Blueprint::factory()->createQuietly();

    $storedPointsCount = app(PriceAggregatorService::class)->recordPricePoints(collect([
        pricePoint($blueprint, 'cardtrader', 0),
        pricePoint($blueprint, 'cardtrader', -100),
        pricePoint($blueprint, 'cardtrader', 1000, language: 'unknown'),
        pricePoint($blueprint, 'cardtrader', 1000, language: 'JA'),
    ]));

    expect($storedPointsCount)->toBe(1);
    expect(DB::table('card_price_points')->value('language'))->toBe('jp');
});

test('only the latest fresh point of each source is aggregated', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    $aggregator = app(PriceAggregatorService::class);

    $aggregator->recordPricePoints(collect([
        // Older observation of the same source: superseded by the newer one
        pricePoint($blueprint, 'cardtrader', 5000, CarbonImmutable::now()->subDays(2)),
        pricePoint($blueprint, 'cardtrader', 1000, CarbonImmutable::now()->subHour()),
        // Outside the freshness window: ignored
        pricePoint($blueprint, 'cardmarket_guide', 9999, CarbonImmutable::now()->subDays(10)),
    ]));

    $estimates = $aggregator->aggregate($blueprint);

    expect($estimates['Near Mint_en']['price_cents'])->toBe(1000);
    expect($estimates['Near Mint_en']['source'])->toBe('aggregate:cardtrader');
});

test('the price records the sources it comes from', function () {
    $blueprint = Blueprint::factory()->createQuietly();

    app(PriceAggregatorService::class)->recordPricePoints(collect([
        pricePoint($blueprint, 'cardtrader', 1000),
        pricePoint($blueprint, 'cardmarket_guide', 1000),
    ]));
    app(PriceAggregatorService::class)->aggregate($blueprint);

    expect(CardPrice::where('blueprint_id', $blueprint->id)->value('source'))
        ->toBe('aggregate:cardmarket_guide+cardtrader');
});
