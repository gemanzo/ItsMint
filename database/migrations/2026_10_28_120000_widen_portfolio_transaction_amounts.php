<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A transaction amount is unit price x quantity: with a 32 bit integer, an expensive
 * card bought in quantity overflowed (after the portfolio had already been changed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_transactions', function (Blueprint $table) {
            $table->bigInteger('amount_cents')->change();
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_transactions', function (Blueprint $table) {
            $table->integer('amount_cents')->change();
        });
    }
};
