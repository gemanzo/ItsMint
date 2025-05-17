<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Expansion extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'ct_id',
        'code',
        'name',
    ];

    /**
     * Get the game that owns the Expansion.
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Get all of the blueprints for the Expansion.
     */
    public function blueprints(): HasMany
    {
        return $this->hasMany(Blueprint::class);
    }
}
