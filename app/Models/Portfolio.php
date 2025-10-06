<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'cash_linked',
    ];

    protected $casts = [
        'cash_linked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function userCards(): BelongsToMany
    {
        return $this->belongsToMany(UserCard::class, 'portfolio_user_card')
            ->withPivot('quantity', 'added_to_portfolio_at')
            ->withTimestamps();
    }
}
