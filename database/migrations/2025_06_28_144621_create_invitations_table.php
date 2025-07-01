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
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // Unique invitation code
            $table->string('email')->nullable(); // Optional: bind to specific email
            $table->string('invited_by_name')->nullable(); // Who sent the invite
            $table->string('invited_by_email')->nullable(); // Inviter's email
            $table->integer('max_uses')->default(1); // How many times can be used
            $table->integer('used_count')->default(0); // How many times has been used
            $table->timestamp('expires_at')->nullable(); // Expiration date
            $table->timestamp('used_at')->nullable(); // When it was used
            $table->foreignId('used_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // Who used it
            $table->json('metadata')->nullable(); // Additional data (features, notes, etc.)
            $table->boolean('is_active')->default(true); // Can be disabled
            $table->enum('type', ['alpha', 'beta', 'admin'])->default('alpha'); // Type of invitation
            $table->timestamps();

            $table->index(['code', 'is_active']);
            $table->index(['email', 'is_active']);
            $table->index(['expires_at', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
