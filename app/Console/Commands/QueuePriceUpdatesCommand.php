<?php

namespace App\Console\Commands;

use App\Jobs\UpdateBlueprintPriceJob;
use App\Models\Blueprint;
use App\Services\BlueprintPriceService;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class QueuePriceUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:queue-price-updates 
                            {--blueprint_ct_id= : Queue update for a specific blueprint ID} 
                            {--game_id= : Filter by game ID} 
                            {--expansion_id= : Filter by expansion ID} 
                            {--batch_size=100 : Number of jobs per batch}
                            {--clear-cache : Clear old pricing caches before running}
                            {--test-service : Test the pricing service with a sample blueprint}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queues jobs to update prices for blueprints with language support. Can target all (in multiple batches), or a specific blueprint. Includes cache management and testing options.';

    protected BlueprintPriceService $blueprintPriceService;

    /**
     * Create a new command instance.
     */
    public function __construct(BlueprintPriceService $blueprintPriceService)
    {
        parent::__construct();
        $this->blueprintPriceService = $blueprintPriceService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Clear old caches if requested
        if ($this->option('clear-cache')) {
            $this->clearOldPricingCaches();
        }

        // Test the service if requested
        if ($this->option('test-service')) {
            return $this->testPricingService();
        }

        $blueprintCtIdOpt = $this->option('blueprint_ct_id');
        $gameIdOpt = $this->option('game_id');
        $expansionIdOpt = $this->option('expansion_id');
        $configuredBatchSize = (int) $this->option('batch_size');

        if ($blueprintCtIdOpt) {
            $blueprint = Blueprint::where('ct_id', $blueprintCtIdOpt)->first();
            if ($blueprint) {
                UpdateBlueprintPriceJob::dispatch($blueprint, $this->blueprintPriceService)->onQueue('price-updates');
            } else {
                $this->error("Blueprint with CardTrader ID: {$blueprintCtIdOpt} not found.");

                return Command::FAILURE;
            }
        } else {
            $query = Blueprint::query();

            if ($gameIdOpt) {
                $query->where('game_id', $gameIdOpt);
            }

            if ($expansionIdOpt) {
                $query->where('expansion_id', $expansionIdOpt);
            }

            $jobsForCurrentBatch = [];
            $totalBlueprintsProcessed = 0;
            $dispatchedBatchCount = 0;

            $query->orderBy('id')->chunkById($configuredBatchSize, function ($blueprintsChunk) use (&$jobsForCurrentBatch, &$totalBlueprintsProcessed, &$dispatchedBatchCount, $configuredBatchSize) {
                foreach ($blueprintsChunk as $blueprint) {
                    $jobsForCurrentBatch[] = new UpdateBlueprintPriceJob($blueprint, $this->blueprintPriceService);
                    $totalBlueprintsProcessed++;

                    if (count($jobsForCurrentBatch) >= $configuredBatchSize) {
                        $this->dispatchBatch($jobsForCurrentBatch, 'price-updates');
                        $dispatchedBatchCount++;
                        $jobsForCurrentBatch = [];
                    }
                }
            });

            if (! empty($jobsForCurrentBatch)) {
                $this->dispatchBatch($jobsForCurrentBatch, 'price-updates');
                $dispatchedBatchCount++;
            }

            if ($dispatchedBatchCount === 0) {
                $this->warn('No blueprints found to queue for price updates.');
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Helper method to dispatch a batch of jobs.
     */
    private function dispatchBatch(array $jobs, string $queueName): void
    {
        if (empty($jobs)) {
            return;
        }

        Bus::batch($jobs)
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error('Blueprint price update SUB-BATCH failed one or more jobs.', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                    'failed_jobs_count' => $batch->failedJobs,
                    'first_failed_job_id' => $batch->failedJobIds[0] ?? 'N/A',
                ]);
                echo "Sub-Batch [{$batch->id}] FAILED. Error: {$e->getMessage()}. Failed jobs: {$batch->failedJobs}".PHP_EOL;
            })
            ->name('Update Blueprint Prices Batch - Part - '.now()->toDateTimeString().'-'.Str::random(4))
            ->onQueue($queueName)
            ->dispatch();
    }

    /**
     * Clear old pricing caches that used the v1 structure.
     */
    private function clearOldPricingCaches(): void
    {
        $this->info('🧹 Clearing old pricing caches...');

        // Get all blueprints to clear their old cache keys
        $blueprintIds = Blueprint::pluck('id');
        $clearedCount = 0;

        foreach ($blueprintIds as $blueprintId) {
            // Clear old v1 cache keys
            if (Cache::forget("blueprint_price_{$blueprintId}")) {
                $clearedCount++;
            }
        }

        $this->info("✅ Cleared {$clearedCount} old pricing cache entries");
        $this->info('💡 New pricing data will use language-based cache keys (blueprint_price_v2_*)');
    }

    /**
     * Test the pricing service with a sample blueprint.
     */
    private function testPricingService(): int
    {
        $this->info('🧪 Testing the new language-based pricing service...');

        // Find a blueprint with recent price data
        $testBlueprint = Blueprint::whereHas('cardPrices', function ($query) {
            $query->where('fetched_at', '>', now()->subDays(7));
        })->with(['cardPrices', 'expansion'])->first();

        if (! $testBlueprint) {
            $this->warn('⚠️  No blueprint with recent price data found for testing');
            $this->info('💡 Run price updates first: php artisan itsmint:queue-price-updates --blueprint_ct_id=<some_ct_id>');

            return Command::SUCCESS;
        }

        $this->info("📋 Testing with Blueprint: {$testBlueprint->name} (ID: {$testBlueprint->id})");
        $expansionName = $testBlueprint->expansion->name ?? 'Unknown';
        $this->info("🎮 Expansion: {$expansionName}");

        // Test the service
        $startTime = microtime(true);
        $prices = $this->blueprintPriceService->calculatePrices($testBlueprint);
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        if (! $prices) {
            $this->error('❌ Service returned null - check logs for details');

            return Command::FAILURE;
        }

        $this->info("⚡ Service execution time: {$executionTime}ms");
        $this->newLine();

        // Display results
        $priceCount = count($prices) - (isset($prices['_metadata']) ? 1 : 0);
        $this->info("📊 Found {$priceCount} price entries:");

        foreach ($prices as $key => $priceData) {
            if ($key === '_metadata') {
                continue;
            }

            $priceEuros = number_format($priceData['price_cents'] / 100, 2);
            $this->line("   • {$priceData['condition']} ({$priceData['language_name']}): €{$priceEuros}");

            if (isset($priceData['original_price_cents'])) {
                $originalEuros = number_format($priceData['original_price_cents'] / 100, 2);
                $this->line("     └─ Original: €{$originalEuros} (adjusted by hierarchy)");
            }
        }

        // Display metadata if available
        if (isset($prices['_metadata'])) {
            $metadata = $prices['_metadata'];
            $this->newLine();
            $this->info('📈 Metadata:');
            $this->line("   • Total products processed: {$metadata['total_products_processed']}");
            $this->line('   • Languages found: '.implode(', ', $metadata['languages_found']));
            $this->line('   • Conditions found: '.implode(', ', $metadata['unique_conditions_found']));

            if (! empty($metadata['price_range_by_language'])) {
                $this->line('   • Price ranges by language:');
                foreach ($metadata['price_range_by_language'] as $lang => $range) {
                    $minEuros = number_format($range['min_price_cents'] / 100, 2);
                    $maxEuros = number_format($range['max_price_cents'] / 100, 2);
                    $supportedLanguages = $this->blueprintPriceService::getSupportedLanguages();
                    $langName = $supportedLanguages[$lang] ?? ucfirst($lang);
                    $this->line("     └─ {$langName}: €{$minEuros} - €{$maxEuros}");
                }
            }
        }

        $this->newLine();
        $this->info('✅ Language-based pricing service test completed successfully!');

        return Command::SUCCESS;
    }
}
