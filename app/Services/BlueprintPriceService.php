<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class BlueprintPriceService
{
    private const CONDITION_HIERARCHY = [
        "Near Mint",
        "Slightly Played",
        "Moderately Played",
        "Played",
        "Poor"
    ];

    /**
     * Calculate and adjust blueprint prices based on CardTrader marketplace data.
     *
     * @param Blueprint $blueprint
     * @return array<string, array<string, mixed>>|null
     */
    public function calculatePrices(Blueprint $blueprint): ?array
    {
        $cacheKey = "blueprint_price_{$blueprint->id}";
        $cachedPrices = Cache::get($cacheKey);

        if ($cachedPrices !== null) {
            return $cachedPrices;
        }

        $token = config('services.cardtrader.api_token');
        $baseApiUrl = config('services.cardtrader.api_base_url');
        $blueprintCtId = $blueprint->ct_id;

        if (empty($token) || empty($baseApiUrl)) {
            Log::error('CardTrader API service not configured for BlueprintPriceService.', ['blueprint_id' => $blueprint->id]);
            return null;
        }

        $marketplaceUrl = $baseApiUrl . '/marketplace/products';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->retry(5, function ($attempt, \Illuminate\Http\Client\RequestException $exception) use ($blueprintCtId) {
                // Retry only on 429 errors
                if ($exception->response && $exception->response->status() === 429) {
                    // Exponential backoff increased: e.g., 2.25s, 6.75s, 20.25s, ...
                    $delay = pow(3, $attempt) * 750;
                    Log::warning('CardTrader API rate limit hit. Retrying attempt #' . $attempt . ' after ' . ($delay / 1000) . 's.', [
                        'blueprint_id' => $blueprintCtId,
                        'status' => $exception->response->status(),
                    ]);
                    return $delay;
                }
                // Do not retry for other errors
                return 0;
            })->get($marketplaceUrl, [
                'blueprint_id' => $blueprintCtId
            ]);

            if (!$response->successful()) {
                Log::error('CardTrader API Error - Fetching Marketplace Products in Service', [
                    'blueprint_id' => $blueprint->id,
                    'ct_id' => $blueprintCtId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null; // Don't cache failures to allow for retries
            }

            $apiProducts = $response->json()[$blueprintCtId] ?? [];

            if (empty($apiProducts)) {
                Log::info('No products found on marketplace for blueprint in Service.', ['blueprint_id' => $blueprint->id, 'ct_id' => $blueprintCtId]);
                Cache::put($cacheKey, null, now()->addMinutes(60)); // Cache null result
                return null;
            }

            $lowestPricesByCondition = $this->extractLowestPricesByCondition($apiProducts);

            if (empty($lowestPricesByCondition)) {
                Log::info('No valid products with recognized conditions found to process for blueprint in Service.', ['blueprint_id' => $blueprint->id, 'ct_id' => $blueprintCtId]);
                Cache::put($cacheKey, null, now()->addMinutes(60)); // Cache null result
                return null;
            }

            $calculatedPrices = $this->applyHierarchyLogic($lowestPricesByCondition);

            Cache::put($cacheKey, $calculatedPrices, now()->addMinutes(60));

            return $calculatedPrices;
        } catch (\Exception $e) {
            Log::error('Exception in BlueprintPriceService', [
                'blueprint_id' => $blueprint->id,
                'ct_id' => $blueprintCtId,
                'error' => $e->getMessage(),
                'trace_small' => substr($e->getTraceAsString(), 0, 500)
            ]);
            return null;
        }
    }

    /**
     * Extracts the lowest price for each recognized condition from the API products.
     *
     * @param array $apiProducts
     * @return array<string, array<string, mixed>>
     */
    private function extractLowestPricesByCondition(array $apiProducts): array
    {
        $lowestPricesByCondition = [];
        // TODO: Implement a proper currency conversion system. For now, we only process EUR.
        $targetCurrency = 'EUR';

        foreach ($apiProducts as $product) {
            $currency = $product['price_currency'];
            if ($currency !== $targetCurrency) {
                continue;
            }

            $properties = $product['properties_hash'] ?? $product['properties'] ?? [];
            $condition = $properties['condition'] ?? null;
            if (!$condition || !in_array($condition, self::CONDITION_HIERARCHY)) {
                continue;
            }

            $language = strtolower(
                $properties['onepiece_language'] ?? $properties['language'] ?? 'N/A'
            );
            $foil_raw = $properties['onepiece_foil'] ?? $properties['foil'] ?? null;
            $is_foil = filter_var($foil_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

            $priceCents = $product['price_cents'];

            if (!isset($lowestPricesByCondition[$condition]) || $priceCents < $lowestPricesByCondition[$condition]['price_cents']) {
                $lowestPricesByCondition[$condition] = [
                    'price_cents' => $priceCents,
                    'currency' => $currency,
                    'language' => $language,
                    'is_foil' => $is_foil,
                    'fetched_at' => Carbon::now(),
                    'source' => 'cardtrader_service_by_condition' // Changed source
                ];
            }
        }
        return $lowestPricesByCondition;
    }

    /**
     * Applies hierarchical pricing logic to the lowest prices.
     * Ensures that a lower condition card is not more expensive than a higher condition one.
     *
     * @param array<string, array<string, mixed>> $lowestPricesByCondition
     * @return array<string, array<string, mixed>>
     */
    private function applyHierarchyLogic(array $lowestPricesByCondition): array
    {
        $adjustedPrices = [];
        $lastValidPriceCents = null;

        foreach (self::CONDITION_HIERARCHY as $hierarchicalCondition) {
            if (isset($lowestPricesByCondition[$hierarchicalCondition])) {
                $currentEntry = $lowestPricesByCondition[$hierarchicalCondition];
                $originalPriceCents = $currentEntry['price_cents'];
                $adjustedPriceCents = $originalPriceCents;
                $source = $currentEntry['source']; // Keep original source or adjust

                if ($lastValidPriceCents !== null && $originalPriceCents > $lastValidPriceCents) {
                    $adjustedPriceCents = $lastValidPriceCents;
                    $source = 'cardtrader_service_adjusted_hierarchy'; // Changed source
                }

                $adjustedPrices[$hierarchicalCondition] = $currentEntry;
                $adjustedPrices[$hierarchicalCondition]['price_cents'] = $adjustedPriceCents;
                $adjustedPrices[$hierarchicalCondition]['source'] = $source;

                $lastValidPriceCents = $adjustedPriceCents;
            }
        }
        return $adjustedPrices;
    }
}
