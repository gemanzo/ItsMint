<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Blueprint;
use App\Services\BlueprintPriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BlueprintController extends Controller
{
    public function priceHistory(Blueprint $blueprint, Request $request): JsonResponse
    {
        $request->validate([
            'language' => 'nullable|string|size:2',
            'condition' => 'nullable|string|in:Near Mint,Slightly Played,Moderately Played,Played,Poor',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = $blueprint->cardPrices()->orderBy('fetched_at', 'asc');

        // Filter by language if provided
        if ($request->filled('language')) {
            $query->where('language', strtolower($request->language));
        }

        // Filter by condition if provided
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        // Apply limit if provided
        if ($request->filled('limit')) {
            $query->limit($request->limit);
        }

        $priceHistory = $query->get();

        return response()->json([
            'data' => $priceHistory,
            'meta' => [
                'total_records' => $priceHistory->count(),
                'language_filter' => $request->language,
                'condition_filter' => $request->condition,
            ],
        ]);
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
}
