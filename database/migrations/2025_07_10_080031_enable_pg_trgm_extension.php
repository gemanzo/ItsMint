<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Enable pg_trgm extension for trigram similarity matching
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        // Create trigram index on ocr_match_string for fast similarity searches
        DB::statement('CREATE INDEX IF NOT EXISTS idx_blueprints_ocr_trigram ON blueprints USING gin (ocr_match_string gin_trgm_ops)');

        // Create additional index for fast LIKE queries on ocr_match_string
        DB::statement('CREATE INDEX IF NOT EXISTS idx_blueprints_ocr_text ON blueprints (ocr_match_string) WHERE ocr_match_string IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes
        DB::statement('DROP INDEX IF EXISTS idx_blueprints_ocr_trigram');
        DB::statement('DROP INDEX IF EXISTS idx_blueprints_ocr_text');

        // Note: We don't drop the extension as it might be used by other tables
        // DB::statement('DROP EXTENSION IF EXISTS pg_trgm');
    }
};
