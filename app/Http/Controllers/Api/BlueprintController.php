<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Models\CardPrice; // Added this import
use App\Services\BlueprintPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BlueprintController extends Controller
{
    /**
     * Get the price history for a specific blueprint, aggregating all language variants.
     */
    public function priceHistory(Blueprint $blueprint, Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'required|string',
            'period' => 'nullable|string|in:1D,1W,1M,3M,1Y,MAX',
            'condition' => 'nullable|string',
        ]);

        $language = $request->input('language');
        $period = $request->input('period', '1D'); // Default to 1 day
        $condition = $request->input('condition', 'Near Mint'); // Default to Near Mint but allow other conditions

        $query = CardPrice::where('blueprint_id', $blueprint->id)
            ->where('language', $language)
            ->where('condition', $condition)
            ->orderBy('fetched_at', 'asc');

        if ($period !== 'MAX') {
            $date = match ($period) {
                '1D' => now()->subDay(),
                '1W' => now()->subWeek(),
                '1M' => now()->subMonth(),
                '3M' => now()->subMonths(3),
                '1Y' => now()->subYear(),
            };
            $query->where('fetched_at', '>=', $date);
        }

        $priceHistory = $query->get(['price_cents', 'fetched_at']);

        return response()->json($priceHistory);
    }

    /**
     * Get current prices grouped by language for a blueprint.
     */
    public function currentPricesByLanguage(Blueprint $blueprint): JsonResponse
    {
        $pricesByLanguage = [];
        $supportedLanguages = BlueprintPriceService::getSupportedLanguages();

        foreach ($supportedLanguages as $langCode => $langName) {
            $prices = $blueprint->cardPrices()
                ->where('language', $langCode)
                ->orderBy('fetched_at', 'desc')
                ->orderBy('condition')
                ->get()
                ->groupBy('condition')
                ->map(function ($conditionPrices) {
                    return $conditionPrices->first(); // Get latest for each condition
                });

            if ($prices->isNotEmpty()) {
                $pricesByLanguage[$langCode] = [
                    'language_code' => $langCode,
                    'language_name' => $langName,
                    'prices' => $prices->values(),
                    'last_updated' => $prices->max('fetched_at'),
                ];
            }
        }

        return response()->json([
            'data' => $pricesByLanguage,
            'meta' => [
                'blueprint_id' => $blueprint->id,
                'blueprint_name' => $blueprint->name,
                'languages_available' => count($pricesByLanguage),
                'supported_languages' => $supportedLanguages,
            ],
        ]);
    }

    /**
     * Get cheapest prices across all languages for each condition.
     */
    public function cheapestPrices(Blueprint $blueprint): JsonResponse
    {
        $conditions = ['Near Mint', 'Slightly Played', 'Moderately Played', 'Played', 'Poor'];
        $cheapestPrices = [];

        foreach ($conditions as $condition) {
            $cheapest = $blueprint->cardPrices()
                ->where('condition', $condition)
                ->orderBy('price_cents', 'asc')
                ->orderBy('fetched_at', 'desc')
                ->first();

            if ($cheapest) {
                $cheapestPrices[$condition] = $cheapest;
            }
        }

        return response()->json([
            'data' => $cheapestPrices,
            'meta' => [
                'blueprint_id' => $blueprint->id,
                'blueprint_name' => $blueprint->name,
                'conditions_found' => count($cheapestPrices),
            ],
        ]);
    }

    /**
     * Get price statistics for a blueprint.
     */
    public function priceStatistics(Blueprint $blueprint): JsonResponse
    {
        $stats = [
            'total_price_records' => $blueprint->cardPrices()->count(),
            'languages_available' => $blueprint->cardPrices()->distinct('language')->count('language'),
            'conditions_available' => $blueprint->cardPrices()->distinct('condition')->count('condition'),
            'price_range' => [
                'min_price_cents' => $blueprint->cardPrices()->min('price_cents'),
                'max_price_cents' => $blueprint->cardPrices()->max('price_cents'),
            ],
            'last_updated' => $blueprint->cardPrices()->max('fetched_at'),
            'by_language' => [],
        ];

        // Language-specific stats
        $languageStats = $blueprint->cardPrices()
            ->selectRaw('language, COUNT(*) as record_count, MIN(price_cents) as min_price, MAX(price_cents) as max_price, MAX(fetched_at) as last_updated')
            ->groupBy('language')
            ->get();

        $supportedLanguages = BlueprintPriceService::getSupportedLanguages();

        foreach ($languageStats as $langStat) {
            $stats['by_language'][$langStat->language] = [
                'language_name' => $supportedLanguages[$langStat->language] ?? ucfirst($langStat->language),
                'record_count' => $langStat->record_count,
                'price_range' => [
                    'min_price_cents' => $langStat->min_price,
                    'max_price_cents' => $langStat->max_price,
                ],
                'last_updated' => $langStat->last_updated,
            ];
        }

        return response()->json([
            'data' => $stats,
            'meta' => [
                'blueprint_id' => $blueprint->id,
                'blueprint_name' => $blueprint->name,
            ],
        ]);
    }

    /**
     * Get all language variants for a blueprint.
     */
    public function languageVariants(Blueprint $blueprint): JsonResponse
    {
        $variants = $blueprint->allLanguageVersions()
            ->with(['cardPrices' => function ($query) {
                $query->orderBy('fetched_at', 'desc')
                    ->orderBy('condition');
            }])
            ->get();

        $variantData = [];
        $supportedLanguages = BlueprintPriceService::getSupportedLanguages();

        foreach ($variants as $variant) {
            $language = $variant->primary_language ?? 'unknown';

            $variantData[] = [
                'id' => $variant->id,
                'ct_id' => $variant->ct_id,
                'name' => $variant->name,
                'language' => $language,
                'language_name' => $supportedLanguages[$language] ?? ucfirst($language),
                'image_url' => $variant->image_url,
                'collector_number' => $variant->collector_number,
                'expansion_id' => $variant->expansion_id,
                'price_summary' => $this->getPriceSummaryForBlueprint($variant),
                'has_prices' => $variant->cardPrices->isNotEmpty(),
                'last_price_update' => $variant->cardPrices->max('fetched_at'),
            ];
        }

        return response()->json([
            'data' => $variantData,
            'meta' => [
                'base_blueprint_id' => $blueprint->id,
                'base_blueprint_name' => $blueprint->name,
                'total_variants' => count($variantData),
                'languages_available' => collect($variantData)->pluck('language')->unique()->count(),
            ],
        ]);
    }

    /**
     * Get aggregated prices from all language variants.
     */
    public function aggregatedLanguagePrices(Blueprint $blueprint): JsonResponse
    {
        $allLanguagesPrices = $blueprint->getAllLanguagesPrices();
        $aggregatedData = [];
        $supportedLanguages = BlueprintPriceService::getSupportedLanguages();

        foreach ($allLanguagesPrices as $language => $data) {
            $prices = collect($data['prices']);

            if ($prices->isNotEmpty()) {
                $byCondition = $prices->groupBy('condition')->map(function ($conditionPrices) {
                    $latest = $conditionPrices->sortByDesc('fetched_at')->first();

                    return [
                        'price_cents' => $latest->price_cents,
                        'currency' => $latest->currency,
                        'fetched_at' => $latest->fetched_at,
                        'source' => $latest->source,
                    ];
                });

                $aggregatedData[$language] = [
                    'blueprint_id' => $data['blueprint_id'],
                    'ct_id' => $data['ct_id'],
                    'language' => $language,
                    'language_name' => $supportedLanguages[$language] ?? ucfirst($language),
                    'prices_by_condition' => $byCondition,
                    'cheapest_price' => $prices->min('price_cents'),
                    'most_expensive_price' => $prices->max('price_cents'),
                    'last_updated' => $prices->max('fetched_at'),
                    'total_price_records' => $prices->count(),
                ];
            }
        }

        return response()->json([
            'data' => $aggregatedData,
            'meta' => [
                'base_blueprint_id' => $blueprint->id,
                'base_blueprint_name' => $blueprint->name,
                'languages_with_prices' => count($aggregatedData),
                'total_variants' => $blueprint->allLanguageVersions()->count(),
            ],
        ]);
    }

    /**
     * Get market statistics for a blueprint.
     */
    public function marketStatistics(Blueprint $blueprint, Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'nullable|string',
            'condition' => 'nullable|string',
        ]);

        $language = $request->input('language', 'en');
        $condition = $request->input('condition', 'Near Mint');

        // 1. Market Cap = Average Price × Unique Owners
        $avgPrice = $blueprint->cardPrices()
            ->where('language', $language)
            ->where('condition', $condition)
            ->avg('price_cents') ?? 0;

        $uniqueOwners = $blueprint->userCards()
            ->distinct('user_id')
            ->count();

        $marketCapCents = $avgPrice * $uniqueOwners;

        // 2. Average Price (30d)
        $avgPrice30d = $blueprint->cardPrices()
            ->where('language', $language)
            ->where('condition', $condition)
            ->where('fetched_at', '>=', now()->subDays(30))
            ->avg('price_cents') ?? 0;

        // 3. Popularity Rank
        $totalUserCards = $blueprint->userCards()->count();
        $popularityRank = Blueprint::query()
            ->join('user_cards', 'blueprints.id', '=', 'user_cards.blueprint_id')
            ->select('blueprints.id')
            ->groupBy('blueprints.id')
            ->havingRaw('COUNT(user_cards.id) > ?', [$totalUserCards])
            ->count() + 1;

        // 4. Price Change (7d)
        $currentPrice = $blueprint->cardPrices()
            ->where('language', $language)
            ->where('condition', $condition)
            ->latest('fetched_at')
            ->first();

        $weekAgoPrice = $blueprint->cardPrices()
            ->where('language', $language)
            ->where('condition', $condition)
            ->where('fetched_at', '<=', now()->subWeek())
            ->latest('fetched_at')
            ->first();

        $priceChangePercentage = null;
        if ($currentPrice && $weekAgoPrice && $weekAgoPrice->price_cents > 0) {
            $priceChangePercentage = (($currentPrice->price_cents - $weekAgoPrice->price_cents) / $weekAgoPrice->price_cents) * 100;
        }

        return response()->json([
            'data' => [
                'market_cap_cents' => $marketCapCents,
                'market_cap_formatted' => $this->formatMarketCap($marketCapCents),
                'avg_price_30d_cents' => $avgPrice30d,
                'avg_price_30d_formatted' => '€' . number_format($avgPrice30d / 100, 2),
                'popularity_rank' => $popularityRank,
                'price_change_7d_percentage' => $priceChangePercentage,
                'price_change_7d_formatted' => $priceChangePercentage !== null ?
                    ($priceChangePercentage >= 0 ? '+' : '') . number_format($priceChangePercentage, 1) . '%' : 'N/A',
            ],
            'meta' => [
                'blueprint_id' => $blueprint->id,
                'language' => $language,
                'condition' => $condition,
                'unique_owners' => $uniqueOwners,
                'total_user_cards' => $totalUserCards,
                'calculated_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Format market cap for display
     */
    private function formatMarketCap(float $cents): string
    {
        $value = $cents / 100; // Convert to euros

        if ($value >= 1000000) {
            return '€' . number_format($value / 1000000, 1) . 'M';
        } elseif ($value >= 1000) {
            return '€' . number_format($value / 1000, 1) . 'K';
        } else {
            return '€' . number_format($value, 2);
        }
    }

    /**
     * Get price summary for a single blueprint.
     */
    private function getPriceSummaryForBlueprint(Blueprint $blueprint): array
    {
        if ($blueprint->cardPrices->isEmpty()) {
            return [];
        }

        $latest = $blueprint->cardPrices->sortByDesc('fetched_at')->first();
        $cheapest = $blueprint->cardPrices->sortBy('price_cents')->first();

        return [
            'latest_price' => [
                'price_cents' => $latest->price_cents,
                'currency' => $latest->currency,
                'condition' => $latest->condition,
                'fetched_at' => $latest->fetched_at,
            ],
            'cheapest_price' => [
                'price_cents' => $cheapest->price_cents,
                'currency' => $cheapest->currency,
                'condition' => $cheapest->condition,
                'fetched_at' => $cheapest->fetched_at,
            ],
            'price_range' => [
                'min_cents' => $blueprint->cardPrices->min('price_cents'),
                'max_cents' => $blueprint->cardPrices->max('price_cents'),
            ],
        ];
    }
}
