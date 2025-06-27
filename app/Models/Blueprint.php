<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use App\Models\UserCard;
use App\Enums\ProductType;

class Blueprint extends Model
{
    use HasFactory;

    protected $fillable = [
        'expansion_id',
        'ct_id',
        'name',
        'version',
        'ct_category_id',
        'product_type',
        'ai_classification',
        'ai_confidence_score',
        'image_url',
        'scryfall_id',
        'card_market_ids',
        'tcg_player_id',
        'properties',
        // One Piece TCG specific fields
        'colors',
        'card_effect',
        'card_type',
        'card_cost',
        'card_power',
        'card_counter',
        'card_attribute',
        'trigger',
        'note',
        'family',
        'card_number',
        'official_image_url',
        'card_archetype',
        'rarity',
        'onepiece_rarity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'card_market_ids' => 'array',
        'properties' => 'array',
        'colors' => 'array',
        'cost' => 'integer',
        'power' => 'integer',
        'ai_confidence_score' => 'decimal:2',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['category'];

    /**
     * Get the expansion that owns the Blueprint.
     */
    public function expansion(): BelongsTo
    {
        return $this->belongsTo(Expansion::class);
    }

    /**
     * Get all of the prices for the Blueprint.
     */
    public function cardPrices(): HasMany
    {
        return $this->hasMany(CardPrice::class);
    }

    /**
     * Get the latest price for the Blueprint.
     */
    public function latestPrice()
    {
        return $this->hasOne(CardPrice::class)->latestOfMany('fetched_at');
    }

    /**
     * OneLog cards related to this blueprint.
     */
    public function oneLogCards(): BelongsToMany
    {
        return $this->belongsToMany(OneLogCard::class, 'blueprint_onelog_relations', 'blueprint_id', 'onelog_card_id')
            ->withPivot(['match_type', 'confidence_score', 'match_metadata', 'is_verified', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get the category for the Blueprint.
     *
     * @return object|null
     */
    public function getCategoryAttribute(): ?object
    {
        if (is_null($this->ct_category_id)) {
            return null;
        }

        $cardtraderCategoriesConfig = config('services.cardtrader');
        $categoryNameMapping = [];
        if (is_array($cardtraderCategoriesConfig)) {
            foreach ($cardtraderCategoriesConfig as $key => $value) {
                if (Str::endsWith($key, '_category_id') && is_numeric($value)) {
                    // This logic attempts to create a readable name from config keys
                    // Example: 'one_piece_single_card_category_id' -> 'One Piece Single Card'
                    // It might need adjustment if config keys have different patterns.
                    $name = Str::title(str_replace('_', ' ', Str::remove('_category_id', $key)));
                    $categoryNameMapping[(int)$value] = $name;
                }
            }
        }

        $categoryName = $categoryNameMapping[$this->ct_category_id] ?? null;

        if ($categoryName) {
            return (object) [
                'id' => $this->ct_category_id,
                'name' => $categoryName,
            ];
        }

        // Fallback if no name found in config, return ID as name
        return (object) [
            'id' => $this->ct_category_id,
            'name' => (string) $this->ct_category_id,
        ];
    }

    /**
     * Get the collector number from properties or card_number field.
     */
    public function getCollectorNumberAttribute(): ?string
    {
        // Check new card_number field first, then fallback to properties
        return $this->card_number ?? $this->properties['fixed_properties']['collector_number'] ?? null;
    }

    /**
     * Get the rarity from properties.
     */
    public function getRarityAttribute(): ?string
    {
        return $this->properties['fixed_properties']['onepiece_rarity'] ?? null;
    }

    /**
     * Check if the card is multi-colored.
     */
    public function getIsMultiColoredAttribute(): bool
    {
        return is_array($this->colors) && count($this->colors) > 1;
    }

    /**
     * Get primary color for single-colored cards.
     */
    public function getPrimaryColorAttribute(): ?string
    {
        if (!is_array($this->colors) || empty($this->colors)) {
            return null;
        }

        return $this->colors[0];
    }

    /**
     * Check if the card has a counter value.
     */
    public function getHasCounterAttribute(): bool
    {
        return !empty($this->counter) && $this->counter !== '0';
    }

    /**
     * Get formatted cost display.
     */
    public function getFormattedCostAttribute(): string
    {
        if (is_null($this->cost)) {
            return '-';
        }

        return (string) $this->cost;
    }

    /**
     * Get formatted power display.
     */
    public function getFormattedPowerAttribute(): string
    {
        if (is_null($this->power)) {
            return '-';
        }

        return number_format($this->power);
    }

    /**
     * Get formatted counter display.
     */
    public function getFormattedCounterAttribute(): string
    {
        if (empty($this->counter)) {
            return '-';
        }

        return $this->counter;
    }

    public function userCards(): HasMany
    {
        return $this->hasMany(UserCard::class);
    }

    /**
     * Get the product type enum instance.
     */
    public function getProductTypeEnumAttribute(): ?ProductType
    {
        if (!$this->product_type) {
            return null;
        }

        return ProductType::tryFrom($this->product_type);
    }

    /**
     * Check if this blueprint is a single card.
     */
    public function getIsSingleCardAttribute(): bool
    {
        // Use product_type first, fallback to enum mapping if available
        if ($this->product_type) {
            return $this->product_type_enum?->isSingleCard() ?? false;
        }

        // Fallback to ProductType enum mapping based on ct_category_id
        return ProductType::fromCategoryId($this->ct_category_id)->isSingleCard();
    }

    /**
     * Get the AI classification confidence level.
     */
    public function getConfidenceLevelAttribute(): string
    {
        if (is_null($this->ai_confidence_score)) {
            return 'not_classified';
        }

        $score = (float) $this->ai_confidence_score;

        if ($score >= 0.8) {
            return 'high';
        } elseif ($score >= 0.5) {
            return 'medium';
        } elseif ($score >= 0.0) {
            return 'low';
        } else {
            return 'uncertain';
        }
    }

    /**
     * Scope for single cards only.
     */
    public function scopeSingleCards($query)
    {
        return $query->where(function ($q) {
            $q->where('product_type', 'single_card')
                ->orWhereIn('ct_category_id', ProductType::getSingleCardCategoryIds());
        });
    }

    /**
     * Scope for products only (non-cards).
     */
    public function scopeProducts($query)
    {
        return $query->where(function ($q) {
            $q->whereNotIn('product_type', ['single_card', 'don_token'])
                ->orWhereNotIn('ct_category_id', ProductType::getSingleCardCategoryIds());
        });
    }
}
