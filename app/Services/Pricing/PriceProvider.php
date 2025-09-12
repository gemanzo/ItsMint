<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\Blueprint;
use Illuminate\Support\Collection;

interface PriceProvider
{
    public function sourceName(): string;

    /** @return Collection<int, PricePoint> */
    public function fetch(Blueprint $blueprint): Collection;
}
