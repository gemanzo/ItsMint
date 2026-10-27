<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_price_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blueprint_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('EUR');
            $table->string('condition', 32);
            $table->string('language', 16);
            $table->boolean('is_foil')->default(false);
            $table->string('source', 64);
            $table->timestampTz('fetched_at')->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['blueprint_id', 'language', 'condition', 'fetched_at'], 'cpp_blueprint_lang_cond_fetched_idx');
            $table->index(['source', 'fetched_at'], 'cpp_source_fetched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_price_points');
    }
};
