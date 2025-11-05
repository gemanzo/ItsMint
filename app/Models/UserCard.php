<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'blueprint_id',
        'condition',
        'language',
        'purchase_price',
        'purchase_price_cents',
        'purchase_currency',
        'acquired_at',
        'sold_price',
        'sold_price_cents',
        'sold_currency',
        'sold_at',
        'notes',
    ];

    protected $casts = [
        'acquired_at' => 'datetime',
        'sold_at' => 'datetime',
        'purchase_price_cents' => 'integer',
        'sold_price_cents' => 'integer',
    ];

    protected $appends = [
        'estimated_value_cents',
    ];

    // Helper for price condition adjustments
    // NM: 0%, LP: -10%, MP: -25%, HP: -50%, DMG: -75%
    // Note: These values should match the frontend constants in resources/js/constants/cardConditions.ts
    protected static array $conditionPriceAdjustments = [
        'NM' => 1.00,  // No change
        'LP' => 0.90,  // -10%
        'MP' => 0.75,  // -25%
        'HP' => 0.50,  // -50%
        'DMG' => 0.25, // -75%
    ];

    protected static array $conditionAbbreviations = [
        'Near Mint' => 'NM',
        'Slightly Played' => 'LP',
        'Lightly Played' => 'LP',
        'Moderately Played' => 'MP',
        'Played' => 'HP',
        'Heavily Played' => 'HP',
        'Poor' => 'DMG',
        'Damaged' => 'DMG',
    ];

    /**
     * Get condition-based price adjustments array
     */
    public static function getConditionPriceAdjustments(): array
    {
        return self::$conditionPriceAdjustments;
    }

    /**
     * Calculate estimated price in cents for a given condition and base price
     */
    public static function calculateEstimatedPriceCents(int $basePriceCents, string $condition): int
    {
        $conditionAbbreviation = self::$conditionAbbreviations[$condition] ?? $condition;
        $adjustmentFactor = self::$conditionPriceAdjustments[$conditionAbbreviation] ?? 1.00;

        return (int) round($basePriceCents * $adjustmentFactor);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    public function portfolios(): BelongsToMany
    {
        return $this->belongsToMany(Portfolio::class, 'portfolio_user_card')
            ->withPivot('quantity', 'added_to_portfolio_at')
            ->withTimestamps();
    }

    // Accessor for purchase_price (converts cents to float)
    public function getPurchasePriceAttribute(): ?float
    {
        return $this->purchase_price_cents ? $this->purchase_price_cents / 100 : null;
    }

    // Mutator for purchase_price (converts float to cents)
    public function setPurchasePriceAttribute(?float $value): void
    {
        $this->attributes['purchase_price_cents'] = $value ? (int) ($value * 100) : null;
    }

    // Accessor for sold_price (converts cents to float)
    public function getSoldPriceAttribute(): ?float
    {
        return $this->sold_price_cents ? $this->sold_price_cents / 100 : null;
    }

    // Mutator for sold_price (converts float to cents)
    public function setSoldPriceAttribute(?float $value): void
    {
        $this->attributes['sold_price_cents'] = $value ? (int) ($value * 100) : null;
    }

    /**
     * Get the estimated value of the card in cents based on its language, condition, and blueprint's latest price.
     * Optimized to use eagerly loaded relationships when available to avoid N+1 queries.
     */
    public function getEstimatedValueCentsAttribute(): ?int
    {
        if (! $this->blueprint) {
            return null;
        }

        $priceModel = null;

        // Try to use eagerly loaded cardPrices relationship if available
        if ($this->blueprint->relationLoaded('cardPrices')) {
            // Filter the already loaded collection for language-specific price
            $priceModel = $this->blueprint->cardPrices
                ->where('language', strtolower($this->language))
                ->sortBy([
                    ['condition_priority', 'asc'],
                    ['fetched_at', 'desc'],
                    ['price_cents', 'desc'],
                ])
                ->first();
        }

        // Fallback to query if not eagerly loaded
        if (! $priceModel) {
            // Prioritize the price for the specific language of the user's card.
            $priceModel = $this->blueprint->latestPriceForLanguage($this->language)->first();
        }

        // If no price is found for the specific language, fallback to the generic latest price.
        if (! $priceModel) {
            $priceModel = $this->blueprint->latestPrice;
        }

        if (! $priceModel || $priceModel->price_cents === null) {
            return null;
        }

        $basePriceCents = $priceModel->price_cents;

        // The condition of the UserCard should be used, not the condition from the fetched price.
        return self::calculateEstimatedPriceCents($basePriceCents, $this->condition);
    }

    /**
     * Get the estimated value formatted as currency.
     */
    public function getEstimatedValueFormattedAttribute(): ?string
    {
        $valueCents = $this->getEstimatedValueCentsAttribute();
        if ($valueCents === null) {
            return null; // Or 'N/A' or some other placeholder
        }

        // Assuming EUR, adapt if currency can vary and needs to be dynamic
        return number_format($valueCents / 100, 2, ',', '.') . ' €';
    }
}
