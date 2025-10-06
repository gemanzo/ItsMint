<?php

declare(strict_types=1);

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
        Schema::create('portfolio_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_transaction_id')->nullable()->constrained('portfolio_transactions')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->integer('unit_purchase_price_cents');
            $table->integer('unit_sale_price_cents');
            $table->string('platform'); // ebay, cardmarket, private, cardtrader, custom
            $table->text('notes')->nullable();
            $table->timestamp('sold_at')->useCurrent();
            $table->timestamp('acquired_at')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'sold_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_sales');
    }
};
