<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use Carbon\CarbonImmutable;

final class PricePoint
{
    public function __construct(
        public readonly int $blueprintId,
        public readonly int $priceCents,
        public readonly string $currency,
        public readonly string $condition,
        public readonly string $language,
        public readonly bool $isFoil,
        public readonly CarbonImmutable $fetchedAt,
        public readonly string $source,
        public readonly array $meta = [],
    ) {}
}
