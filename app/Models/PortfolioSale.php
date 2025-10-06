<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PortfolioSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_id',
        'user_card_id',
        'portfolio_transaction_id',
        'quantity',
        'unit_purchase_price_cents',
        'unit_sale_price_cents',
        'platform',
        'notes',
        'sold_at',
        'acquired_at',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'acquired_at' => 'datetime',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function userCard(): BelongsTo
    {
        return $this->belongsTo(UserCard::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PortfolioTransaction::class, 'portfolio_transaction_id');
    }
}
