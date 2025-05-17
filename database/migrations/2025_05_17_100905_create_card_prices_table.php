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
        Schema::create('card_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained('blueprints')->onDelete('cascade');
            $table->integer('price_cents');
            $table->string('currency', 3);
            $table->string('condition')->nullable();
            $table->string('language', 5)->nullable(); // e.g., 'en', 'it', 'zh-CN'
            $table->boolean('is_foil')->nullable();
            $table->string('source')->default('cardtrader');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->index(['blueprint_id', 'fetched_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_prices');
    }
};
