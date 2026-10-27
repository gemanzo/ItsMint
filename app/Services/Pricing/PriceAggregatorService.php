<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Enums\CardLanguage;
use App\Models\Blueprint;
use App\Models\CardPrice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the daily market price of a card from the raw price points of every source.
 *
 * The work is split in two independent steps:
 * 1. recordPricePoints(): each source (Cardmarket guide, CardTrader listings) stores
 *    its raw observations in card_price_points, whenever it is fetched;
 * 2. aggregate(): reads only the database (no network call) and combines the most
 *    recent point of each source into one price per language, condition and foil,
 *    stored in card_prices for the current day.
 *
 * Both steps are idempotent: running them again the same day doesn't create duplicates.
 */
final class PriceAggregatorService
{
    /**
     * Points older than this are not used: a price based only on stale
     * observations would look current while it is not.
     */
    private const FRESHNESS_WINDOW_DAYS = 7;

    /**
     * Share of values clamped on each side before averaging (outlier protection).
     */
    private const WINSORIZE_ALPHA = 0.15;

    /** @param PriceProvider[] $providers */
    public function __construct(
        private readonly array $providers,
    ) {}

    /**
     * Fetch every provider, store the points and aggregate them.
     *
     * Meant for on-demand runs (e.g. itsmint:prices-debug): scheduled runs fetch each
     * source separately and then call aggregate().
     *
     * @return array<string, array<string, mixed>>
     */
    public function estimate(Blueprint $blueprint): array
    {
        foreach ($this->providers as $provider) {
            $this->recordPricePoints($provider->fetch($blueprint));
        }

        return $this->aggregate($blueprint);
    }

    /**
     * Store raw price points.
     *
     * Points with a non-positive price or an unknown language are discarded.
     * Recording the same observation twice is a no-op (unique snapshot index).
     *
     * @param  Collection<int, PricePoint>  $pricePoints
     * @return int Number of points actually stored
     */
    public function recordPricePoints(Collection $pricePoints): int
    {
        $pointRows = [];

        foreach ($pricePoints as $pricePoint) {
            if ($pricePoint->priceCents <= 0) {
                continue;
            }

            $language = CardLanguage::fromProviderValue($pricePoint->language);
            if ($language === null) {
                continue;
            }

            $pointRows[] = [
                'blueprint_id' => $pricePoint->blueprintId,
                'price_cents' => $pricePoint->priceCents,
                'currency' => $pricePoint->currency,
                'condition' => $pricePoint->condition,
                'language' => $language->value,
                'is_foil' => $pricePoint->isFoil,
                'source' => $pricePoint->source,
                // With the timezone offset: the column is a timestamptz
                'fetched_at' => $pricePoint->fetchedAt->utc()->toIso8601String(),
                'meta' => json_encode($pricePoint->meta),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (empty($pointRows)) {
            return 0;
        }

        return DB::table('card_price_points')->insertOrIgnore($pointRows);
    }

    /**
     * Combine the stored points into today's prices of the card (database only).
     *
     * @return array<string, array<string, mixed>> Prices keyed by "{condition}_{language}"
     */
    public function aggregate(Blueprint $blueprint): array
    {
        $pointsByPriceKey = $this->latestFreshPointOfEachSource($blueprint)
            ->groupBy(fn (PricePoint $pricePoint) => $this->priceKey($pricePoint));

        $estimates = [];

        foreach ($pointsByPriceKey as $priceKey => $pointsOfAllSources) {
            $weightedPriceCents = $this->weightedAveragePriceCents($pointsOfAllSources);
            if ($weightedPriceCents === null) {
                continue;
            }

            /** @var PricePoint $referencePoint */
            $referencePoint = $pointsOfAllSources->first();
            $contributingSources = $pointsOfAllSources
                ->map(fn (PricePoint $pricePoint) => $pricePoint->source)
                ->unique()
                ->sort()
                ->implode('+');
            $newestObservationAt = $pointsOfAllSources
                ->map(fn (PricePoint $pricePoint) => $pricePoint->fetchedAt)
                ->max();

            $estimates[$priceKey] = [
                'price_cents' => $weightedPriceCents,
                'currency' => 'EUR',
                'condition' => $referencePoint->condition,
                'language' => $referencePoint->language,
                'is_foil' => $referencePoint->isFoil,
                // When the underlying market data was observed, not when it was aggregated
                'fetched_at' => $newestObservationAt,
                // Provenance: which sources produced this price
                'source' => 'aggregate:'.$contributingSources,
            ];
        }

        $this->upsertTodayPrices($blueprint, $estimates);

        return $estimates;
    }

    /**
     * Most recent point of each source for each language/condition/foil, within the
     * freshness window. Taking one point per source keeps a source fetched many times
     * from outweighing the others.
     *
     * @return Collection<int, PricePoint>
     */
    private function latestFreshPointOfEachSource(Blueprint $blueprint): Collection
    {
        return DB::table('card_price_points')
            ->where('blueprint_id', $blueprint->id)
            ->where('fetched_at', '>=', now()->subDays(self::FRESHNESS_WINDOW_DAYS))
            ->orderByDesc('fetched_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (object $pointRow) => new PricePoint(
                blueprintId: $pointRow->blueprint_id,
                priceCents: $pointRow->price_cents,
                currency: $pointRow->currency,
                condition: $pointRow->condition,
                language: $pointRow->language,
                isFoil: (bool) $pointRow->is_foil,
                fetchedAt: CarbonImmutable::parse($pointRow->fetched_at),
                source: $pointRow->source,
                meta: json_decode($pointRow->meta ?? '[]', true) ?? [],
            ))
            // Rows are sorted newest first: unique() keeps the latest point of each source
            ->unique(fn (PricePoint $pricePoint) => $pricePoint->source.'|'.$this->priceKey($pricePoint))
            ->values();
    }

    /**
     * Weighted average of the points (outliers clamped first), or null when no point has weight.
     *
     * @param  Collection<int, PricePoint>  $pricePoints
     */
    private function weightedAveragePriceCents(Collection $pricePoints): ?int
    {
        $pricePoints = $pricePoints->values();
        $clampedPrices = $this->winsorize(
            $pricePoints->map(fn (PricePoint $pricePoint) => $pricePoint->priceCents)->all(),
            self::WINSORIZE_ALPHA
        );

        $weightedPricesSum = 0.0;
        $weightsSum = 0.0;
        foreach ($pricePoints as $pointIndex => $pricePoint) {
            $pointWeight = $this->weightFor($pricePoint);
            $weightedPricesSum += $clampedPrices[$pointIndex] * $pointWeight;
            $weightsSum += $pointWeight;
        }

        if ($weightsSum <= 0) {
            return null;
        }

        return (int) round($weightedPricesSum / $weightsSum);
    }

    /**
     * Store today's prices: one row per card, language, condition, foil and day.
     *
     * @param  array<string, array<string, mixed>>  $estimates
     */
    private function upsertTodayPrices(Blueprint $blueprint, array $estimates): void
    {
        if (empty($estimates)) {
            return;
        }

        $todayDate = today()->toDateString();
        $priceRows = [];

        foreach ($estimates as $estimate) {
            $priceRows[] = [
                'blueprint_id' => $blueprint->id,
                'language' => $estimate['language'],
                'condition' => $estimate['condition'],
                'is_foil' => $estimate['is_foil'],
                'price_date' => $todayDate,
                'price_cents' => $estimate['price_cents'],
                'currency' => $estimate['currency'],
                'source' => $estimate['source'],
                // card_prices.fetched_at has no timezone: always stored in UTC
                'fetched_at' => $estimate['fetched_at']->utc()->toDateTimeString(),
            ];
        }

        CardPrice::query()->upsert(
            $priceRows,
            uniqueBy: ['blueprint_id', 'language', 'condition', 'is_foil', 'price_date'],
            update: ['price_cents', 'currency', 'source', 'fetched_at']
        );
    }

    /**
     * Key identifying a price: condition and language, plus a suffix for foil prices.
     */
    private function priceKey(PricePoint $pricePoint): string
    {
        $priceKey = $pricePoint->condition.'_'.$pricePoint->language;

        if ($pricePoint->isFoil) {
            $priceKey .= '_foil';
        }

        return $priceKey;
    }

    private function weightFor(PricePoint $pp): float
    {
        $base = match ($pp->source) {
            'cardmarket_guide' => 9.0,
            'cardtrader' => 1.0,
            default => 0.5,
        };

        $ageDays = $pp->fetchedAt->diffInDays(now());
        $fresh = pow(0.5, $ageDays / 7);

        return max(0.05, $base * $fresh);
    }

    /**
     * Clamp outliers to the [alpha, 1 - alpha] quantile bounds.
     *
     * IMPORTANT: the returned values keep the ORIGINAL order of the input, because the
     * caller re-aligns them by index with their source weights. The bounds are computed
     * on a sorted copy only (sorting the input itself would assign each weight to the
     * wrong price).
     *
     * @param  int[]  $values
     * @return int[]
     */
    private function winsorize(array $values, float $alpha): array
    {
        $valuesCount = count($values);
        if ($valuesCount === 0) {
            return $values;
        }

        $sortedValues = array_values($values);
        sort($sortedValues);

        $trimmedPerSide = (int) floor($alpha * $valuesCount);
        $lowerBound = $sortedValues[$trimmedPerSide];
        $upperBound = $sortedValues[$valuesCount - $trimmedPerSide - 1];

        return array_map(
            fn (int $value) => min($upperBound, max($lowerBound, $value)),
            array_values($values)
        );
    }
}
