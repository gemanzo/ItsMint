<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make price storage idempotent.
 *
 * card_prices: exactly one price per card, language, condition, foil and day
 * (price_date), written with upserts. Before this, every run inserted new rows
 * ("find today's row, then create"), so overlapping runs produced duplicates.
 *
 * card_price_points: a raw observation (same source, card, language, condition,
 * foil and fetch time) can be stored only once, so re-ingesting the same
 * Cardmarket guide no longer duplicates its points.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rows that can't be identified by the new key are removed
        DB::statement('DELETE FROM card_prices WHERE language IS NULL OR condition IS NULL');

        // A missing foil flag means a non-foil price
        DB::statement('UPDATE card_prices SET is_foil = false WHERE is_foil IS NULL');

        Schema::table('card_prices', function (Blueprint $table) {
            $table->date('price_date')->nullable()->after('fetched_at');
        });
        DB::statement('UPDATE card_prices SET price_date = fetched_at::date');

        // Keep only the most recent price of each day
        DB::statement(<<<'SQL'
            DELETE FROM card_prices
            WHERE id IN (
                SELECT id
                FROM (
                    SELECT
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY blueprint_id, language, condition, is_foil, price_date
                            ORDER BY fetched_at DESC, id DESC
                        ) AS position_in_day
                    FROM card_prices
                ) ranked_prices
                WHERE position_in_day > 1
            )
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE card_prices
                ALTER COLUMN price_date SET NOT NULL,
                ALTER COLUMN language SET NOT NULL,
                ALTER COLUMN condition SET NOT NULL,
                ALTER COLUMN is_foil SET DEFAULT false,
                ALTER COLUMN is_foil SET NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX card_prices_daily_unique
            ON card_prices (blueprint_id, language, condition, is_foil, price_date)
        SQL);

        // Raw points: keep the first copy of each identical observation
        DB::statement(<<<'SQL'
            DELETE FROM card_price_points
            WHERE id IN (
                SELECT id
                FROM (
                    SELECT
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY blueprint_id, source, language, condition, is_foil, fetched_at
                            ORDER BY id
                        ) AS copy_number
                    FROM card_price_points
                ) numbered_points
                WHERE copy_number > 1
            )
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX card_price_points_snapshot_unique
            ON card_price_points (blueprint_id, source, language, condition, is_foil, fetched_at)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS card_price_points_snapshot_unique');
        DB::statement('DROP INDEX IF EXISTS card_prices_daily_unique');

        DB::statement(<<<'SQL'
            ALTER TABLE card_prices
                ALTER COLUMN language DROP NOT NULL,
                ALTER COLUMN condition DROP NOT NULL,
                ALTER COLUMN is_foil DROP NOT NULL,
                ALTER COLUMN is_foil DROP DEFAULT
        SQL);

        Schema::table('card_prices', function (Blueprint $table) {
            $table->dropColumn('price_date');
        });
    }
};
