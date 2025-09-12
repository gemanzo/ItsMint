<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Create materialized view with best/latest price per (blueprint_id, language, is_foil)
        DB::statement(<<<SQL
            CREATE MATERIALIZED VIEW IF NOT EXISTS card_latest_prices_mv AS
            WITH ranked AS (
                SELECT
                    id,
                    blueprint_id,
                    language,
                    is_foil,
                    condition,
                    condition_priority,
                    price_cents,
                    fetched_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY blueprint_id, language, is_foil
                        ORDER BY condition_priority ASC, fetched_at DESC, price_cents DESC, id DESC
                    ) AS rn
                FROM card_prices
            )
            SELECT
                blueprint_id,
                language,
                is_foil,
                condition,
                condition_priority,
                price_cents,
                fetched_at
            FROM ranked
            WHERE rn = 1
        SQL);

        // Unique index required for CONCURRENTLY refresh and fast lookups
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX IF NOT EXISTS idx_card_latest_prices_mv_unique
            ON card_latest_prices_mv (blueprint_id, language, is_foil)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_card_latest_prices_mv_unique');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS card_latest_prices_mv');
    }
};
