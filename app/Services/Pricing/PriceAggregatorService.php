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

    /** @param int[] $values */
    private function winsorize(array $values, float $alpha): array
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return $values;
        }
        $k = (int) floor($alpha * $n);
        $low = $values[$k] ?? $values[0];
        $high = $values[max(0, $n - $k - 1)] ?? $values[$n - 1];
        return array_map(fn($v) => min($high, max($low, $v)), $values);
    }
}
