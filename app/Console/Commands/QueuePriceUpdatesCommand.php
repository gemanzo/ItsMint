<?php

namespace App\Console\Commands;

use App\Models\Blueprint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Jobs\UpdateBlueprintPriceJob;
use App\Services\BlueprintPriceService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Bus\Batch;
use Throwable;
use Illuminate\Support\Str;

class QueuePriceUpdatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'itsmint:queue-price-updates {--blueprint_ct_id= : Queue update for a specific blueprint ID} {--batch_size=100 : Number of jobs per batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Queues jobs to update prices for blueprints. Can target all (in multiple batches), or a specific blueprint.';

    protected BlueprintPriceService $blueprintPriceService;

    /**
     * Create a new command instance.
     *
     * @param BlueprintPriceService $blueprintPriceService
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
        $blueprintCtIdOpt = $this->option('blueprint_ct_id');
        $configuredBatchSize = (int) $this->option('batch_size');

        if ($blueprintCtIdOpt) {
            $blueprint = Blueprint::where('ct_id', $blueprintCtIdOpt)->first();
            if ($blueprint) {
                UpdateBlueprintPriceJob::dispatch($blueprint, $this->blueprintPriceService)->onQueue('price-updates');
                $this->info("Single price update queued for Blueprint CT_ID: {$blueprintCtIdOpt} (Name: {$blueprint->name})");
            } else {
                $this->error("Blueprint with CardTrader ID: {$blueprintCtIdOpt} not found.");
                return Command::FAILURE;
            }
        } else {
            $this->info("Preparing to queue price updates for all blueprints in batches of {$configuredBatchSize} jobs...");

            $jobsForCurrentBatch = [];
            $totalBlueprintsProcessed = 0;
            $dispatchedBatchCount = 0;

            Blueprint::orderBy('id')->chunkById($configuredBatchSize, function ($blueprintsChunk) use (&$jobsForCurrentBatch, &$totalBlueprintsProcessed, &$dispatchedBatchCount, $configuredBatchSize) {
                foreach ($blueprintsChunk as $blueprint) {
                    $jobsForCurrentBatch[] = new UpdateBlueprintPriceJob($blueprint, $this->blueprintPriceService);
                    $totalBlueprintsProcessed++;

                    if (count($jobsForCurrentBatch) >= $configuredBatchSize) {
                        $this->dispatchBatch($jobsForCurrentBatch, 'price-updates');
                        $dispatchedBatchCount++;
                        $this->info("Dispatched batch #{$dispatchedBatchCount} with " . count($jobsForCurrentBatch) . " jobs.");
                        $jobsForCurrentBatch = [];
                    }
                }
            });

            if (!empty($jobsForCurrentBatch)) {
                $this->dispatchBatch($jobsForCurrentBatch, 'price-updates');
                $dispatchedBatchCount++;
                $this->info("Dispatched final batch #{$dispatchedBatchCount} with " . count($jobsForCurrentBatch) . " jobs.");
            }

            if ($dispatchedBatchCount > 0) {
                $this->info("Total of {$dispatchedBatchCount} batches dispatched, encompassing {$totalBlueprintsProcessed} blueprint update jobs.");
            } else {
                $this->info('No blueprints found to queue for price updates.');
            }
        }

        Log::info('QueuePriceUpdatesCommand finished.');
        return Command::SUCCESS;
    }

    /**
     * Helper method to dispatch a batch of jobs.
     */
    private function dispatchBatch(array $jobs, string $queueName): void
    {
        if (empty($jobs)) return;

        Bus::batch($jobs)
            ->then(function (Batch $batch) {
                Log::info('Blueprint price update batch PARTIALLY completed (a sub-batch). ID: ' . $batch->id, ['total_jobs' => $batch->totalJobs]);
                echo "Sub-Batch [{$batch->id}] ALL JOBS COMPLETED. Total: {$batch->totalJobs}, Processed: {$batch->processedJobs()}, Failed: {$batch->failedJobs}" . PHP_EOL;
            })
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error('Blueprint price update SUB-BATCH failed one or more jobs.', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                    'failed_jobs_count' => $batch->failedJobs,
                    'first_failed_job_id' => $batch->failedJobIds[0] ?? 'N/A'
                ]);
                echo "Sub-Batch [{$batch->id}] FAILED. Error: {$e->getMessage()}. Failed jobs: {$batch->failedJobs}" . PHP_EOL;
            })
            ->finally(function (Batch $batch) {
                Log::info('Blueprint price update SUB-BATCH finished executing.', ['batch_id' => $batch->id, 'total_jobs' => $batch->totalJobs, 'processed_jobs' => $batch->processedJobs(), 'failed_jobs' => $batch->failedJobs]);
                echo "Sub-Batch [{$batch->id}] FINISHED. Total: {$batch->totalJobs}, Processed: {$batch->processedJobs()}, Failed: {$batch->failedJobs}" . PHP_EOL;
            })
            ->name('Update Blueprint Prices Batch - Part - ' . now()->toDateTimeString() . '-' . Str::random(4))
            ->onQueue($queueName)
            ->dispatch();
    }
}
