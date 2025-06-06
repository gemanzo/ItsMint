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
        Schema::create('card_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('blueprint_id')->nullable()->constrained()->onDelete('set null');
            $table->string('original_filename');
            $table->string('file_path');
            $table->string('file_hash')->index();
            $table->text('ocr_text')->nullable();
            $table->json('ocr_data')->nullable()->comment('Full OCR response with confidence scores');
            $table->json('detected_cards')->nullable()->comment('Array of detected card matches');
            $table->decimal('confidence_score', 5, 2)->nullable()->comment('Overall confidence score 0-100');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->enum('source', ['upload', 'camera'])->default('upload');
            $table->json('metadata')->nullable()->comment('Image metadata, dimensions, etc.');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['blueprint_id', 'confidence_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_scans');
    }
};
