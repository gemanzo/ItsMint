<?php

namespace App\Jobs;

use App\Models\Blueprint;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\Providers\CardTraderPriceProvider;
use DateTimeInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fetch the CardTrader listings of a card, store them as price points and
 * recompute the card's prices of the day.
 */
class UpdateBlueprintPriceJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Cards whose CardTrader listings were fetched more recently than this are
     * not fetched again (e.g. nightly batch followed by the CardTrader mirror).
     */
    public const REFETCH_AFTER_HOURS = 12;

    /**
     * Seconds before the job is killed. It must stay below the queue "retry_after"
     * (180s, config/queue.php), otherwise a slow job is picked up a second time
     * while it is still running.
     */
    public int $timeout = 120;

    /**
     * Real failures (CardTrader errors) allowed before the job is marked as failed.
     * Releases caused by the rate limiter are not counted.
     */
    public int $maxExceptions = 3;

    public function __construct(
        public Blueprint $blueprint,
    ) {
        $this->onQueue('price-updates');
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new RateLimited('cardtrader-api')];
    }

    /**
     * The rate limiter releases jobs back on the queue until there is room for them.
     * With a deadline instead of a number of tries, those releases don't make the job
     * fail (before, with "tries = 1", every rate limited job ended in failed_jobs).
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /**
     * Seconds to wait before retrying after a real failure.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    /**
     * Execute the job.
     *
     * Services are resolved here and not in the constructor: the queue payload only
     * contains the card, never a serialized copy of the services.
     */
    public function handle(CardTraderPriceProvider $cardTraderPriceProvider, PriceAggregatorService $priceAggregator): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (self::wasFetchedRecently($this->blueprint)) {
            Log::debug('UpdateBlueprintPriceJob: CardTrader listings fetched recently, skipped', [
                'blueprint_id' => $this->blueprint->id,
            ]);

            return;
        }

        // Throws CardTraderApiException when CardTrader can't provide the data: the job is
        // retried and, after $maxExceptions failures, marked as failed (Horizon, failed_jobs)
        $cardTraderPricePoints = $cardTraderPriceProvider->fetch($this->blueprint);

        $storedPointsCount = $priceAggregator->recordPricePoints($cardTraderPricePoints);
        $estimates = $priceAggregator->aggregate($this->blueprint);

        Log::debug('UpdateBlueprintPriceJob: prices updated', [
            'blueprint_id' => $this->blueprint->id,
            'stored_points_count' => $storedPointsCount,
            'prices_count' => count($estimates),
        ]);
    }

    /**
     * Whether the CardTrader listings of the card were fetched recently enough.
     */
    public static function wasFetchedRecently(Blueprint $blueprint): bool
    {
        return DB::table('card_price_points')
            ->where('blueprint_id', $blueprint->id)
            ->where('source', 'cardtrader')
            ->where('fetched_at', '>=', now()->subHours(self::REFETCH_AFTER_HOURS))
            ->exists();
    }
}
