<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a catalog card (or its expansion/game, which cascade to it) deleted
 * every user's copies of that card, with their portfolio entries and sales
 * history. User data must never disappear because of a catalog change: the
 * deletion of a card that someone owns is now refused by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_cards', function (Blueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->foreign('blueprint_id')->references('id')->on('blueprints')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_cards', function (Blueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->foreign('blueprint_id')->references('id')->on('blueprints')->cascadeOnDelete();
        });
    }
};
