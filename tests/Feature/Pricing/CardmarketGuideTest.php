<?php

use App\Models\Blueprint;
use App\Models\CardPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Write a Cardmarket price guide to a temporary file and point the config to it.
 *
 * @param  array<int, array<string, mixed>>  $priceGuideRows
 */
function useLocalCardmarketGuide(string $createdAt, array $priceGuideRows): string
{
    $guidePath = sys_get_temp_dir().'/itsmint_test_price_guide_'.uniqid().'.json';
    File::put($guidePath, json_encode([
        'version' => 1,
        'createdAt' => $createdAt,
        'priceGuides' => $priceGuideRows,
    ]));

    config([
        'services.cardmarket.op_price_guide_path' => $guidePath,
        'services.cardmarket.op_price_guide_url' => 'https://downloads.cardmarket.test/price_guide.json',
    ]);

    return $guidePath;
}

/**
 * A single card blueprint whose CardTrader default language is the given one.
 *
 * @param  int[]  $cardmarketProductIds
 */
function blueprintWithCardmarketIds(array $cardmarketProductIds, string $primaryLanguage = 'en'): Blueprint
{
    return Blueprint::factory()->createQuietly([
        'card_market_ids' => $cardmarketProductIds,
        'properties' => [
            'editable_properties' => [
                ['name' => 'onepiece_language', 'default_value' => $primaryLanguage],
            ],
        ],
    ]);
}

test('the ingest prices cards from the local guide without calling any API', function () {
    Http::fake();
    useLocalCardmarketGuide(now()->subHours(3)->toIso8601String(), [
        ['idProduct' => 123, 'avg' => 10.00, 'trend' => 9.50],
    ]);
    $blueprint = blueprintWithCardmarketIds([123]);

    $this->artisan('itsmint:ingest-cardmarket')->assertSuccessful();

    Http::assertNothingSent();
    expect(DB::table('card_price_points')->where('blueprint_id', $blueprint->id)->value('source'))->toBe('cardmarket_guide');
    expect(CardPrice::where('blueprint_id', $blueprint->id)->value('price_cents'))->toBe(1000);
});

test('a stale guide is refused instead of being stored as current prices', function () {
    useLocalCardmarketGuide(now()->subDays(3)->toIso8601String(), [
        ['idProduct' => 123, 'avg' => 10.00],
    ]);
    blueprintWithCardmarketIds([123]);

    $this->artisan('itsmint:ingest-cardmarket')->assertFailed();

    expect(DB::table('card_price_points')->count())->toBe(0);
    expect(CardPrice::count())->toBe(0);
});

test('japanese cards use their japanese cardmarket product', function () {
    // The first id is the english product, the second the japanese one ("en:0,jp:1")
    useLocalCardmarketGuide(now()->toIso8601String(), [
        ['idProduct' => 111, 'avg' => 5.00],
        ['idProduct' => 222, 'avg' => 20.00],
    ]);
    // CardTrader writes Japanese as "ja"
    $blueprint = blueprintWithCardmarketIds([111, 222], 'ja');

    $this->artisan('itsmint:ingest-cardmarket')->assertSuccessful();

    $storedPoint = DB::table('card_price_points')->where('blueprint_id', $blueprint->id)->first();
    expect($storedPoint->language)->toBe('jp');
    expect($storedPoint->price_cents)->toBe(2000);
});

test('an invalid download never replaces the current guide', function () {
    $guidePath = useLocalCardmarketGuide(now()->toIso8601String(), [
        ['idProduct' => 123, 'avg' => 10.00],
    ]);
    $currentGuideContent = File::get($guidePath);
    Http::fake(['downloads.cardmarket.test/*' => Http::response('<html>Maintenance</html>', 200)]);

    $this->artisan('itsmint:download-cardmarket-guide')->assertFailed();

    expect(File::get($guidePath))->toBe($currentGuideContent);
});

test('a valid download replaces the guide', function () {
    $guidePath = useLocalCardmarketGuide(now()->subDays(2)->toIso8601String(), []);
    $newGuide = [
        'version' => 1,
        'createdAt' => now()->toIso8601String(),
        'priceGuides' => [['idProduct' => 123, 'avg' => 12.5]],
    ];
    Http::fake(['downloads.cardmarket.test/*' => Http::response($newGuide, 200)]);

    $this->artisan('itsmint:download-cardmarket-guide')->assertSuccessful();

    expect(json_decode(File::get($guidePath), true))->toBe($newGuide);
    expect(File::exists($guidePath.'.download'))->toBeFalse();
});
