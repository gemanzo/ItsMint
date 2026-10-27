<?php

use App\Models\Blueprint;
use App\Services\BlueprintPriceService;
use Illuminate\Support\Facades\Http;

/**
 * A CardTrader marketplace listing for a One Piece card.
 */
function cardTraderListing(int $priceCents, string $condition, ?string $language, bool $isFoil = false, string $currency = 'EUR'): array
{
    return [
        'price_cents' => $priceCents,
        'price_currency' => $currency,
        'properties_hash' => [
            'condition' => $condition,
            'onepiece_language' => $language,
            'onepiece_foil' => $isFoil,
        ],
    ];
}

beforeEach(function () {
    config([
        'services.cardtrader.api_token' => 'test-token',
        'services.cardtrader.api_base_url' => 'https://api.cardtrader.test/api/v2',
    ]);
});

test('only valid non-foil EUR listings with a known language are priced', function () {
    $blueprint = Blueprint::factory()->createQuietly(['ct_id' => 555]);

    Http::fake([
        'api.cardtrader.test/*' => Http::response([
            '555' => [
                cardTraderListing(1200, 'Near Mint', 'en'),
                cardTraderListing(1000, 'Near Mint', 'en'),
                // Japanese spelled with the ISO code: same language as "jp"
                cardTraderListing(800, 'Near Mint', 'JA'),
                // Must all be ignored
                cardTraderListing(100, 'Near Mint', 'unknown'),
                cardTraderListing(100, 'Near Mint', null),
                cardTraderListing(100, 'Near Mint', 'en', isFoil: true),
                cardTraderListing(100, 'Near Mint', 'en', currency: 'USD'),
            ],
        ]),
    ]);

    $prices = app(BlueprintPriceService::class)->calculatePrices($blueprint);

    expect($prices['Near Mint_en']['price_cents'])->toBe(1000);
    expect($prices['Near Mint_en']['product_count'])->toBe(2);
    expect($prices['Near Mint_en']['is_foil'])->toBeFalse();
    expect($prices['Near Mint_jp']['price_cents'])->toBe(800);
    expect(collect($prices)->except('_metadata')->keys()->all())
        ->toEqualCanonicalizing(['Near Mint_en', 'Near Mint_jp']);
});
