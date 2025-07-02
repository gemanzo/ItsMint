<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CardScan extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'blueprint_id',
        'original_filename',
        'file_path',
        'file_hash',
        'ocr_text',
        'ocr_data',
        'detected_cards',
        'confidence_score',
        'status',
        'error_message',
        'source',
        'metadata',
        'processed_at',
    ];

    protected $casts = [
        'ocr_data' => 'array',
        'detected_cards' => 'array',
        'metadata' => 'array',
        'confidence_score' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Check if the scan is completed successfully
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the scan failed
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the scan is still processing
     */
    public function isProcessing(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    /**
     * Get the confidence score as percentage
     */
    public function getConfidencePercentageAttribute(): ?string
    {
        return $this->confidence_score ? $this->confidence_score.'%' : null;
    }

    /**
     * Get the best match from detected cards
     */
    public function getBestMatchAttribute(): ?array
    {
        if (! $this->detected_cards || empty($this->detected_cards)) {
            return null;
        }

        return collect($this->detected_cards)->sortByDesc('confidence')->first();
    }
}
