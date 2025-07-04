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
                'response_keys' => is_array($adjustedPrices) ? array_keys($adjustedPrices) : null,
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

            $pricesProcessed = 0;
            $pricesSkipped = 0;

            foreach ($adjustedPrices as $priceKey => $priceData) {
                // Skip metadata entry
                if ($priceKey === '_metadata') {
                    continue;
                }

                // Validate that priceData has required fields for the new structure
                if (! isset($priceData['condition']) || ! isset($priceData['language'])) {
                    Log::warning('UpdateBlueprintPriceJob: Price data missing required fields', [
                        'blueprint_id' => $blueprint->id,
                        'price_key' => $priceKey,
                        'missing_fields' => [
                            'condition' => ! isset($priceData['condition']),
                            'language' => ! isset($priceData['language']),
                        ],
                    ]);
                    $pricesSkipped++;

                    continue;
                }

                CardPrice::updateOrCreate([
                    'blueprint_id' => $blueprint->id,
                    'condition' => $priceData['condition'],
                    'language' => $priceData['language'], // Now includes language in search criteria
                ], [
                    'price_cents' => $priceData['price_cents'],
                    'currency' => $priceData['currency'] ?? 'EUR',
                    'is_foil' => $priceData['is_foil'] ?? false,
                    'source' => $priceData['source'] ?? 'Default Source',
                    'fetched_at' => $priceData['fetched_at'],
                ]);

                Log::info('Saved/Updated price for blueprint by condition and language (from service).', [
                    'blueprint_id' => $blueprint->id,
                    'condition' => $priceData['condition'],
                    'language' => $priceData['language'],
                    'price_cents' => $priceData['price_cents'],
                    'source' => $priceData['source'],
                    'was_adjusted' => isset($priceData['original_price_cents']),
                    'original_price_cents' => $priceData['original_price_cents'] ?? null,
                ]);

                $pricesProcessed++;
            }

            Log::info('UpdateBlueprintPriceJob completed successfully', [
                'blueprint_id' => $blueprint->id,
                'ct_id' => $blueprintCtId,
                'prices_processed' => $pricesProcessed,
                'prices_skipped' => $pricesSkipped,
                'total_entries_in_response' => count($adjustedPrices),
            ]);
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
