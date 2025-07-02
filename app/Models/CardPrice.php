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
    ];

    public function blueprint()
    {
        return $this->belongsTo(Blueprint::class);
    }
}
