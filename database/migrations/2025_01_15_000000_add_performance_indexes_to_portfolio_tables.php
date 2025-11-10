<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Index for portfolio_user_card aggregations (quantity calculations)
        if (! $this->indexExists('portfolio_user_card', 'idx_portfolio_user_card_portfolio_quantity')) {
            DB::statement('CREATE INDEX idx_portfolio_user_card_portfolio_quantity ON portfolio_user_card (portfolio_id, quantity)');
        }

        // Composite index for portfolio_user_card lookups with quantity
        if (! $this->indexExists('portfolio_user_card', 'idx_portfolio_user_card_composite')) {
            DB::statement('CREATE INDEX idx_portfolio_user_card_composite ON portfolio_user_card (portfolio_id, user_card_id, quantity)');
        }

        // Index for user_cards acquired_at (temporal filters)
        if (! $this->indexExists('user_cards', 'idx_user_cards_acquired_at')) {
            DB::statement('CREATE INDEX idx_user_cards_acquired_at ON user_cards (acquired_at)');
        }

        // Index for user_cards user_id + acquired_at (common query pattern)
        if (! $this->indexExists('user_cards', 'idx_user_cards_user_acquired')) {
            DB::statement('CREATE INDEX idx_user_cards_user_acquired ON user_cards (user_id, acquired_at)');
        }

        // Index for portfolio_sales sold_at ordering (already has composite but add single for flexibility)
        if (! $this->indexExists('portfolio_sales', 'idx_portfolio_sales_sold_at')) {
            DB::statement('CREATE INDEX idx_portfolio_sales_sold_at ON portfolio_sales (sold_at DESC)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_portfolio_user_card_portfolio_quantity');
        DB::statement('DROP INDEX IF EXISTS idx_portfolio_user_card_composite');
        DB::statement('DROP INDEX IF EXISTS idx_user_cards_acquired_at');
        DB::statement('DROP INDEX IF EXISTS idx_user_cards_user_acquired');
        DB::statement('DROP INDEX IF EXISTS idx_portfolio_sales_sold_at');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $result = DB::selectOne(
            "SELECT COUNT(*) as count FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $indexName]
        );

        return $result->count > 0;
    }
};

