<?php

namespace App\Observers;

use App\Models\Blueprint;
use App\Services\ElasticsearchService;
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
        $this->indexBlueprint($blueprint);
    }

    /**
     * Handle the Blueprint "updated" event.
     */
    public function updated(Blueprint $blueprint): void
    {
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
                'error' => $e->getMessage()
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
     * Index a blueprint in Elasticsearch
     */
    private function indexBlueprint(Blueprint $blueprint): void
    {
        try {
            // Load relationships
            $blueprint->load(['expansion', 'latestPrice']);

            // Transform to Elasticsearch format
            $data = [
                'id' => $blueprint->id,
                'name' => $blueprint->name,
                'image_url' => $blueprint->image_url,
                'expansion' => $blueprint->expansion ? [
                    'id' => $blueprint->expansion->id,
                    'name' => $blueprint->expansion->name,
                    'code' => $blueprint->expansion->code,
                ] : null,
                'category' => $blueprint->category ? [
                    'id' => $blueprint->category->id,
                    'name' => $blueprint->category->name,
                ] : null,
                'latest_price' => $blueprint->latestPrice ? [
                    'price_cents' => $blueprint->latestPrice->price_cents,
                    'currency' => $blueprint->latestPrice->currency,
                    'language' => strtolower($blueprint->latestPrice->language ?? 'en'),
                    'condition' => $blueprint->latestPrice->condition,
                    'is_foil' => $blueprint->latestPrice->is_foil ?? false,
                    'fetched_at' => $this->formatDateForElasticsearch($blueprint->latestPrice->fetched_at),
                ] : null,
                'properties' => $blueprint->properties,
                'created_at' => $this->formatDateForElasticsearch($blueprint->created_at),
                'updated_at' => $this->formatDateForElasticsearch($blueprint->updated_at),
            ];

            $this->elasticsearch->indexBlueprint($data);
            Log::info('Blueprint indexed in Elasticsearch', ['blueprint_id' => $blueprint->id]);
        } catch (\Exception $e) {
            Log::error('Failed to index blueprint in Elasticsearch', [
                'blueprint_id' => $blueprint->id,
                'error' => $e->getMessage()
            ]);
        }
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
