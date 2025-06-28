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
    protected $signature = 'itsmint:queue-price-updates {--blueprint_ct_id= : Queue update for a specific blueprint ID} {--game_id= : Filter by game ID} {--expansion_id= : Filter by expansion ID} {--batch_size=100 : Number of jobs per batch}';

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

            if (!empty($jobsForCurrentBatch)) {
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
        if (empty($jobs)) return;

        Bus::batch($jobs)
            ->catch(function (Batch $batch, Throwable $e) {
                Log::error('Blueprint price update SUB-BATCH failed one or more jobs.', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                    'failed_jobs_count' => $batch->failedJobs,
                    'first_failed_job_id' => $batch->failedJobIds[0] ?? 'N/A'
                ]);
                echo "Sub-Batch [{$batch->id}] FAILED. Error: {$e->getMessage()}. Failed jobs: {$batch->failedJobs}" . PHP_EOL;
            })
            ->name('Update Blueprint Prices Batch - Part - ' . now()->toDateTimeString() . '-' . Str::random(4))
            ->onQueue($queueName)
            ->dispatch();
    }
}
