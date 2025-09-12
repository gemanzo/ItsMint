<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Blueprint;
use App\Services\Pricing\PriceAggregatorService;
use App\Services\Pricing\Providers\CardmarketGuidePriceProvider;
use Illuminate\Console\Command;

final class IngestCardmarketGuide extends Command
{
    protected $signature = 'prices:ingest-cardmarket {--limit= : Max blueprints to process}';

    protected $description = 'Download Cardmarket price guide JSON and create price points + estimates';

    public function handle(PriceAggregatorService $aggregator, CardmarketGuidePriceProvider $provider): int
    {
        $limit = (int) ($this->option('limit') ?? 0);

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
