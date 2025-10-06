<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioSale;
use App\Models\PortfolioTransaction;
use App\Models\UserCard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PortfolioTradingService
{
    public function sell(
        Portfolio $portfolio,
        UserCard $userCard,
        int $quantity,
        int $unitSalePriceCents,
        string $platform,
        ?string $notes = null,
        ?\Carbon\Carbon $soldAt = null
    ): PortfolioSale {
        $pivot = $portfolio->userCards()->where('user_card_id', $userCard->id)->first();
        if (! $pivot) {
            throw ValidationException::withMessages(['user_card_id' => 'Card not in portfolio.']);
        }
        $currentQty = (int) ($pivot->pivot->quantity ?? 0);
        if ($quantity < 1 || $quantity > $currentQty) {
            throw ValidationException::withMessages(['quantity' => 'Invalid quantity.']);
        }

        $soldAt = $soldAt ?? now();

        return DB::transaction(function () use ($portfolio, $userCard, $quantity, $unitSalePriceCents, $platform, $notes, $soldAt, $currentQty) {
            $newQty = $currentQty - $quantity;
            if ($newQty > 0) {
                $portfolio->userCards()->updateExistingPivot($userCard->id, ['quantity' => $newQty]);
            } else {
                $portfolio->userCards()->detach($userCard->id);
            }

            $unitPurchaseCents = (int) ($userCard->purchase_price_cents ?? 0);

            $tx = null;
            if ($portfolio->cash_linked) {
                $tx = PortfolioTransaction::create([
                    'portfolio_id' => $portfolio->id,
                    'type' => PortfolioTransaction::TYPE_SALE,
                    'amount_cents' => $unitSalePriceCents * $quantity,
                    'currency' => 'EUR',
                    'platform' => $platform,
                    'notes' => $notes,
                    'meta' => [
                        'user_card_id' => $userCard->id,
                        'quantity' => $quantity,
                        'unit_sale_price_cents' => $unitSalePriceCents,
                        'unit_purchase_price_cents' => $unitPurchaseCents,
                    ],
                    'occurred_at' => $soldAt,
                ]);
            }

            $sale = PortfolioSale::create([
                'portfolio_id' => $portfolio->id,
                'user_card_id' => $userCard->id,
                'portfolio_transaction_id' => $tx?->id,
                'quantity' => $quantity,
                'unit_purchase_price_cents' => $unitPurchaseCents,
                'unit_sale_price_cents' => $unitSalePriceCents,
                'platform' => $platform,
                'notes' => $notes,
                'sold_at' => $soldAt,
                'acquired_at' => $userCard->acquired_at,
            ]);

            return $sale;
        });
    }

    public function recordPurchase(Portfolio $portfolio, UserCard $userCard, int $quantity, ?\Carbon\Carbon $occurredAt = null): void
    {
        if ($portfolio->cash_linked) {
            PortfolioTransaction::create([
                'portfolio_id' => $portfolio->id,
                'type' => PortfolioTransaction::TYPE_PURCHASE,
                'amount_cents' => -1 * ((int) ($userCard->purchase_price_cents ?? 0) * $quantity),
                'currency' => 'EUR',
                'platform' => null,
                'notes' => 'Card purchase (added to portfolio)',
                'meta' => [
                    'user_card_id' => $userCard->id,
                    'quantity' => $quantity,
                    'unit_purchase_price_cents' => (int) ($userCard->purchase_price_cents ?? 0),
                ],
                'occurred_at' => $occurredAt ?? now(),
            ]);
        }
    }
}
