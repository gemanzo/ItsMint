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
        Schema::create('blueprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expansion_id')->constrained('expansions')->onDelete('cascade');
            $table->unsignedBigInteger('ct_id')->unique()->comment('CardTrader Blueprint ID');
            $table->string('name');
            $table->string('version')->nullable();
            $table->unsignedBigInteger('ct_category_id')->comment('CardTrader Category ID');
            $table->string('image_url')->nullable();
            $table->string('scryfall_id')->nullable();
            $table->json('card_market_ids')->nullable();
            $table->string('tcg_player_id')->nullable();
            $table->json('properties')->nullable()->comment('Editable properties from CardTrader');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
