<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add generated column for condition priority (PostgreSQL)
        // 1: Near Mint, 2: Slightly Played, 3: Moderately Played, 4: Played, 5: Poor, 6: others
        DB::statement(<<<SQL
            ALTER TABLE card_prices
            ADD COLUMN IF NOT EXISTS condition_priority smallint
            GENERATED ALWAYS AS (
                CASE
                    WHEN condition = 'Near Mint' THEN 1
                    WHEN condition = 'Slightly Played' THEN 2
                    WHEN condition = 'Moderately Played' THEN 3
                    WHEN condition = 'Played' THEN 4
                    WHEN condition = 'Poor' THEN 5
                    ELSE 6
                END
            ) STORED
        SQL);

        // Composite indexes to speed up latest price queries
        DB::statement(<<<SQL
            CREATE INDEX IF NOT EXISTS idx_card_prices_bp_lang_cond_fetch_price
            ON card_prices (blueprint_id, language, condition_priority, fetched_at DESC, price_cents DESC)
        SQL);

        DB::statement(<<<SQL
            CREATE INDEX IF NOT EXISTS idx_card_prices_bp_cond_fetch_price
            ON card_prices (blueprint_id, condition_priority, fetched_at DESC, price_cents DESC)
        SQL);

        // Optional: support lookups by (blueprint_id, condition)
        DB::statement(<<<SQL
            CREATE INDEX IF NOT EXISTS idx_card_prices_bp_condition
            ON card_prices (blueprint_id, condition)
        SQL);
    }

    public function down(): void
    {
        // Drop indexes first
        DB::statement('DROP INDEX IF EXISTS idx_card_prices_bp_lang_cond_fetch_price');
        DB::statement('DROP INDEX IF EXISTS idx_card_prices_bp_cond_fetch_price');
        DB::statement('DROP INDEX IF EXISTS idx_card_prices_bp_condition');

        // Drop column
        DB::statement('ALTER TABLE card_prices DROP COLUMN IF EXISTS condition_priority');
    }
};
