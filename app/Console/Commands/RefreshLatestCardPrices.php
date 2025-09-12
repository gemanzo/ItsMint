<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class RefreshLatestCardPrices extends Command
{
    protected $signature = 'itsmint:refresh-latest';

    protected $description = 'Refresh materialized view for latest/best card prices';

    public function handle(): int
    {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY card_latest_prices_mv');
        $this->info('card_latest_prices_mv refreshed.');
        return self::SUCCESS;
    }
}
