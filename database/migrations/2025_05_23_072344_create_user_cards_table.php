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
        Schema::create('user_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('blueprint_id')->constrained()->onDelete('cascade');
            $table->string('condition')->index();
            $table->string('language', 10)->default('en')->index(); // Adjusted length, consider ISO 639-1 or 639-2 codes
            $table->integer('purchase_price_cents')->nullable();
            $table->string('purchase_currency', 3)->default('EUR')->nullable();
            $table->date('acquired_at')->nullable();
            $table->integer('sold_price_cents')->nullable();
            $table->string('sold_currency', 3)->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_cards');
    }
};
