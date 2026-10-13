<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Blueprint;
use App\Models\CardPrice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PriceAggregatorService
{
    /** @param PriceProvider[] $providers */
    public function __construct(
        private readonly array $providers,
    ) {}

    /** @return array<string, array<string, mixed>> */
    public function estimate(Blueprint $blueprint): array
    {
        $points = collect($this->providers)
            ->flatMap(fn(PriceProvider $p) => $p->fetch($blueprint))
            ->filter();

        $this->storeRawPoints($points);

        $byKey = $points->groupBy(fn(PricePoint $pp) => strtolower($pp->language) . '|' . $pp->condition);

        $estimates = [];
        foreach ($byKey as $key => $group) {
            [$lang, $cond] = explode('|', $key, 2);

            $values = $group->map(fn(PricePoint $pp) => [
                'value' => $pp->priceCents,
                'weight' => $this->weightFor($pp),
            ]);

            if ($values->isEmpty()) {
                continue;
            }

            $trimmed = $this->winsorize($values->pluck('value')->all(), 0.15);
            $values = $values->values()->map(fn($v, $i) => array_merge($v, ['value' => $trimmed[$i]]));

            $num = 0.0;
            $den = 0.0;
            foreach ($values as $v) {
                $num += $v['value'] * $v['weight'];
                $den += $v['weight'];
            }
            if ($den <= 0) {
                continue;
            }

            $priceCents = (int) round($num / $den);

            $estimates[$cond . '_' . $lang] = [
                'price_cents' => $priceCents,
                'currency' => 'EUR',
                'condition' => $cond,
                'language' => $lang,
                'is_foil' => false,
                'fetched_at' => Carbon::now(),
                'source' => 'aggregate_v1',
            ];
        }

        $this->upsertDailyAggregates($blueprint, $estimates);

        return $estimates;
    }

    private function storeRawPoints(Collection $points): void
    {
        if ($points->isEmpty()) {
            return;
        }
        $rows = $points->map(fn(PricePoint $pp) => [
            'blueprint_id' => $pp->blueprintId,
            'price_cents' => $pp->priceCents,
            'currency' => $pp->currency,
            'condition' => $pp->condition,
            'language' => strtolower($pp->language),
            'is_foil' => $pp->isFoil,
            'source' => $pp->source,
            'fetched_at' => $pp->fetchedAt->toDateTimeString(),
            'meta' => json_encode($pp->meta),
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('card_price_points')->insert($rows);
    }

    private function upsertDailyAggregates(Blueprint $bp, array $estimates): void
    {
        foreach ($estimates as $entry) {
            $exists = CardPrice::query()
                ->where('blueprint_id', $bp->id)
                ->where('condition', $entry['condition'])
                ->where('language', $entry['language'])
                ->whereDate('fetched_at', today())
                ->first();

            if ($exists) {
                $exists->update([
                    'price_cents' => $entry['price_cents'],
                    'currency' => $entry['currency'],
                    'is_foil' => $entry['is_foil'] ?? false,
                    'source' => $entry['source'],
                    'fetched_at' => $entry['fetched_at'],
                ]);
            } else {
                CardPrice::create([
                    'blueprint_id' => $bp->id,
                    'condition' => $entry['condition'],
                    'language' => $entry['language'],
                    'price_cents' => $entry['price_cents'],
                    'currency' => $entry['currency'],
                    'is_foil' => $entry['is_foil'] ?? false,
                    'source' => $entry['source'],
                    'fetched_at' => $entry['fetched_at'],
                ]);
            }
        }
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
