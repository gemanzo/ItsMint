<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CardCondition;
use App\Enums\CardLanguage;
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

    /**
     * Calculate estimated price in cents for a given condition and base price.
     *
     * The condition can be written in any accepted spelling (see CardCondition);
     * the adjustment factors live in CardCondition::priceAdjustmentFactor().
     */
    public static function calculateEstimatedPriceCents(int $basePriceCents, string $condition): int
    {
        $adjustmentFactor = CardCondition::fromProviderValue($condition)?->priceAdjustmentFactor() ?? 1.00;

        return (int) round($basePriceCents * $adjustmentFactor);
    }

    /**
     * Always store the canonical condition code ("NM", "LP", ...), whatever spelling
     * is assigned. Controllers normalize validated input already: this guarantees that
     * no other code path can store a different vocabulary.
     */
    public function setConditionAttribute(string $condition): void
    {
        $this->attributes['condition'] = CardCondition::normalize($condition);
    }

    /**
     * Always store the canonical language code ("en", "jp", ...).
     */
    public function setLanguageAttribute(string $language): void
    {
        $this->attributes['language'] = CardLanguage::normalize($language);
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
        // 0 is a real price (free card): only null means "unknown"
        if ($this->purchase_price_cents === null) {
            return null;
        }

        return $this->purchase_price_cents / 100;
    }

    // Mutator for purchase_price (converts float to cents)
    public function setPurchasePriceAttribute(?float $value): void
    {
        $this->attributes['purchase_price_cents'] = self::eurosToCents($value);
    }

    // Accessor for sold_price (converts cents to float)
    public function getSoldPriceAttribute(): ?float
    {
        if ($this->sold_price_cents === null) {
            return null;
        }

        return $this->sold_price_cents / 100;
    }

    // Mutator for sold_price (converts float to cents)
    public function setSoldPriceAttribute(?float $value): void
    {
        $this->attributes['sold_price_cents'] = self::eurosToCents($value);
    }

    /**
     * Convert an amount in euros to integer cents.
     *
     * Rounded, not truncated: floats can't represent most decimal amounts exactly
     * (19.99 * 100 = 1998.9999...), so a plain (int) cast loses a cent. 0 stays 0
     * (a free card), only null means "unknown".
     */
    public static function eurosToCents(?float $amountInEuros): ?int
    {
        if ($amountInEuros === null) {
            return null;
        }

        return (int) round($amountInEuros * 100);
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
            // Filter the already loaded collection for language-specific price, with the
            // same rules as Blueprint::latestPriceForLanguage(): current prices only,
            // most recent day first, then the best condition of that day
            $oldestCurrentPriceDate = CardPrice::oldestCurrentPriceDate();
            $priceModel = $this->blueprint->cardPrices
                ->where('language', strtolower($this->language))
                ->filter(function (CardPrice $cardPrice) use ($oldestCurrentPriceDate) {
                    return ! $cardPrice->is_foil
                        && $cardPrice->price_date->toDateString() >= $oldestCurrentPriceDate;
                })
                ->sortBy([
                    fn (CardPrice $first, CardPrice $second) => $second->price_date <=> $first->price_date,
                    ['condition_priority', 'asc'],
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
        return number_format($valueCents / 100, 2, ',', '.').' €';
    }
}
