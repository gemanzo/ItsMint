<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\Providers\CardmarketGuidePriceProvider;
use App\Services\Pricing\Providers\CardmarketGuideReader;
use Illuminate\Console\Command;

final class IngestCardmarketGuide extends Command
{
    protected $signature = 'itsmint:ingest-cardmarket {--limit= : Max blueprints to process}';

    protected $description = 'Use Cardmarket local guide to create price points + estimates';

    public function handle(PriceAggregatorService $aggregator, CardmarketGuidePriceProvider $provider, CardmarketGuideReader $reader): int
    {
        $limit = (int) ($this->option('limit') ?? 0);

        // Preload Cardmarket JSON once (cached 24h + in-memory) before iterating
        $this->info('Preloading Cardmarket price guide...');
        $reader->preload();

        $query = Blueprint::query()->singleCards();
        if ($limit > 0) {
            $query->limit($limit);
        }

        $count = 0;
        $this->withProgressBar($query->cursor(), function (Blueprint $bp) use ($aggregator, &$count) {
            $aggregator->estimate($bp);
            $count++;
        });

        $this->newLine();
        $this->info("Processed {$count} blueprints.");

        return self::SUCCESS;
    }
}
