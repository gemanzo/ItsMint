<?php

namespace App\Jobs;

use App\Models\Blueprint;
use App\Models\CardPrice;
use App\Services\BlueprintPriceService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateBlueprintPriceJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The queue this job should be sent to.
     *
     * @var string
     */
    protected Blueprint $localBlueprint;

    protected BlueprintPriceService $blueprintPriceService;

    /**
     * Create a new job instance.
     */
    public function __construct(Blueprint $localBlueprint, BlueprintPriceService $blueprintPriceService)
    {
        $this->localBlueprint = $localBlueprint;
        $this->blueprintPriceService = $blueprintPriceService;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // It's good practice to ensure the model is correctly hydrated,
        // especially if SerializesModels might sometimes pass an ID.
        $blueprint = $this->localBlueprint;
        if (! $blueprint instanceof Blueprint && (is_int($blueprint) || is_string($blueprint))) {
            Log::warning('UpdateBlueprintPriceJob: localBlueprint was an ID, attempting to rehydrate.', ['id' => $blueprint]);
            $blueprint = Blueprint::find($this->localBlueprint);
        }

        if (! $blueprint instanceof Blueprint) {
            Log::error('UpdateBlueprintPriceJob: Invalid or non-existent Blueprint model.', [
                'data_type' => gettype($this->localBlueprint),
                'data_passed' => $this->localBlueprint,
            ]);

            // Optionally, fail the job if the blueprint is critical and not found
            // $this->fail('Blueprint not found or invalid.');
            return;
        }

        $blueprintCtId = $blueprint->ct_id;

        try {
            $adjustedPrices = $this->blueprintPriceService->calculatePrices($blueprint);

            // --- BEGIN ADDED LOGGING ---
            Log::debug('UpdateBlueprintPriceJob: Data received from BlueprintPriceService', [
                'blueprint_id' => $blueprint->id,
                'ct_id' => $blueprintCtId,
                'service_response_is_null' => is_null($adjustedPrices),
                'service_response_count' => is_array($adjustedPrices) ? count($adjustedPrices) : 'N/A (not an array)',
                // Consider logging $adjustedPrices directly if the data volume is manageable
                // 'service_response_data' => $adjustedPrices
            ]);
            // --- END ADDED LOGGING ---

            if (is_null($adjustedPrices)) {
                Log::info('BlueprintPriceService returned null, skipping price updates for blueprint.', ['blueprint_id' => $blueprint->id, 'ct_id' => $blueprintCtId]);

                return;
            }

            if (empty($adjustedPrices)) {
                Log::info('No prices to save after service calculation for blueprint.', ['blueprint_id' => $blueprint->id, 'ct_id' => $blueprintCtId]);

                return;
            }

            foreach ($adjustedPrices as $condition => $priceData) {
                CardPrice::updateOrCreate([
                    'blueprint_id' => $blueprint->id,
                    'condition' => $condition,
                ], [
                    'price_cents' => $priceData['price_cents'],
                    'currency' => $priceData['currency'] ?? 'EUR',
                    'language' => $priceData['language'] ?? 'en',
                    'is_foil' => $priceData['is_foil'] ?? false,
                    'source' => $priceData['source'] ?? 'Default Source',
                    'fetched_at' => $priceData['fetched_at'],
                ]);
                Log::info('Saved/Updated price for blueprint by condition (from service).', [
                    'blueprint_id' => $blueprint->id,
                    'condition' => $condition,
                    'price' => $priceData['price_cents'],
                    'source' => $priceData['source'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception in UpdateBlueprintPriceJob (after service call)', [
                'blueprint_id' => $blueprint->id ?? 'N/A',
                'ct_id' => $blueprintCtId ?? 'N/A',
                'error' => $e->getMessage(),
                'trace_small' => substr($e->getTraceAsString(), 0, 500),
            ]);
            // Optionally rethrow to let the batch know this job failed, or handle failure here.
            // It's good practice to rethrow if the job cannot complete its task.
            throw $e;
        }
    }
}
