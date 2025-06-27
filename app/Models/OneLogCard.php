<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class OneLogCard extends Model
{
    use HasFactory;

    protected $table = 'onelog_cards';

    protected $fillable = [
        'onelog_id',
        'img_id',
        'series_id',
        'name',
        'serial',
        'rarity',
        'type',
        'cost',
        'attribute',
        'power',
        'counter',
        'colors',
        'feature',
        'effect',
        'card_sets',
        'trigger',
        'remarks',
        'base_serial',
        'expansion_code',
        'is_variant',
        'is_promo',
    ];

    protected $casts = [
        'colors' => 'json',
        'remarks' => 'json',
        'cost' => 'integer',
        'power' => 'integer',
        'is_variant' => 'boolean',
        'is_promo' => 'boolean',
    ];

    /**
     * Blueprints related to this OneLog card.
     */
    public function blueprints(): BelongsToMany
    {
        return $this->belongsToMany(Blueprint::class, 'blueprint_onelog_relations', 'onelog_card_id', 'blueprint_id')
            ->withPivot(['match_type', 'confidence_score', 'match_metadata', 'is_verified', 'notes'])
            ->withTimestamps();
    }

    /**
     * Scope to filter by expansion code.
     */
    public function scopeByExpansion($query, string $expansionCode)
    {
        return $query->where('expansion_code', $expansionCode);
    }

    /**
     * Scope to filter variants.
     */
    public function scopeVariants($query)
    {
        return $query->where('is_variant', true);
    }

    /**
     * Scope to filter promos.
     */
    public function scopePromos($query)
    {
        return $query->where('is_promo', true);
    }

    /**
     * Scope to filter unmatched cards.
     */
    public function scopeUnmatched($query)
    {
        return $query->whereDoesntHave('blueprints');
    }

    /**
     * Get the primary color from colors array.
     */
    public function getPrimaryColorAttribute(): ?string
    {
        $colors = $this->colors;
        return !empty($colors) ? $colors[0] : null;
    }

    /**
     * Check if card is multicolor.
     */
    public function getIsMulticolorAttribute(): bool
    {
        $colors = $this->colors;
        return is_array($colors) && count($colors) > 1;
    }
}
