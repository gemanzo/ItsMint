<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

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
        'ocr_match_string',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'card_market_ids' => 'array',
        'properties' => 'array',
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
     * Now returns the best condition price (Near Mint first, then highest price within condition).
     */
    public function latestPrice()
    {
        return $this->hasOne(CardPrice::class)
            ->selectRaw('*, CASE 
                WHEN condition = ? THEN 1
                WHEN condition = ? THEN 2  
                WHEN condition = ? THEN 3
                WHEN condition = ? THEN 4
                WHEN condition = ? THEN 5
                ELSE 6
            END as condition_priority', [
                'Near Mint',
                'Slightly Played',
                'Moderately Played',
                'Played',
                'Poor',
            ])
            ->orderByRaw('condition_priority ASC, price_cents DESC, fetched_at DESC');
    }

    /**
     * Get prices grouped by language.
     */
    public function pricesByLanguage()
    {
        return $this->cardPrices()
            ->orderBy('language')
            ->orderBy('condition')
            ->orderBy('fetched_at', 'desc');
    }

    /**
     * Get the latest price for a specific language.
     */
    public function latestPriceForLanguage(string $language)
    {
        return $this->hasOne(CardPrice::class)
            ->where('language', strtolower($language))
            ->latestOfMany('fetched_at');
    }

    /**
     * Get the latest price for a specific language and condition.
     */
    public function latestPriceForLanguageAndCondition(string $language, string $condition)
    {
        return $this->hasOne(CardPrice::class)
            ->where('language', strtolower($language))
            ->where('condition', $condition)
            ->latestOfMany('fetched_at');
    }

    /**
     * Get all latest prices by language (one per language).
     */
    public function latestPricesByLanguage()
    {
        return $this->cardPrices()
            ->selectRaw('*, ROW_NUMBER() OVER (PARTITION BY language ORDER BY fetched_at DESC) as rn')
            ->havingRaw('rn = 1')
            ->orderBy('language');
    }

    /**
     * Get cheapest price across all languages for a given condition.
     */
    public function cheapestPriceForCondition(string $condition)
    {
        return $this->hasOne(CardPrice::class)
            ->where('condition', $condition)
            ->orderBy('price_cents', 'asc')
            ->orderBy('fetched_at', 'desc');
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
     * Get language variants of this blueprint (same card, different languages).
     */
    public function languageVariants()
    {
        $collectorNumber = $this->collector_number;

        if (! $collectorNumber) {
            return static::where('id', '!=', $this->id)
                ->where('name', $this->name)
                ->where('ct_category_id', $this->ct_category_id)
                ->where('expansion_id', $this->expansion_id);
        }

        return static::where('id', '!=', $this->id)
            ->where(function ($query) use ($collectorNumber) {
                $query->whereRaw("properties->'fixed_properties'->>'collector_number' = ?", [$collectorNumber])
                    ->orWhere(function ($subQuery) {
                        $subQuery->where('name', $this->name)
                            ->where('ct_category_id', $this->ct_category_id);
                    });
            });
    }

    /**
     * Get all blueprints (including this one) for the same card in different languages.
     */
    public function allLanguageVersions()
    {
        $collectorNumber = $this->collector_number;

        if (! $collectorNumber) {
            return static::where('name', $this->name)
                ->where('ct_category_id', $this->ct_category_id)
                ->where('expansion_id', $this->expansion_id);
        }

        return static::where(function ($query) use ($collectorNumber) {
            $query->whereRaw("properties->'fixed_properties'->>'collector_number' = ?", [$collectorNumber])
                ->orWhere(function ($subQuery) {
                    $subQuery->where('name', $this->name)
                        ->where('ct_category_id', $this->ct_category_id);
                });
        });
    }

    /**
     * Get the primary language for this blueprint from properties.
     */
    public function getPrimaryLanguageAttribute(): ?string
    {
        $editableProps = $this->properties['editable_properties'] ?? [];

        foreach ($editableProps as $prop) {
            if (isset($prop['name']) && str_contains($prop['name'], 'onepiece_language')) {
                return $prop['default_value'] ?? null;
            }
            if (isset($prop['name']) && str_contains($prop['name'], 'language')) {
                return $prop['default_value'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get the full price history from all language variants.
     */
    public function getPriceHistoryAttribute()
    {
        $allVersions = $this->allLanguageVersions()->with('cardPrices')->get();
        $allPrices = collect();

        foreach ($allVersions as $blueprint) {
            $allPrices = $allPrices->merge($blueprint->cardPrices);
        }

        return $allPrices->sortBy('fetched_at')->values();
    }

    /**
     * Get the list of available languages for this card's prices.
     * This returns languages that have actual price data in the CardPrice table.
     */
    public function getAvailableLanguagesAttribute()
    {
        return $this->cardPrices()
            ->select('language')
            ->distinct()
            ->pluck('language')
            ->filter() // Remove any null/empty languages
            ->sort()
            ->values();
    }

    /**
     * Get aggregated prices from all language variants.
     */
    public function getAllLanguagesPrices(): array
    {
        $allVersions = $this->allLanguageVersions()->with('cardPrices')->get();
        $pricesByLanguage = [];

        foreach ($allVersions as $blueprint) {
            $language = $blueprint->primary_language ?? 'unknown';

            if (! isset($pricesByLanguage[$language])) {
                $pricesByLanguage[$language] = [
                    'blueprint_id' => $blueprint->id,
                    'ct_id' => $blueprint->ct_id,
                    'language' => $language,
                    'prices' => [],
                ];
            }

            foreach ($blueprint->cardPrices as $price) {
                $pricesByLanguage[$language]['prices'][] = $price;
            }
        }

        return $pricesByLanguage;
    }

    /**
     * Check if this blueprint has language variants.
     */
    public function hasLanguageVariants(): bool
    {
        return $this->languageVariants()->exists();
    }

    /**
     * Get the category for the Blueprint.
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
                    $categoryNameMapping[(int) $value] = $name;
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
     * Get the collector number from properties.
     */
    public function getCollectorNumberAttribute(): ?string
    {
        return $this->properties['fixed_properties']['collector_number'] ?? null;
    }

    /**
     * Get the rarity from properties.
     */
    public function getRarityAttribute(): ?string
    {
        return $this->properties['fixed_properties']['onepiece_rarity'] ?? null;
    }

    /**
     * Get the primary OneLog card (best match).
     */
    public function getPrimaryOneLogCardAttribute(): ?OneLogCard
    {
        return $this->oneLogCards()
            ->orderByPivot('confidence_score', 'desc')
            ->orderByPivot('is_verified', 'desc')
            ->first();
    }

    /**
     * Get card data from OneLog relationship.
     */
    public function getOneLogDataAttribute(): ?object
    {
        $card = $this->getPrimaryOneLogCardAttribute();

        if (! $card) {
            return null;
        }

        return (object) [
            'colors' => $card->colors,
            'card_effect' => $card->effect,
            'card_type' => $card->type,
            'card_cost' => $card->cost,
            'card_power' => $card->power,
            'card_counter' => $card->counter,
            'card_attribute' => $card->attribute,
            'trigger' => $card->trigger,
            'rarity' => $card->rarity,
            'serial' => $card->serial,
            'feature' => $card->feature,
        ];
    }

    /**
     * Check if the card is multi-colored (from OneLog data).
     */
    public function getIsMultiColoredAttribute(): bool
    {
        $oneLogData = $this->getOneLogDataAttribute();
        if (! $oneLogData || ! $oneLogData->colors) {
            return false;
        }

        return is_array($oneLogData->colors) && count($oneLogData->colors) > 1;
    }

    /**
     * Get primary color for single-colored cards (from OneLog data).
     */
    public function getPrimaryColorAttribute(): ?string
    {
        $oneLogData = $this->getOneLogDataAttribute();
        if (! $oneLogData || ! $oneLogData->colors || ! is_array($oneLogData->colors) || empty($oneLogData->colors)) {
            return null;
        }

        return $oneLogData->colors[0];
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
        if (! $this->product_type) {
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
            $enum = $this->getProductTypeEnumAttribute();

            return $enum ? $enum->isSingleCard() : false;
        }

        // Fallback to ProductType enum mapping based on ct_category_id
        return ProductType::fromCategoryId($this->ct_category_id)->isSingleCard();
    }

    /**
     * Check if this blueprint is a promo card.
     */
    public function getIsPromoCardAttribute(): bool
    {
        return $this->properties['fixed_properties']['onepiece_rarity'] == 'Promo';
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
