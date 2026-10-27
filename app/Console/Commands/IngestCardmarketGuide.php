<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\Providers\CardmarketGuidePriceProvider;
use App\Services\Pricing\Providers\CardmarketGuideReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Store the downloaded Cardmarket price guide as price points and update the
 * daily prices of every single card.
 *
 * Reads only the local guide and the database: CardTrader is fetched by the
 * rate limited price jobs, never from here.
 */
final class IngestCardmarketGuide extends Command
{
    protected $signature = 'itsmint:ingest-cardmarket {--limit= : Max blueprints to process}';

    protected $description = 'Store the Cardmarket price guide as price points and update the daily prices';

    /**
     * A guide older than this is refused: its prices would be stored as current ones
     * (e.g. when the download keeps failing and an old file is left on disk).
     */
    private const MAX_GUIDE_AGE_HOURS = 48;

    public function handle(
        CardmarketGuideReader $guideReader,
        CardmarketGuidePriceProvider $cardmarketPriceProvider,
        PriceAggregatorService $priceAggregator,
    ): int {
        $this->info('Loading the Cardmarket price guide...');
        $guideReader->preload();

        $guideCreatedAt = $guideReader->guideCreatedAt();
        if ($guideCreatedAt === null) {
            $this->error('Cardmarket price guide not available or without creation date: run itsmint:download-cardmarket-guide first.');

            return self::FAILURE;
        }

        if ($guideCreatedAt->lt(now()->subHours(self::MAX_GUIDE_AGE_HOURS))) {
            $this->error("The Cardmarket price guide is too old (created {$guideCreatedAt->toDateTimeString()}): prices not updated.");
            Log::error('Cardmarket ingest refused a stale price guide', [
                'guide_created_at' => $guideCreatedAt->toIso8601String(),
            ]);

            return self::FAILURE;
        }

        $blueprintsQuery = Blueprint::query()->singleCards();
        $limit = (int) ($this->option('limit') ?? 0);
        if ($limit > 0) {
            $blueprintsQuery->limit($limit);
        }

        $processedBlueprintsCount = 0;
        $failedBlueprintsCount = 0;

        $this->withProgressBar(
            $blueprintsQuery->cursor(),
            function (Blueprint $blueprint) use ($cardmarketPriceProvider, $priceAggregator, &$processedBlueprintsCount, &$failedBlueprintsCount) {
                // One card failing must not stop the other cards
                try {
                    $priceAggregator->recordPricePoints($cardmarketPriceProvider->fetch($blueprint));
                    $priceAggregator->aggregate($blueprint);
                    $processedBlueprintsCount++;
                } catch (Throwable $exception) {
                    $failedBlueprintsCount++;
                    Log::error('Cardmarket ingest failed for a card', [
                        'blueprint_id' => $blueprint->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        );

        $this->newLine();
        $this->info("Processed {$processedBlueprintsCount} blueprints, {$failedBlueprintsCount} failed.");

        // A partial failure is reported as a failure, so the scheduler doesn't hide it
        if ($failedBlueprintsCount > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
