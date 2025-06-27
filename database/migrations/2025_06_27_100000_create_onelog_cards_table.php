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
        Schema::create('onelog_cards', function (Blueprint $table) {
            $table->id();

            // OneLog specific fields (exact mirror of their API)
            $table->string('onelog_id')->unique(); // their "id" field
            $table->string('img_id')->nullable();
            $table->string('series_id');
            $table->string('name');
            $table->string('serial'); // their collector number
            $table->string('rarity')->nullable();
            $table->string('type')->nullable();
            $table->integer('cost')->nullable();
            $table->string('attribute')->nullable();
            $table->integer('power')->nullable();
            $table->string('counter')->nullable();
            $table->jsonb('colors')->nullable(); // card colors as array (supports multicolor)
            $table->string('feature')->nullable(); // archetype
            $table->text('effect')->nullable();
            $table->text('card_sets')->nullable();
            $table->string('trigger')->nullable();
            $table->jsonb('remarks')->nullable();

            // Processed/normalized fields for easier querying
            $table->string('base_serial')->nullable(); // ST01-001 from ST01-001_p3
            $table->string('expansion_code')->nullable(); // ST01, OP01, etc.
            $table->boolean('is_variant')->default(false);
            $table->boolean('is_promo')->default(false);

            $table->timestamps();

            // Indexes for performance
            $table->index('series_id');
            $table->index('serial');
            $table->index('base_serial');
            $table->index('expansion_code');
            $table->index(['series_id', 'serial']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onelog_cards');
    }
};
