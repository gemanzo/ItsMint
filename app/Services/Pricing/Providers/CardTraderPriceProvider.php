<?php

declare(strict_types=1);

namespace App\Services\Pricing\Providers;

use App\Models\Blueprint;
use App\Services\BlueprintPriceService;
use App\Services\Pricing\PricePoint;
use App\Services\Pricing\PriceProvider;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class CardTraderPriceProvider implements PriceProvider
{
    public function __construct(private readonly BlueprintPriceService $service) {}

    public function sourceName(): string
    {
        return 'cardtrader';
    }

    /** @return Collection<int, PricePoint> */
    public function fetch(Blueprint $blueprint): Collection
    {
        $result = $this->service->calculatePrices($blueprint);
        if (! $result) {
            return collect();
        }

        $points = [];
        foreach ($result as $key => $data) {
            if ($key === '_metadata') {
                continue;
            }
            $points[] = new PricePoint(
                blueprintId: $blueprint->id,
                priceCents: (int) ($data['price_cents'] ?? 0),
                currency: (string) ($data['currency'] ?? 'EUR'),
                condition: (string) $data['condition'],
                language: (string) strtolower($data['language'] ?? 'en'),
                isFoil: (bool) ($data['is_foil'] ?? false),
                fetchedAt: CarbonImmutable::parse($data['fetched_at'] ?? now()),
                source: $this->sourceName(),
                meta: ['product_count' => $data['product_count'] ?? null],
            );
        }

        return collect($points);
    }
}
