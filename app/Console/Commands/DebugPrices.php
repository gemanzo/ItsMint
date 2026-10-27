<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\PriceProvider;
use Illuminate\Console\Command;

final class DebugPrices extends Command
{
    protected $signature = 'itsmint:prices-debug {blueprintId : The local blueprint id}';

    protected $description = 'Print raw price points by provider and the final aggregated estimate';

    public function handle(PriceAggregatorService $aggregator): int
    {
        $bpId = (int) $this->argument('blueprintId');
        $bp = Blueprint::find($bpId);
        if (! $bp) {
            $this->error('Blueprint not found: '.$bpId);

            return self::FAILURE;
        }

        // Access providers via container binding in AppServiceProvider
        $ref = new \ReflectionClass($aggregator);
        $prop = $ref->getProperty('providers');
        $prop->setAccessible(true);
        /** @var PriceProvider[] $providers */
        $providers = $prop->getValue($aggregator);

        foreach ($providers as $provider) {
            $this->info('Provider: '.$provider->sourceName());
            $points = $provider->fetch($bp);
            if ($points->isEmpty()) {
                $this->line('  (no points)');

                continue;
            }
            $this->table(
                ['price_cents', 'currency', 'condition', 'language', 'is_foil', 'fetched_at', 'meta'],
                $points->take(20)->map(fn ($p) => [
                    $p->priceCents,
                    $p->currency,
                    $p->condition,
                    $p->language,
                    $p->isFoil ? '1' : '0',
                    $p->fetchedAt->toDateTimeString(),
                    json_encode($p->meta),
                ])->all()
            );
        }

        $this->info('Aggregated estimate:');
        $est = $aggregator->estimate($bp);
        $rows = collect($est)
            ->reject(fn ($v, $k) => $k === '_metadata')
            ->map(fn ($v) => [
                $v['condition'],
                $v['language'],
                $v['price_cents'],
                $v['currency'],
                $v['source'],
                (string) $v['fetched_at'],
            ])->values()->all();
        $this->table(['condition', 'language', 'price_cents', 'currency', 'source', 'fetched_at'], $rows);

        return self::SUCCESS;
    }
}
