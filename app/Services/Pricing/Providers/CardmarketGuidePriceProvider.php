<?php

declare(strict_types=1);

namespace App\Services\Pricing\Providers;

use App\Models\Blueprint;
use App\Services\Pricing\PricePoint;
use App\Services\Pricing\PriceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CardmarketGuidePriceProvider implements PriceProvider
{
    public function __construct(private readonly CardmarketGuideReader $reader) {}

    public function sourceName(): string
    {
        return 'cardmarket_guide';
    }

    /** @return Collection<int, PricePoint> */
    public function fetch(Blueprint $blueprint): Collection
    {
        $rows = $this->reader->lookup($blueprint);
        if (empty($rows)) {
            return collect();
        }

        $points = [];
        foreach ($rows as $r) {
            $eur = $r['avg_price_eur'] ?? $r['trend_price_eur'] ?? null;
            if (!is_numeric($eur)) {
                continue;
            }
            $points[] = new PricePoint(
                blueprintId: $blueprint->id,
                priceCents: (int) round(((float) $eur) * 100),
                currency: 'EUR',
                condition: $r['condition'] ?? 'Near Mint',
                language: strtolower($r['language'] ?? 'en'),
                isFoil: (bool) ($r['is_foil'] ?? false),
                fetchedAt: CarbonImmutable::parse($r['date'] ?? now()),
                source: $this->sourceName(),
                meta: ['sample_size' => $r['sample_size'] ?? null],
            );
        }

        return collect($points);
    }
}
