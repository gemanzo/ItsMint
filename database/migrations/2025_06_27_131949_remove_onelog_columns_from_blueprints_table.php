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
            // Remove OneLog-specific columns that are now available through relationships
            $table->dropColumn([
                'card_life',
                'colors',
                'card_effect',
                'card_archetype',
                'card_cost',
                'card_power',
                'card_counter',
                'card_attribute',
                'card_type',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            // Restore the columns if needed
            $table->string('card_life')->nullable();
            $table->json('colors')->nullable();
            $table->text('card_effect')->nullable();
            $table->string('card_archetype')->nullable();
            $table->integer('card_cost')->nullable();
            $table->integer('card_power')->nullable();
            $table->string('card_counter')->nullable();
            $table->string('card_attribute')->nullable();
            $table->string('card_type')->nullable();
        });
    }
};
