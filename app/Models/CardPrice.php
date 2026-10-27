<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardPrice extends Model
{
    use HasFactory;

    /**
     * A price older than this is no longer shown as the current market price:
     * it would look current while the data pipeline stopped updating it.
     * Keep in sync with the card_latest_prices_mv materialized view.
     */
    public const CURRENT_PRICE_MAX_AGE_DAYS = 30;

    protected $fillable = [
        'blueprint_id',
        'price_cents',
        'currency',
        'condition',
        'language',
        'is_foil',
        'source',
        'fetched_at',
        'price_date',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'is_foil' => 'boolean',
        'fetched_at' => 'datetime',
        // Day the price refers to: one price per card, language, condition and foil per day
        'price_date' => 'date',
    ];

    protected static function booted(): void
    {
        // The day of a price defaults to the day it was fetched
        // (the aggregator always sets it explicitly, through upserts)
        static::creating(function (CardPrice $cardPrice) {
            if ($cardPrice->price_date === null && $cardPrice->fetched_at !== null) {
                $cardPrice->price_date = $cardPrice->fetched_at->toDateString();
            }
        });
    }

    /**
     * Oldest price day still considered current (YYYY-MM-DD).
     */
    public static function oldestCurrentPriceDate(): string
    {
        return today()->subDays(self::CURRENT_PRICE_MAX_AGE_DAYS)->toDateString();
    }

    /**
     * Prices usable as the current market price: non-foil and recent enough.
     */
    public function scopeCurrent($query)
    {
        return $query
            ->where($this->qualifyColumn('is_foil'), false)
            ->where($this->qualifyColumn('price_date'), '>=', self::oldestCurrentPriceDate());
    }

    /**
     * Most recent day first, then the best condition of that day (Near Mint first).
     * A recent price always wins over an older one, whatever its condition.
     */
    public function scopeLatestFirst($query)
    {
        return $query
            ->orderByDesc($this->qualifyColumn('price_date'))
            ->orderBy($this->qualifyColumn('condition_priority'))
            ->orderByDesc($this->qualifyColumn('id'));
    }

    public function blueprint()
    {
        return $this->belongsTo(Blueprint::class);
    }
}
