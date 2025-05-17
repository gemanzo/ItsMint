<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'image_url',
        'scryfall_id',
        'card_market_ids',
        'tcg_player_id',
        'properties',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'card_market_ids' => 'array',
        'properties' => 'array',
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
}
