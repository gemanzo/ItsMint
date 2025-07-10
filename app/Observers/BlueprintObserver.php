<?php

namespace App\Observers;

use App\Models\Blueprint;
use App\Services\ElasticsearchService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class BlueprintObserver
{
    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Handle the Blueprint "created" event.
     */
    public function created(Blueprint $blueprint): void
    {
        $this->updateOcrMatchString($blueprint);
        $this->indexBlueprint($blueprint);
    }

    /**
     * Handle the Blueprint "updated" event.
     */
    public function updated(Blueprint $blueprint): void
    {
        $this->updateOcrMatchString($blueprint);
        $this->indexBlueprint($blueprint);
    }

    /**
     * Handle the Blueprint "deleted" event.
     */
    public function deleted(Blueprint $blueprint): void
    {
        try {
            $this->elasticsearch->deleteBlueprint($blueprint->id);
            Log::info('Blueprint deleted from Elasticsearch', ['blueprint_id' => $blueprint->id]);
        } catch (\Exception $e) {
            Log::error('Failed to delete blueprint from Elasticsearch', [
                'blueprint_id' => $blueprint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Blueprint "restored" event.
     */
    public function restored(Blueprint $blueprint): void
    {
        //
    }

    /**
     * Handle the Blueprint "force deleted" event.
     */
    public function forceDeleted(Blueprint $blueprint): void
    {
        //
    }

    /**
     * Update OCR match string for a blueprint using Artisan command
     */
    private function updateOcrMatchString(Blueprint $blueprint): void
    {
        try {
            Artisan::call('itsmint:generate-blueprint-ocr-match-string', [
                'blueprint_id' => $blueprint->id,
                '--force' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update OCR match string via command', [
                'blueprint_id' => $blueprint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Index a blueprint in Elasticsearch
     */
    private function indexBlueprint(Blueprint $blueprint): void
    {
        try {
            // Load relationships including all card prices for language analysis
            $blueprint->load(['expansion', 'latestPrice', 'cardPrices']);

            // Calculate price statistics by language
            $pricesByLanguage = [];
            $overallPriceStats = [
                'min_price_cents' => null,
                'max_price_cents' => null,
                'avg_price_cents' => null,
                'languages_available' => [],
            ];

            if ($blueprint->cardPrices->isNotEmpty()) {
                $groupedPrices = $blueprint->cardPrices->groupBy('language');

                foreach ($groupedPrices as $language => $prices) {
                    $languagePrices = $prices->pluck('price_cents')->filter();
                    if ($languagePrices->isNotEmpty()) {
                        $pricesByLanguage[$language] = [
                            'min_price_cents' => $languagePrices->min(),
                            'max_price_cents' => $languagePrices->max(),
                            'avg_price_cents' => round($languagePrices->avg()),
                            'record_count' => $languagePrices->count(),
                            'latest_update' => $this->formatDateForElasticsearch(
                                $prices->max('fetched_at')
                            ),
                        ];
                        $overallPriceStats['languages_available'][] = $language;
                    }
                }

                // Overall statistics across all languages
                $allPrices = $blueprint->cardPrices->pluck('price_cents')->filter();
                if ($allPrices->isNotEmpty()) {
                    $overallPriceStats['min_price_cents'] = $allPrices->min();
                    $overallPriceStats['max_price_cents'] = $allPrices->max();
                    $overallPriceStats['avg_price_cents'] = round($allPrices->avg());
                }
            }

            // Transform to Elasticsearch format
            $data = [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'image_url' => $blueprint->image_url,
                'collector_number' => $blueprint->collector_number,
                'search_text' => $this->buildSearchText($blueprint),
                'expansion' => $blueprint->expansion ? [
                    'id' => $blueprint->expansion->id,
                    'name' => $blueprint->expansion->name,
                    'code' => $blueprint->expansion->code,
                ] : null,
                'latest_price' => $blueprint->latestPrice ? [
                    'price_cents' => $blueprint->latestPrice->price_cents,
                    'currency' => $blueprint->latestPrice->currency,
                    'language' => strtolower($blueprint->latestPrice->language ?? 'en'),
                    'condition' => $blueprint->latestPrice->condition,
                    'is_foil' => $blueprint->latestPrice->is_foil ?? false,
                    'fetched_at' => $this->formatDateForElasticsearch($blueprint->latestPrice->fetched_at),
                ] : null,
                'price_statistics' => $overallPriceStats,
                'prices_by_language' => $pricesByLanguage,
                'properties' => $blueprint->properties,
                'created_at' => $this->formatDateForElasticsearch($blueprint->created_at),
                'updated_at' => $this->formatDateForElasticsearch($blueprint->updated_at),
            ];

            $this->elasticsearch->indexBlueprint($data);
            Log::info('Blueprint indexed in Elasticsearch', [
                'blueprint_id' => $blueprint->id,
                'languages_indexed' => count($pricesByLanguage),
                'total_price_records' => $blueprint->cardPrices->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to index blueprint in Elasticsearch', [
                'blueprint_id' => $blueprint->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build search text for Elasticsearch indexing
     */
    private function buildSearchText(Blueprint $blueprint): string
    {
        $searchParts = [
            $blueprint->name,
            $blueprint->collector_number,
            $blueprint->expansion?->name,
            $blueprint->expansion?->code,
        ];

        // Include OneLog card data if available
        $primaryOneLogCard = $blueprint->getPrimaryOneLogCardAttribute();
        if ($primaryOneLogCard) {
            $searchParts = array_merge($searchParts, [
                $primaryOneLogCard->serial,
                $primaryOneLogCard->type,
                $primaryOneLogCard->attribute,
                $primaryOneLogCard->feature,
                $primaryOneLogCard->effect,
                is_array($primaryOneLogCard->colors) ? implode(' ', $primaryOneLogCard->colors) : '',
            ]);
        }

        // Include OCR match string if available
        if ($blueprint->ocr_match_string) {
            $searchParts[] = $blueprint->ocr_match_string;
        }

        return implode(' ', array_filter($searchParts));
    }

    /**
     * Format date for Elasticsearch compatibility
     */
    private function formatDateForElasticsearch($date): string
    {
        if (is_string($date)) {
            return \Carbon\Carbon::parse($date)->toISOString();
        }

        if ($date instanceof \Carbon\Carbon) {
            return $date->toISOString();
        }

        return \Carbon\Carbon::instance($date)->toISOString();
    }
}
