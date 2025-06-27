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
        Schema::create('blueprint_onelog_relations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('blueprint_id')->constrained('blueprints')->onDelete('cascade');
            $table->foreignId('onelog_card_id')->constrained('onelog_cards')->onDelete('cascade');

            // Matching metadata
            $table->string('match_type'); // exact, fuzzy_name, base_serial, manual, etc.
            $table->decimal('confidence_score', 3, 2)->nullable(); // 0.00 to 1.00
            $table->json('match_metadata')->nullable(); // details about how the match was made
            $table->boolean('is_verified')->default(false); // manual verification flag
            $table->text('notes')->nullable();

            $table->timestamps();

            // Ensure unique relationships
            $table->unique(['blueprint_id', 'onelog_card_id']);

            // Indexes
            $table->index('match_type');
            $table->index('confidence_score');
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprint_onelog_relations');
    }
};
