<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The "latest price" of a card was the best condition ever recorded, then the most
 * recent: a Near Mint price from six months ago won over today's Lightly Played one,
 * and prices of a source that stopped updating stayed "current" forever.
 *
 * Now (same rules as CardPrice::scopeCurrent / scopeLatestFirst):
 * - most recent day first, then the best condition of that day;
 * - prices older than 30 days (CardPrice::CURRENT_PRICE_MAX_AGE_DAYS) are excluded.
 * The window is evaluated at each refresh (itsmint:refresh-latest, hourly).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS card_latest_prices_mv');

        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW card_latest_prices_mv AS
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
                    price_date,
                    ROW_NUMBER() OVER (
                        PARTITION BY blueprint_id, language, is_foil
                        ORDER BY price_date DESC, condition_priority ASC, id DESC
                    ) AS rn
                FROM card_prices
                WHERE price_date >= CURRENT_DATE - 30
            )
            SELECT
                blueprint_id,
                language,
                is_foil,
                condition,
                condition_priority,
                price_cents,
                fetched_at,
                price_date
            FROM ranked
            WHERE rn = 1
        SQL);

        // Unique index required for CONCURRENTLY refresh and fast lookups
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX idx_card_latest_prices_mv_unique
            ON card_latest_prices_mv (blueprint_id, language, is_foil)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS card_latest_prices_mv');

        // Previous definition (2025_09_12_000001_create_card_latest_prices_mv)
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW card_latest_prices_mv AS
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

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX idx_card_latest_prices_mv_unique
            ON card_latest_prices_mv (blueprint_id, language, is_foil)
        SQL);
    }
};
