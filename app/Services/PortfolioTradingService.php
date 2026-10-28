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
    /**
     * Restrict the query to cards with the same purchase price.
     *
     * A card with an unknown purchase price (null) must match the other unknown ones:
     * comparing with 0 found nothing, so those cards could never be sold.
     */
    private function whereSamePurchasePrice($userCardsQuery, ?int $purchasePriceCents)
    {
        if ($purchasePriceCents === null) {
            return $userCardsQuery->whereNull('user_cards.purchase_price_cents');
        }

        return $userCardsQuery->where('user_cards.purchase_price_cents', $purchasePriceCents);
    }

    public function sell(
        Portfolio $portfolio,
        UserCard $userCard,
        int $quantity,
        int $unitSalePriceCents,
        string $platform,
        ?string $notes = null,
        ?\Carbon\Carbon $soldAt = null
    ): PortfolioSale {
        // Verify the card is in the portfolio
        $pivot = $portfolio->userCards()->where('user_card_id', $userCard->id)->first();
        if (! $pivot) {
            throw ValidationException::withMessages(['user_card_id' => 'Card not in portfolio.']);
        }

        // Calculate total available quantity for this card type (same blueprint, condition, language, purchase_price)
        // This handles cases where multiple UserCard entries exist for the same card type
        $totalAvailableQty = $this->whereSamePurchasePrice(
            $portfolio->userCards()
                ->where('blueprint_id', $userCard->blueprint_id)
                ->where('condition', $userCard->condition)
                ->where('language', $userCard->language),
            $userCard->purchase_price_cents
        )->sum('portfolio_user_card.quantity');

        if ($quantity < 1 || $quantity > $totalAvailableQty) {
            throw ValidationException::withMessages(['quantity' => 'Invalid quantity.']);
        }

        $soldAt = $soldAt ?? now();

        return DB::transaction(function () use ($portfolio, $userCard, $quantity, $unitSalePriceCents, $platform, $notes, $soldAt) {
            // Find all UserCard entries that match this card type
            $matchingCards = $this->whereSamePurchasePrice(
                $portfolio->userCards()
                    ->where('blueprint_id', $userCard->blueprint_id)
                    ->where('condition', $userCard->condition)
                    ->where('language', $userCard->language),
                $userCard->purchase_price_cents
            )->get();

            $remainingToSell = $quantity;

            // Sell from matching cards until we reach the desired quantity
            foreach ($matchingCards as $matchingCard) {
                if ($remainingToSell <= 0) {
                    break;
                }

                $availableQty = (int) ($matchingCard->pivot->quantity ?? 0);
                if ($availableQty <= 0) {
                    continue;
                }

                $qtyToSellFromThisCard = min($remainingToSell, $availableQty);
                $newQty = $availableQty - $qtyToSellFromThisCard;

                if ($newQty > 0) {
                    $portfolio->userCards()->updateExistingPivot($matchingCard->id, ['quantity' => $newQty]);
                } else {
                    $portfolio->userCards()->detach($matchingCard->id);
                }

                $remainingToSell -= $qtyToSellFromThisCard;
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
