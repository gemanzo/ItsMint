<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sale of one or more copies of a card. The purchase lots it consumed (oldest
 * first) are in lots(); blueprint, condition and language are a snapshot, so the
 * sale stays meaningful after the lots are removed.
 */
final class PortfolioSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_id',
        'user_card_id',
        'blueprint_id',
        'condition',
        'language',
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
        'quantity' => 'integer',
        'unit_purchase_price_cents' => 'integer',
        'unit_sale_price_cents' => 'integer',
    ];

    protected $appends = [
        'realized_profit_cents',
    ];

    /**
     * Realized profit: sale amount minus the purchase cost of the consumed lots.
     * Null when the cost of a consumed lot is unknown (it must not count as 0).
     */
    public function getRealizedProfitCentsAttribute(): ?int
    {
        $saleAmountCents = $this->unit_sale_price_cents * $this->quantity;

        $soldLots = $this->relationLoaded('lots') ? $this->lots : $this->lots()->get();

        if ($soldLots->isEmpty()) {
            // Sales recorded without lot details: use the sale's own purchase price
            if ($this->unit_purchase_price_cents === null) {
                return null;
            }

            return $saleAmountCents - ($this->unit_purchase_price_cents * $this->quantity);
        }

        $purchaseCostCents = 0;
        foreach ($soldLots as $soldLot) {
            if ($soldLot->unit_purchase_price_cents === null) {
                return null;
            }
            $purchaseCostCents += $soldLot->unit_purchase_price_cents * $soldLot->quantity;
        }

        return $saleAmountCents - $purchaseCostCents;
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * First lot consumed by the sale (kept for compatibility), null once removed.
     */
    public function userCard(): BelongsTo
    {
        return $this->belongsTo(UserCard::class);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Purchase lots consumed by the sale, oldest first.
     */
    public function lots(): HasMany
    {
        return $this->hasMany(PortfolioSaleLot::class)->orderBy('id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(PortfolioTransaction::class, 'portfolio_transaction_id');
    }
}
