<?php

use App\Exceptions\CardTraderApiException;
use App\Jobs\UpdateBlueprintPriceJob;
use App\Models\Blueprint;
use App\Models\CardPrice;
use App\Services\BlueprintPriceService;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;

/**
 * CardTrader marketplace response with one Near Mint English listing for the card.
 */
function cardTraderMarketplaceResponse(Blueprint $blueprint, int $priceCents): array
{
    return [
        (string) $blueprint->ct_id => [[
            'price_cents' => $priceCents,
            'price_currency' => 'EUR',
            'properties_hash' => [
                'condition' => 'Near Mint',
                'onepiece_language' => 'en',
                'onepiece_foil' => false,
            ],
        ]],
    ];
}

beforeEach(function () {
    config([
        'services.cardtrader.api_token' => 'test-token',
        'services.cardtrader.api_base_url' => 'https://api.cardtrader.test/api/v2',
    ]);

    // Retries must not really wait during the tests
    Sleep::fake();
});

test('the job stores the CardTrader listings and the price of the day', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    Http::fake(['api.cardtrader.test/*' => Http::response(cardTraderMarketplaceResponse($blueprint, 1500))]);

    UpdateBlueprintPriceJob::dispatchSync($blueprint);

    expect(DB::table('card_price_points')->where('blueprint_id', $blueprint->id)->where('source', 'cardtrader')->count())->toBe(1);
    expect(CardPrice::where('blueprint_id', $blueprint->id)->value('price_cents'))->toBe(1500);
});

test('a card fetched recently is not fetched again', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    Http::fake(['api.cardtrader.test/*' => Http::response(cardTraderMarketplaceResponse($blueprint, 1500))]);

    UpdateBlueprintPriceJob::dispatchSync($blueprint);
    UpdateBlueprintPriceJob::dispatchSync($blueprint);

    Http::assertSentCount(1);
});

test('transient CardTrader errors are retried, then reported as a failure', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    Http::fake(['api.cardtrader.test/*' => Http::response('Server error', 500)]);

    expect(fn () => app(BlueprintPriceService::class)->calculatePrices($blueprint))
        ->toThrow(CardTraderApiException::class);

    Http::assertSentCount(3);
    expect(CardPrice::count())->toBe(0);
});

test('permanent CardTrader errors are not retried', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    Http::fake(['api.cardtrader.test/*' => Http::response('Unauthorized', 401)]);

    expect(fn () => app(BlueprintPriceService::class)->calculatePrices($blueprint))
        ->toThrow(CardTraderApiException::class);

    Http::assertSentCount(1);
});

test('a CardTrader failure makes the job fail instead of silently succeeding', function () {
    $blueprint = Blueprint::factory()->createQuietly();
    Http::fake(['api.cardtrader.test/*' => Http::response('Server error', 503)]);

    expect(fn () => UpdateBlueprintPriceJob::dispatchSync($blueprint))
        ->toThrow(CardTraderApiException::class);
});

test('price jobs go to the price-updates queue and retry until a deadline', function () {
    Queue::fake();
    $blueprint = Blueprint::factory()->createQuietly();

    UpdateBlueprintPriceJob::dispatch($blueprint);

    Queue::assertPushedOn('price-updates', UpdateBlueprintPriceJob::class);
    expect((new UpdateBlueprintPriceJob($blueprint))->retryUntil()->isFuture())->toBeTrue();
});

test('the nightly command only queues cards not fetched recently', function () {
    Bus::fake();
    $recentlyFetchedBlueprint = Blueprint::factory()->createQuietly();
    $outdatedBlueprint = Blueprint::factory()->createQuietly();
    DB::table('card_price_points')->insert([
        'blueprint_id' => $recentlyFetchedBlueprint->id,
        'price_cents' => 1000,
        'currency' => 'EUR',
        'condition' => 'Near Mint',
        'language' => 'en',
        'is_foil' => false,
        'source' => 'cardtrader',
        'fetched_at' => now()->subHour(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('itsmint:queue-price-updates')->assertSuccessful();

    Bus::assertBatched(function (PendingBatch $batch) use ($outdatedBlueprint) {
        return $batch->jobs->count() === 1
            && $batch->jobs->first()->blueprint->is($outdatedBlueprint);
    });
});
