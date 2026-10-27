<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CardPrice extends Model
{
    use HasFactory;

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

    public function blueprint()
    {
        return $this->belongsTo(Blueprint::class);
    }
}
