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
            $table->jsonb('colors')->nullable(); // ["Red", "Blue"]
            $table->text('card_effect')->nullable(); // Full ability text
            $table->string('card_archetype')->nullable(); // Land of Wano, etc.
            $table->integer('card_cost')->nullable();
            $table->integer('card_power')->nullable();
            $table->string('card_counter')->nullable();
            $table->string('card_attribute')->nullable();
            $table->string('card_life')->nullable();
            $table->string('card_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn('colors');
            $table->dropColumn('card_effect');
            $table->dropColumn('card_archetype');
            $table->dropColumn('card_cost');
            $table->dropColumn('card_power');
            $table->dropColumn('card_counter');
            $table->dropColumn('card_attribute');
            $table->dropColumn('card_life');
            $table->dropColumn('card_type');
        });
    }
};
