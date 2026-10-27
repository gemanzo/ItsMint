<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CardLanguage;
use App\Exceptions\CardTraderApiException;
use App\Models\Blueprint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BlueprintPriceService
{
    private const CONDITION_HIERARCHY = [
        'Near Mint',
        'Slightly Played',
        'Moderately Played',
        'Played',
        'Poor',
    ];

    /**
     * Total attempts of a marketplace request (first try included).
     */
    private const MAX_REQUEST_ATTEMPTS = 3;

    /**
     * Longest wait between two attempts: the whole request must stay well within
     * the timeout of the price update job.
     */
    private const MAX_RETRY_DELAY_MILLISECONDS = 10_000;

    /**
     * Calculate and adjust blueprint prices based on CardTrader marketplace data,
     * organized by condition and language.
     *
     * Returns an empty array when the card has no usable listing.
     *
     * @return array<string, array<string, mixed>>
     *
     * @throws CardTraderApiException when CardTrader can't provide the data: the caller
     *                                must not mistake an API failure for "no prices"
     */
    public function calculatePrices(Blueprint $blueprint): array
    {
        $token = config('services.cardtrader.api_token');
        $baseApiUrl = config('services.cardtrader.api_base_url');
        $blueprintCtId = $blueprint->ct_id;

        if (empty($token) || empty($baseApiUrl)) {
            throw CardTraderApiException::notConfigured();
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(
                    self::MAX_REQUEST_ATTEMPTS,
                    fn (int $attempt, Throwable $exception) => $this->retryDelayMilliseconds($attempt, $exception),
                    // Only transient failures are retried: an authentication or a "not found"
                    // error would fail again
                    fn (Throwable $exception) => $this->isTransientFailure($exception),
                )
                ->get($baseApiUrl.'/marketplace/products', [
                    'blueprint_id' => $blueprintCtId,
                ]);
        } catch (RequestException|ConnectionException $exception) {
            throw CardTraderApiException::requestFailed($blueprint, $exception);
        }

        $apiProducts = $response->json()[$blueprintCtId] ?? [];

        if (empty($apiProducts)) {
            return [];
        }

        $lowestPricesByConditionAndLanguage = $this->extractLowestPricesByConditionAndLanguage($apiProducts);

        if (empty($lowestPricesByConditionAndLanguage)) {
            return [];
        }

        $calculatedPrices = $this->applyHierarchyLogicByLanguage($lowestPricesByConditionAndLanguage);

        // Add summary statistics
        $calculatedPrices['_metadata'] = $this->generatePricingMetadata($apiProducts, $calculatedPrices);

        return $calculatedPrices;
    }

    /**
     * Whether a failed marketplace request is worth retrying.
     */
    private function isTransientFailure(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $statusCode = $exception->response->status();

            return $statusCode === 429 || $statusCode >= 500;
        }

        return false;
    }

    /**
     * Wait before the next attempt: what CardTrader asks for when rate limited
     * (Retry-After header), otherwise an exponential backoff.
     */
    private function retryDelayMilliseconds(int $attempt, Throwable $exception): int
    {
        $retryAfterSeconds = null;
        if ($exception instanceof RequestException) {
            $retryAfterSeconds = $exception->response->header('Retry-After');
        }

        if (is_numeric($retryAfterSeconds)) {
            $requestedDelayMilliseconds = (int) $retryAfterSeconds * 1000;
        } else {
            $requestedDelayMilliseconds = (2 ** $attempt) * 500;
        }

        $delayMilliseconds = min($requestedDelayMilliseconds, self::MAX_RETRY_DELAY_MILLISECONDS);

        Log::warning('CardTrader API request failed, retrying', [
            'attempt' => $attempt,
            'delay_milliseconds' => $delayMilliseconds,
            'error' => $exception->getMessage(),
        ]);

        return $delayMilliseconds;
    }

    /**
     * Extracts the lowest price for each condition-language combination from the API products.
     *
     * Only non-foil EUR listings are considered: every stored price is a non-foil price
     * (is_foil = false), so foil listings must not be mixed in.
     *
     * @return array<string, array<string, mixed>>
     */
    private function extractLowestPricesByConditionAndLanguage(array $apiProducts): array
    {
        $lowestPrices = [];
        $unknownLanguagesListingsCount = [];
        // TODO: Implement a proper currency conversion system. For now, we only process EUR.
        $targetCurrency = 'EUR';

        foreach ($apiProducts as $product) {
            if (($product['price_currency'] ?? null) !== $targetCurrency) {
                continue;
            }

            $properties = $product['properties_hash'] ?? $product['properties'] ?? [];
            $condition = $properties['condition'] ?? null;
            if (! $condition || ! in_array($condition, self::CONDITION_HIERARCHY)) {
                continue;
            }

            $rawFoil = $properties['onepiece_foil'] ?? $properties['foil'] ?? null;
            $isFoil = filter_var($rawFoil, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
            if ($isFoil) {
                continue;
            }

            $rawLanguage = $properties['onepiece_language'] ?? $properties['language'] ?? null;
            $language = CardLanguage::fromProviderValue($rawLanguage);
            if ($language === null) {
                $unknownLanguageKey = (string) ($rawLanguage ?? 'missing');
                $unknownLanguagesListingsCount[$unknownLanguageKey] = ($unknownLanguagesListingsCount[$unknownLanguageKey] ?? 0) + 1;

                continue;
            }

            $priceCents = (int) $product['price_cents'];
            $conditionLanguageKey = $condition.'_'.$language->value;

            if (! isset($lowestPrices[$conditionLanguageKey])) {
                $lowestPrices[$conditionLanguageKey] = [
                    'price_cents' => $priceCents,
                    'currency' => $targetCurrency,
                    'condition' => $condition,
                    'language' => $language->value,
                    'language_name' => $language->displayName(),
                    'is_foil' => false,
                    'fetched_at' => Carbon::now(),
                    'source' => 'cardtrader_service_by_condition_and_language',
                    'product_count' => 0,
                ];
            }

            // Number of listings for this condition-language combination
            $lowestPrices[$conditionLanguageKey]['product_count']++;

            if ($priceCents < $lowestPrices[$conditionLanguageKey]['price_cents']) {
                $lowestPrices[$conditionLanguageKey]['price_cents'] = $priceCents;
            }
        }

        // Logged once per fetch (not once per listing) to keep the logs readable
        if (! empty($unknownLanguagesListingsCount)) {
            Log::info('CardTrader listings skipped: unsupported or missing language', [
                'listings_count_by_raw_language' => $unknownLanguagesListingsCount,
            ]);
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
        return CardLanguage::displayNamesByCode();
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
