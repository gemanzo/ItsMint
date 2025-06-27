<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            // Add product type enum column
            $table->string('product_type', 20)->nullable()->index()->after('ct_category_id');

            // Add computed classification for AI analysis
            $table->string('ai_classification', 20)->nullable()->index()->after('product_type');

            // Add confidence score for AI classification
            $table->decimal('ai_confidence_score', 3, 2)->nullable()->after('ai_classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn(['product_type', 'ai_classification', 'ai_confidence_score']);
        });
    }
};
