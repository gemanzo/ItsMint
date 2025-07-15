<?php

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

class ElasticsearchIndexBlueprints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:index-blueprints 
                            {--fresh : Delete existing index and create fresh}
                            {--batch-size=500 : Number of blueprints to process in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index all blueprints into Elasticsearch';

    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        parent::__construct();
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Handle fresh option
        if ($this->option('fresh') && $this->elasticsearch->indexExists()) {
            $this->warn('🗑️  Deleting existing index...');
            if (! $this->elasticsearch->deleteIndex()) {
                $this->error('❌ Failed to delete existing index');

                return Command::FAILURE;
            }
        }

        // Create index if it doesn't exist
        if (! $this->elasticsearch->indexExists()) {
            if (! $this->elasticsearch->createIndex()) {
                $this->error('❌ Failed to create Elasticsearch index');

                return Command::FAILURE;
            }
        }

        // Count total blueprints
        $totalBlueprints = Blueprint::count();

        if ($totalBlueprints === 0) {
            $this->warn('⚠️  No blueprints found to index');

            return Command::SUCCESS;
        }

        // Setup progress bar
        $batchSize = (int) $this->option('batch-size');
        $totalBatches = ceil($totalBlueprints / $batchSize);

        $progressBar = $this->output->createProgressBar($totalBlueprints);
        $progressBar->start();

        $indexedCount = 0;
        $errorCount = 0;

        // Process blueprints in batches
        Blueprint::with(['expansion', 'latestPrice', 'cardPrices'])
            ->chunk($batchSize, function ($blueprints) use (&$indexedCount, &$errorCount, $progressBar) {
                $batchData = [];

                foreach ($blueprints as $blueprint) {
                    $batchData[] = $this->transformBlueprintForIndex($blueprint);
                    $progressBar->advance();
                }

                // Bulk index this batch
                if (! empty($batchData)) {
                    if ($this->elasticsearch->bulkIndexBlueprints($batchData)) {
                        $indexedCount += count($batchData);
                    } else {
                        $errorCount += count($batchData);
                        $this->error("\n❌ Failed to index batch of " . count($batchData) . ' blueprints');
                    }
                }
            });

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Blueprints', $totalBlueprints],
                ['Successfully Indexed', $indexedCount],
                ['Errors', $errorCount],
                ['Success Rate', $totalBlueprints > 0 ? round(($indexedCount / $totalBlueprints) * 100, 2) . '%' : '0%'],
            ]
        );

        if ($errorCount > 0) {
            $this->warn("⚠️  {$errorCount} blueprints failed to index. Check logs for details.");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Transform a Blueprint model into the format expected by Elasticsearch
     */
    private function transformBlueprintForIndex(Blueprint $blueprint): array
    {
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

        return [
            'id' => $blueprint->id,
            'name' => $blueprint->name,
            'image_url' => $blueprint->image_url,
            'collector_number' => $blueprint->collector_number,
            'search_text' => $this->buildSearchText($blueprint),
            'expansion' => $blueprint->expansion ? [
                'id' => $blueprint->expansion->id,
                'name' => $blueprint->expansion->name,
                'code' => $blueprint->expansion->code,
                'normalized_name' => $this->normalizeExpansionName($blueprint->expansion->name),
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
    }

    /**
     * Format date for Elasticsearch compatibility
     */
    private function formatDateForElasticsearch($date): string
    {
        if (is_string($date)) {
            // Parse string date and convert to ISO 8601
            return \Carbon\Carbon::parse($date)->toISOString();
        }

        if ($date instanceof \Carbon\Carbon) {
            return $date->toISOString();
        }

        // Fallback for other DateTime instances
        return \Carbon\Carbon::instance($date)->toISOString();
    }

    private function normalizeExpansionName(string $name): string
    {
        return strtolower(
            preg_replace('/[\s\-:\.]/', '', $name)
        );
    }

    /**
     * Build the search_text field for better search matching.
     * Combines name and collector_number for unified search.
     */
    private function buildSearchText(Blueprint $blueprint): string
    {
        $searchText = $blueprint->name;

        if ($blueprint->collector_number) {
            $searchText .= ' ' . $blueprint->collector_number;
        }

        // Add expansion code if available for broader matching
        if ($blueprint->expansion && $blueprint->expansion->code) {
            $searchText .= ' ' . $blueprint->expansion->code;
        }

        return trim($searchText);
    }
}
