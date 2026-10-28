<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quantity of one purchase lot consumed by a sale (FIFO), with a snapshot of the
 * lot's purchase price and date: the history stays correct if the lot is removed.
 */
final class PortfolioSaleLot extends Model
{
    protected $fillable = [
        'portfolio_sale_id',
        'user_card_id',
        'quantity',
        'unit_purchase_price_cents',
        'acquired_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_purchase_price_cents' => 'integer',
        'acquired_at' => 'date',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(PortfolioSale::class, 'portfolio_sale_id');
    }

    /**
     * The purchase lot, null when it has been removed since.
     */
    public function userCard(): BelongsTo
    {
        return $this->belongsTo(UserCard::class);
    }
}
