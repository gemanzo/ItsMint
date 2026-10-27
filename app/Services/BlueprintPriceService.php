<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlueprintPriceService
{
    private const CONDITION_HIERARCHY = [
        'Near Mint',
        'Slightly Played',
        'Moderately Played',
        'Played',
        'Poor',
    ];

    private const SUPPORTED_LANGUAGES = [
        'jp' => 'Japanese',
        'en' => 'English',
        'fr' => 'French',
        'zh-cn' => 'Chinese Simplified',
        'zh-tw' => 'Chinese Traditional',
        'ko' => 'Korean',
    ];

    /**
     * Calculate and adjust blueprint prices based on CardTrader marketplace data.
     * Now returns prices organized by condition and language.
     *
     * @return array<string, array<string, mixed>>|null
     */
    public function calculatePrices(Blueprint $blueprint): ?array
    {
        $cacheKey = "blueprint_price_v2_{$blueprint->id}";
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

        $marketplaceUrl = $baseApiUrl.'/marketplace/products';

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->retry(5, function (int $attempt, \Throwable $exception) use ($blueprintCtId) {
                // Handle HTTP 429 rate limit
                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    $response = $exception->response;
                    if ($response && $response->status() === 429) {
                        // Exponential backoff: ~0.75s, 2.25s, 6.75s, ...
                        $delay = (int) (pow(3, $attempt) * 750);
                        Log::warning('CardTrader API rate limit hit. Retrying.', [
                            'attempt' => $attempt,
                            'blueprint_id' => $blueprintCtId,
                            'status' => $response->status(),
                        ]);

                        return $delay;
                    }
                }

                // Handle transient connection issues
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    $delay = (int) (pow(2, $attempt) * 500);
                    Log::warning('CardTrader API connection issue. Retrying.', [
                        'attempt' => $attempt,
                        'blueprint_id' => $blueprintCtId,
                        'error' => $exception->getMessage(),
                    ]);

                    return $delay;
                }

                // No retry for other errors
                return 0;
            })->get($marketplaceUrl, [
                'blueprint_id' => $blueprintCtId,
            ]);

            if (! $response->successful()) {
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
                Cache::put($cacheKey, null, now()->addMinutes(60)); // Cache null result

                return null;
            }

            $lowestPricesByConditionAndLanguage = $this->extractLowestPricesByConditionAndLanguage($apiProducts);

            if (empty($lowestPricesByConditionAndLanguage)) {
                Cache::put($cacheKey, null, now()->addMinutes(60)); // Cache null result

                return null;
            }

            $calculatedPrices = $this->applyHierarchyLogicByLanguage($lowestPricesByConditionAndLanguage);

            // Add summary statistics
            $calculatedPrices['_metadata'] = $this->generatePricingMetadata($apiProducts, $calculatedPrices);

            Cache::put($cacheKey, $calculatedPrices, now()->addMinutes(60));

            return $calculatedPrices;
        } catch (\Exception $e) {
            Log::error('Exception in BlueprintPriceService', [
                'blueprint_id' => $blueprint->id,
                'ct_id' => $blueprintCtId,
                'error' => $e->getMessage(),
                'trace_small' => substr($e->getTraceAsString(), 0, 500),
            ]);

            return null;
        }
    }

    /**
     * Extracts the lowest price for each condition-language combination from the API products.
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractLowestPricesByConditionAndLanguage(array $apiProducts): array
    {
        $lowestPrices = [];
        // TODO: Implement a proper currency conversion system. For now, we only process EUR.
        $targetCurrency = 'EUR';

        foreach ($apiProducts as $product) {
            $currency = $product['price_currency'];
            if ($currency !== $targetCurrency) {
                continue;
            }

            $properties = $product['properties_hash'] ?? $product['properties'] ?? [];
            $condition = $properties['condition'] ?? null;
            if (! $condition || ! in_array($condition, self::CONDITION_HIERARCHY)) {
                continue;
            }

            $rawLanguage = $properties['onepiece_language'] ?? $properties['language'] ?? 'unknown';
            $language = strtolower($rawLanguage);

            // Skip unknown languages for now, but log them for future support
            if (! isset(self::SUPPORTED_LANGUAGES[$language]) && $language !== 'unknown') {
                Log::error('Unsupported language found in CardTrader data', [
                    'language' => $language,
                    'raw_language' => $rawLanguage,
                    'product_id' => $product['id'] ?? 'unknown',
                ]);

                continue;
            }

            $foil_raw = $properties['onepiece_foil'] ?? $properties['foil'] ?? null;
            $is_foil = filter_var($foil_raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;

            $priceCents = $product['price_cents'];

            // Create unique key for condition-language combination
            $key = $condition.'_'.$language;

            if (! isset($lowestPrices[$key]) || $priceCents < $lowestPrices[$key]['price_cents']) {
                $lowestPrices[$key] = [
                    'price_cents' => $priceCents,
                    'currency' => $currency,
                    'condition' => $condition,
                    'language' => $language,
                    'language_name' => self::SUPPORTED_LANGUAGES[$language] ?? ucfirst($language),
                    'is_foil' => $is_foil,
                    'fetched_at' => Carbon::now(),
                    'source' => 'cardtrader_service_by_condition_and_language',
                    'product_count' => 1, // Will be updated below
                ];
            }
        }

        // Count how many products exist for each condition-language combination
        foreach ($apiProducts as $product) {
            $currency = $product['price_currency'];
            if ($currency !== $targetCurrency) {
                continue;
            }

            $properties = $product['properties_hash'] ?? $product['properties'] ?? [];
            $condition = $properties['condition'] ?? null;
            if (! $condition || ! in_array($condition, self::CONDITION_HIERARCHY)) {
                continue;
            }

            $language = strtolower($properties['onepiece_language'] ?? $properties['language'] ?? 'unknown');
            if (! isset(self::SUPPORTED_LANGUAGES[$language]) && $language !== 'unknown') {
                continue;
            }

            $key = $condition.'_'.$language;
            if (isset($lowestPrices[$key])) {
                $lowestPrices[$key]['product_count'] = ($lowestPrices[$key]['product_count'] ?? 0) + 1;
            }
        }

        return $lowestPrices;
    }

    /**
     * Applies hierarchical pricing logic to the lowest prices for each language separately.
     * Ensures that a lower condition card is not more expensive than a higher condition one within the same language.
     *
     * @param  array<string, array<string, mixed>>  $lowestPrices
     * @return array<string, array<string, mixed>>
     */
    private function applyHierarchyLogicByLanguage(array $lowestPrices): array
    {
        $adjustedPrices = [];

        // Group by language first
        $pricesByLanguage = [];
        foreach ($lowestPrices as $key => $priceData) {
            $language = $priceData['language'];
            $condition = $priceData['condition'];

            if (! isset($pricesByLanguage[$language])) {
                $pricesByLanguage[$language] = [];
            }

            $pricesByLanguage[$language][$condition] = $priceData;
        }

        // Apply hierarchy logic within each language
        foreach ($pricesByLanguage as $language => $conditionPrices) {
            $lastValidPriceCents = null;

            foreach (self::CONDITION_HIERARCHY as $hierarchicalCondition) {
                if (isset($conditionPrices[$hierarchicalCondition])) {
                    $currentEntry = $conditionPrices[$hierarchicalCondition];
                    $originalPriceCents = $currentEntry['price_cents'];
                    $adjustedPriceCents = $originalPriceCents;
                    $source = $currentEntry['source'];

                    if ($lastValidPriceCents !== null && $originalPriceCents > $lastValidPriceCents) {
                        $adjustedPriceCents = $lastValidPriceCents;
                        $source = 'cardtrader_service_adjusted_hierarchy_by_language';
                    }

                    $key = $hierarchicalCondition.'_'.$language;
                    $adjustedPrices[$key] = $currentEntry;
                    $adjustedPrices[$key]['price_cents'] = $adjustedPriceCents;
                    $adjustedPrices[$key]['source'] = $source;

                    if ($originalPriceCents !== $adjustedPriceCents) {
                        $adjustedPrices[$key]['original_price_cents'] = $originalPriceCents;
                    }

                    $lastValidPriceCents = $adjustedPriceCents;
                }
            }
        }

        return $adjustedPrices;
    }

    /**
     * Generate metadata about the pricing data for analytics and debugging.
     */
    private function generatePricingMetadata(array $apiProducts, array $calculatedPrices): array
    {
        $metadata = [
            'total_products_processed' => count($apiProducts),
            'unique_conditions_found' => [],
            'languages_found' => [],
            'price_range_by_language' => [],
            'generated_at' => Carbon::now(),
        ];

        // Extract conditions and languages from processed data
        foreach ($calculatedPrices as $key => $priceData) {
            if ($key === '_metadata') {
                continue;
            }

            $condition = $priceData['condition'];
            $language = $priceData['language'];

            if (! in_array($condition, $metadata['unique_conditions_found'])) {
                $metadata['unique_conditions_found'][] = $condition;
            }

            if (! in_array($language, $metadata['languages_found'])) {
                $metadata['languages_found'][] = $language;
            }

            // Track price ranges by language
            if (! isset($metadata['price_range_by_language'][$language])) {
                $metadata['price_range_by_language'][$language] = [
                    'min_price_cents' => $priceData['price_cents'],
                    'max_price_cents' => $priceData['price_cents'],
                ];
            } else {
                $metadata['price_range_by_language'][$language]['min_price_cents'] = min(
                    $metadata['price_range_by_language'][$language]['min_price_cents'],
                    $priceData['price_cents']
                );
                $metadata['price_range_by_language'][$language]['max_price_cents'] = max(
                    $metadata['price_range_by_language'][$language]['max_price_cents'],
                    $priceData['price_cents']
                );
            }
        }

        return $metadata;
    }

    /**
     * Get supported languages for cards.
     */
    public static function getSupportedLanguages(): array
    {
        return self::SUPPORTED_LANGUAGES;
    }

    /**
     * Helper method to get prices for a specific language.
     */
    public function getPricesForLanguage(Blueprint $blueprint, string $language): ?array
    {
        $allPrices = $this->calculatePrices($blueprint);

        if (! $allPrices) {
            return null;
        }

        $languagePrices = [];
        foreach ($allPrices as $key => $priceData) {
            if ($key === '_metadata') {
                continue;
            }

            if ($priceData['language'] === strtolower($language)) {
                $languagePrices[$priceData['condition']] = $priceData;
            }
        }

        return empty($languagePrices) ? null : $languagePrices;
    }
}
