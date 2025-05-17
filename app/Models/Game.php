<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'ct_id',
        'name',
        'display_name',
    ];

    /**
     * Get all of the expansions for the Game.
     */
    public function expansions(): HasMany
    {
        return $this->hasMany(Expansion::class);
    }
}
