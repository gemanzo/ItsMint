<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase lots (FIFO).
 *
 * Every user_cards row is now a purchase lot: one acquisition with its own price,
 * date and quantity (the quantity left lives in portfolio_user_card). Adding the
 * same card again creates a new lot instead of overwriting the cost of the copies
 * already owned.
 *
 * - portfolio_sale_lots: which lots each sale consumed (oldest first), with a
 *   snapshot of their purchase price and date, so realized profit and history
 *   survive when a lot is later removed;
 * - portfolio_sales: snapshot of card, condition and language; user_card_id no
 *   longer cascades (removing a card deleted its sales history); the purchase
 *   price can be unknown (null) instead of being counted as 0;
 * - portfolio_transactions.user_card_id: the lot a purchase transaction belongs to,
 *   so the cash ledger follows corrections of the lot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_sale_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_sale_id')->constrained('portfolio_sales')->cascadeOnDelete();
            // Null once the lot itself is removed: the snapshot below keeps the history
            $table->foreignId('user_card_id')->nullable()->constrained('user_cards')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->integer('unit_purchase_price_cents')->nullable();
            $table->date('acquired_at')->nullable();
            $table->timestamps();
        });

        Schema::table('portfolio_sales', function (Blueprint $table) {
            $table->foreignId('blueprint_id')->nullable()->after('user_card_id')->constrained('blueprints')->restrictOnDelete();
            $table->string('condition')->nullable()->after('blueprint_id');
            $table->string('language', 10)->nullable()->after('condition');
        });

        // Snapshot and allocation of the sales recorded so far (one lot each)
        DB::statement(<<<'SQL'
            UPDATE portfolio_sales
            SET blueprint_id = user_cards.blueprint_id,
                condition = user_cards.condition,
                language = user_cards.language
            FROM user_cards
            WHERE user_cards.id = portfolio_sales.user_card_id
        SQL);
        DB::statement(<<<'SQL'
            INSERT INTO portfolio_sale_lots (portfolio_sale_id, user_card_id, quantity, unit_purchase_price_cents, acquired_at, created_at, updated_at)
            SELECT id, user_card_id, quantity, unit_purchase_price_cents, acquired_at::date, created_at, updated_at
            FROM portfolio_sales
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE portfolio_sales
                ALTER COLUMN blueprint_id SET NOT NULL,
                ALTER COLUMN condition SET NOT NULL,
                ALTER COLUMN language SET NOT NULL,
                ALTER COLUMN unit_purchase_price_cents DROP NOT NULL,
                ALTER COLUMN user_card_id DROP NOT NULL
        SQL);

        Schema::table('portfolio_sales', function (Blueprint $table) {
            $table->dropForeign(['user_card_id']);
            $table->foreign('user_card_id')->references('id')->on('user_cards')->nullOnDelete();
        });

        Schema::table('portfolio_transactions', function (Blueprint $table) {
            $table->foreignId('user_card_id')->nullable()->after('portfolio_id')->constrained('user_cards')->nullOnDelete();
        });
        DB::statement(<<<'SQL'
            UPDATE portfolio_transactions
            SET user_card_id = (meta->>'user_card_id')::bigint
            WHERE type = 'purchase'
              AND meta->>'user_card_id' IS NOT NULL
              AND EXISTS (SELECT 1 FROM user_cards WHERE user_cards.id = (portfolio_transactions.meta->>'user_card_id')::bigint)
        SQL);

        // Lots of the same card, oldest first (FIFO lookups)
        Schema::table('user_cards', function (Blueprint $table) {
            $table->index(['user_id', 'blueprint_id', 'condition', 'language', 'acquired_at'], 'user_cards_lot_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_cards', function (Blueprint $table) {
            $table->dropIndex('user_cards_lot_lookup_index');
        });

        Schema::table('portfolio_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_card_id');
        });

        // Sales without a lot can't satisfy the previous NOT NULL / CASCADE schema
        DB::statement('DELETE FROM portfolio_sales WHERE user_card_id IS NULL');
        DB::statement('UPDATE portfolio_sales SET unit_purchase_price_cents = 0 WHERE unit_purchase_price_cents IS NULL');

        Schema::table('portfolio_sales', function (Blueprint $table) {
            $table->dropForeign(['user_card_id']);
        });
        DB::statement(<<<'SQL'
            ALTER TABLE portfolio_sales
                ALTER COLUMN user_card_id SET NOT NULL,
                ALTER COLUMN unit_purchase_price_cents SET NOT NULL
        SQL);
        Schema::table('portfolio_sales', function (Blueprint $table) {
            $table->foreign('user_card_id')->references('id')->on('user_cards')->cascadeOnDelete();
            $table->dropConstrainedForeignId('blueprint_id');
            $table->dropColumn(['condition', 'language']);
        });

        Schema::dropIfExists('portfolio_sale_lots');
    }
};
