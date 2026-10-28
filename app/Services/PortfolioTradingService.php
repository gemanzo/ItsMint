<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioSale;
use App\Models\PortfolioSaleLot;
use App\Models\PortfolioTransaction;
use App\Models\User;
use App\Models\UserCard;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Purchase lots, sales and cash ledger of portfolios.
 *
 * - A purchase lot is a user_cards row: one acquisition with its own price, date and
 *   quantity. The quantity still owned is the pivot quantity in portfolio_user_card.
 * - Sales consume the lots of the same card (blueprint, condition, language) oldest
 *   first (FIFO) and record which lots they consumed (portfolio_sale_lots).
 * - In cash linked portfolios, each lot has one purchase transaction whose amount
 *   always reflects the lot (see syncLotPurchaseTransaction), and each sale has one
 *   sale transaction.
 *
 * Every change runs in a database transaction and locks the affected rows, so that
 * two concurrent requests can't both sell or remove the last copy.
 */
final class PortfolioTradingService
{
    /**
     * Create a purchase lot in the portfolio.
     */
    public function addLot(
        Portfolio $portfolio,
        User $owner,
        int $blueprintId,
        string $condition,
        string $language,
        int $quantity,
        ?int $unitPurchasePriceCents,
        Carbon $acquiredAt,
        ?string $notes = null,
    ): UserCard {
        return DB::transaction(function () use ($portfolio, $owner, $blueprintId, $condition, $language, $quantity, $unitPurchasePriceCents, $acquiredAt, $notes) {
            $lot = $owner->userCards()->create([
                'blueprint_id' => $blueprintId,
                'condition' => $condition,
                'language' => $language,
                'purchase_price_cents' => $unitPurchasePriceCents,
                'purchase_currency' => 'EUR',
                'acquired_at' => $acquiredAt,
                'notes' => $notes,
            ]);

            $portfolio->userCards()->attach($lot->id, ['quantity' => $quantity]);

            $this->syncLotPurchaseTransaction($portfolio, $lot);

            return $lot;
        });
    }

    /**
     * Sell copies of a card, consuming its lots in the portfolio oldest first (FIFO).
     *
     * $userCard identifies the card being sold (any lot of it): the copies actually
     * consumed are the oldest ones of the same blueprint, condition and language.
     */
    public function sell(
        Portfolio $portfolio,
        UserCard $userCard,
        int $quantity,
        int $unitSalePriceCents,
        string $platform,
        ?string $notes = null,
        ?Carbon $soldAt = null
    ): PortfolioSale {
        $soldAt = $soldAt ?? now();

        return DB::transaction(function () use ($portfolio, $userCard, $quantity, $unitSalePriceCents, $platform, $notes, $soldAt) {
            $lotsOldestFirst = $this->sortLotsOldestFirst($this->lockLotsOfSameCard($portfolio, $userCard));

            // Checked inside the transaction, on locked rows: the quantity can't change meanwhile
            $availableQuantity = (int) $lotsOldestFirst->sum(fn (UserCard $lot) => $lot->pivot->quantity);
            if ($quantity < 1 || $quantity > $availableQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Invalid quantity: only {$availableQuantity} copies available.",
                ]);
            }

            $consumedLots = $this->takeQuantityFromLots($portfolio, $lotsOldestFirst, $quantity);
            $firstConsumedLot = $consumedLots->first()['lot'];

            // A copy can't be sold before it was acquired (the transaction is rolled back)
            foreach ($consumedLots as $consumedLot) {
                $lotAcquiredOn = $this->lotDate($consumedLot['lot']);
                if ($lotAcquiredOn->gt($soldAt->copy()->startOfDay())) {
                    throw ValidationException::withMessages([
                        'sold_at' => "The sale date can't be earlier than the purchase of the copies sold ({$lotAcquiredOn->toDateString()}).",
                    ]);
                }
            }

            $sale = PortfolioSale::create([
                'portfolio_id' => $portfolio->id,
                'user_card_id' => $firstConsumedLot->id,
                'blueprint_id' => $userCard->blueprint_id,
                'condition' => $userCard->condition,
                'language' => $userCard->language,
                'quantity' => $quantity,
                'unit_purchase_price_cents' => $this->averageUnitPurchasePriceCents($consumedLots, $quantity),
                'unit_sale_price_cents' => $unitSalePriceCents,
                'platform' => $platform,
                'notes' => $notes,
                'sold_at' => $soldAt,
                'acquired_at' => $this->lotDate($firstConsumedLot),
            ]);

            foreach ($consumedLots as $consumedLot) {
                PortfolioSaleLot::create([
                    'portfolio_sale_id' => $sale->id,
                    'user_card_id' => $consumedLot['lot']->id,
                    'quantity' => $consumedLot['quantity'],
                    'unit_purchase_price_cents' => $consumedLot['lot']->purchase_price_cents,
                    'acquired_at' => $consumedLot['lot']->acquired_at,
                ]);
            }

            if ($portfolio->cash_linked) {
                $saleTransaction = PortfolioTransaction::create([
                    'portfolio_id' => $portfolio->id,
                    'type' => PortfolioTransaction::TYPE_SALE,
                    'amount_cents' => $unitSalePriceCents * $quantity,
                    'currency' => 'EUR',
                    'platform' => $platform,
                    'notes' => $notes,
                    'meta' => [
                        'portfolio_sale_id' => $sale->id,
                        'quantity' => $quantity,
                        'unit_sale_price_cents' => $unitSalePriceCents,
                    ],
                    'occurred_at' => $soldAt,
                ]);
                $sale->update(['portfolio_transaction_id' => $saleTransaction->id]);
            }

            return $sale->load('lots');
        });
    }

    /**
     * Undo a sale: the sold copies go back to the lots they came from.
     */
    public function cancelSale(Portfolio $portfolio, PortfolioSale $sale): void
    {
        DB::transaction(function () use ($portfolio, $sale) {
            $restoredLots = collect();

            foreach ($sale->lots as $soldLot) {
                $lot = $soldLot->userCard ?? $this->recreateRemovedLot($portfolio, $sale, $soldLot);

                $this->changePivotQuantity($portfolio, $lot, $soldLot->quantity);
                $restoredLots->push($lot);
            }

            if ($sale->portfolio_transaction_id) {
                PortfolioTransaction::whereKey($sale->portfolio_transaction_id)->delete();
            }

            // Its lot allocations are deleted with it (cascade)
            $sale->delete();

            foreach ($restoredLots->unique('id') as $lot) {
                $this->syncLotPurchaseTransaction($portfolio, $lot);
            }
        });
    }

    /**
     * Correct the quantity still owned of a lot (e.g. a typo when it was added).
     */
    public function changeLotQuantity(Portfolio $portfolio, UserCard $lot, int $newQuantity): void
    {
        DB::transaction(function () use ($portfolio, $lot, $newQuantity) {
            $currentQuantity = $this->lockedPivotQuantity($portfolio, $lot);
            if ($currentQuantity === null) {
                throw ValidationException::withMessages(['user_card_id' => 'Card not in portfolio.']);
            }

            $portfolio->userCards()->updateExistingPivot($lot->id, ['quantity' => $newQuantity]);
            $this->syncLotPurchaseTransaction($portfolio, $lot);
        });
    }

    /**
     * Remove copies from a portfolio (a correction, not a sale).
     *
     * With $onlyFromThisLot the copies are taken from that lot; otherwise from the lots
     * of the same card, most recent first: removing usually undoes the latest additions.
     */
    public function removeCopies(
        Portfolio $portfolio,
        UserCard $cardOrLot,
        int $quantity,
        bool $onlyFromThisLot = false,
    ): void {
        DB::transaction(function () use ($portfolio, $cardOrLot, $quantity, $onlyFromThisLot) {
            $lotsOfSameCard = $this->lockLotsOfSameCard($portfolio, $cardOrLot);

            if ($onlyFromThisLot) {
                $lotsToTakeFrom = $lotsOfSameCard->where('id', $cardOrLot->id)->values();
            } else {
                $lotsToTakeFrom = $this->sortLotsOldestFirst($lotsOfSameCard)->reverse()->values();
            }

            $availableQuantity = (int) $lotsToTakeFrom->sum(fn (UserCard $lot) => $lot->pivot->quantity);
            if ($quantity < 1 || $quantity > $availableQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Insufficient quantity. Only {$availableQuantity} card(s) available.",
                ]);
            }

            $affectedLots = $this->takeQuantityFromLots($portfolio, $lotsToTakeFrom, $quantity)
                ->map(fn (array $consumedLot) => $consumedLot['lot']);

            foreach ($affectedLots as $lot) {
                $this->syncLotPurchaseTransaction($portfolio, $lot);
                $this->deleteLotIfUnused($lot);
            }
        });
    }

    /**
     * Correct the purchase price of a lot.
     *
     * The sales that consumed the lot and the cash ledger follow the correction, so
     * that realized profit and cash flows stay consistent with the lot.
     */
    public function changeLotPurchasePrice(UserCard $lot, ?int $unitPurchasePriceCents): void
    {
        DB::transaction(function () use ($lot, $unitPurchasePriceCents) {
            $lot->update(['purchase_price_cents' => $unitPurchasePriceCents]);

            $affectedSaleIds = PortfolioSaleLot::where('user_card_id', $lot->id)->pluck('portfolio_sale_id');
            PortfolioSaleLot::where('user_card_id', $lot->id)->update(['unit_purchase_price_cents' => $unitPurchasePriceCents]);

            foreach (PortfolioSale::with('lots')->whereIn('id', $affectedSaleIds)->get() as $sale) {
                $sale->update([
                    'unit_purchase_price_cents' => $this->averageUnitPurchasePriceCents($this->soldLotsAsConsumedLots($sale), $sale->quantity),
                ]);
            }

            $this->syncLotPurchaseTransactions($lot);
        });
    }

    /**
     * Keep the purchase transactions of a lot in line with it, in every portfolio
     * holding or having sold copies of it (e.g. after its date changed).
     */
    public function syncLotPurchaseTransactions(UserCard $lot): void
    {
        foreach ($this->portfoliosOfLot($lot) as $portfolio) {
            $this->syncLotPurchaseTransaction($portfolio, $lot);
        }
    }

    /**
     * Remove a lot from every portfolio. The lot itself is kept when sales refer to it,
     * so that their history stays complete.
     */
    public function removeLotEverywhere(UserCard $lot): void
    {
        DB::transaction(function () use ($lot) {
            foreach ($lot->portfolios()->get() as $portfolio) {
                $this->removeCopies($portfolio, $lot, (int) $portfolio->pivot->quantity, onlyFromThisLot: true);
            }

            $this->deleteLotIfUnused($lot);
        });
    }

    /**
     * Keep the purchase transaction of a lot in line with the lot.
     *
     * The amount is what the copies bought in this lot cost: copies still owned plus
     * copies sold from it. No copies left at all (fully removed lot) means no purchase.
     * An unknown purchase price counts as 0 in the cash ledger.
     */
    public function syncLotPurchaseTransaction(Portfolio $portfolio, UserCard $lot): void
    {
        if (! $portfolio->cash_linked) {
            return;
        }

        $ownedQuantity = (int) ($this->pivotQuantity($portfolio, $lot) ?? 0);
        $soldQuantity = (int) PortfolioSaleLot::query()
            ->where('user_card_id', $lot->id)
            ->whereHas('sale', fn ($saleQuery) => $saleQuery->where('portfolio_id', $portfolio->id))
            ->sum('quantity');
        $purchasedQuantity = $ownedQuantity + $soldQuantity;

        $purchaseTransaction = PortfolioTransaction::query()
            ->where('portfolio_id', $portfolio->id)
            ->where('type', PortfolioTransaction::TYPE_PURCHASE)
            ->where('user_card_id', $lot->id)
            ->first();

        if ($purchasedQuantity === 0) {
            $purchaseTransaction?->delete();

            return;
        }

        $unitPurchasePriceCents = (int) ($lot->purchase_price_cents ?? 0);
        $transactionData = [
            'amount_cents' => -1 * $unitPurchasePriceCents * $purchasedQuantity,
            'occurred_at' => $this->lotDate($lot),
            'meta' => [
                'user_card_id' => $lot->id,
                'quantity' => $purchasedQuantity,
                'unit_purchase_price_cents' => $unitPurchasePriceCents,
            ],
        ];

        if ($purchaseTransaction) {
            $purchaseTransaction->update($transactionData);

            return;
        }

        PortfolioTransaction::create($transactionData + [
            'portfolio_id' => $portfolio->id,
            'user_card_id' => $lot->id,
            'type' => PortfolioTransaction::TYPE_PURCHASE,
            'currency' => 'EUR',
            'notes' => 'Card purchase (added to portfolio)',
        ]);
    }

    /**
     * Lots of the same card (blueprint, condition, language) in the portfolio, locked
     * until the end of the transaction. Each lot carries its pivot quantity.
     *
     * @return Collection<int, UserCard>
     */
    private function lockLotsOfSameCard(Portfolio $portfolio, UserCard $userCard): Collection
    {
        return $portfolio->userCards()
            ->where('user_cards.user_id', $portfolio->user_id)
            ->where('user_cards.blueprint_id', $userCard->blueprint_id)
            ->where('user_cards.condition', $userCard->condition)
            ->where('user_cards.language', $userCard->language)
            ->lockForUpdate()
            ->get();
    }

    /**
     * Oldest lot first: acquisition date, then creation order.
     *
     * @param  Collection<int, UserCard>  $lots
     * @return Collection<int, UserCard>
     */
    private function sortLotsOldestFirst(Collection $lots): Collection
    {
        return $lots
            ->sort(function (UserCard $firstLot, UserCard $secondLot) {
                return [$this->lotDate($firstLot), $firstLot->id] <=> [$this->lotDate($secondLot), $secondLot->id];
            })
            ->values();
    }

    /**
     * Take the quantity from the lots in the given order, updating the portfolio.
     *
     * @param  Collection<int, UserCard>  $lotsInConsumptionOrder
     * @return Collection<int, array{lot: UserCard, quantity: int}>
     */
    private function takeQuantityFromLots(Portfolio $portfolio, Collection $lotsInConsumptionOrder, int $quantity): Collection
    {
        $consumedLots = collect();
        $quantityStillToTake = $quantity;

        foreach ($lotsInConsumptionOrder as $lot) {
            if ($quantityStillToTake === 0) {
                break;
            }

            $quantityInLot = (int) $lot->pivot->quantity;
            $quantityTakenFromLot = min($quantityStillToTake, $quantityInLot);
            if ($quantityTakenFromLot === 0) {
                continue;
            }

            $this->changePivotQuantity($portfolio, $lot, -$quantityTakenFromLot);
            $consumedLots->push(['lot' => $lot, 'quantity' => $quantityTakenFromLot]);
            $quantityStillToTake -= $quantityTakenFromLot;
        }

        return $consumedLots;
    }

    /**
     * Add (or subtract, with a negative delta) copies of a lot in the portfolio.
     * The lot leaves the portfolio when no copy is left.
     */
    private function changePivotQuantity(Portfolio $portfolio, UserCard $lot, int $quantityDelta): void
    {
        $currentQuantity = $this->lockedPivotQuantity($portfolio, $lot);
        $newQuantity = ($currentQuantity ?? 0) + $quantityDelta;

        if ($newQuantity <= 0) {
            $portfolio->userCards()->detach($lot->id);
        } elseif ($currentQuantity === null) {
            $portfolio->userCards()->attach($lot->id, ['quantity' => $newQuantity]);
        } else {
            $portfolio->userCards()->updateExistingPivot($lot->id, ['quantity' => $newQuantity]);
        }
    }

    private function pivotQuantity(Portfolio $portfolio, UserCard $lot): ?int
    {
        $pivotQuantity = DB::table('portfolio_user_card')
            ->where('portfolio_id', $portfolio->id)
            ->where('user_card_id', $lot->id)
            ->value('quantity');

        if ($pivotQuantity === null) {
            return null;
        }

        return (int) $pivotQuantity;
    }

    private function lockedPivotQuantity(Portfolio $portfolio, UserCard $lot): ?int
    {
        $pivotQuantity = DB::table('portfolio_user_card')
            ->where('portfolio_id', $portfolio->id)
            ->where('user_card_id', $lot->id)
            ->lockForUpdate()
            ->value('quantity');

        if ($pivotQuantity === null) {
            return null;
        }

        return (int) $pivotQuantity;
    }

    /**
     * Unit purchase price of the sold copies (weighted by quantity), or null when the
     * cost of one of the consumed lots is unknown.
     *
     * @param  Collection<int, array{lot: UserCard, quantity: int}>  $consumedLots
     */
    private function averageUnitPurchasePriceCents(Collection $consumedLots, int $quantity): ?int
    {
        $totalPurchaseCostCents = 0;

        foreach ($consumedLots as $consumedLot) {
            if ($consumedLot['lot']->purchase_price_cents === null) {
                return null;
            }
            $totalPurchaseCostCents += $consumedLot['lot']->purchase_price_cents * $consumedLot['quantity'];
        }

        return (int) round($totalPurchaseCostCents / $quantity);
    }

    /**
     * The lots recorded on a sale, in the shape used by averageUnitPurchasePriceCents().
     *
     * @return Collection<int, array{lot: UserCard, quantity: int}>
     */
    private function soldLotsAsConsumedLots(PortfolioSale $sale): Collection
    {
        return $sale->lots->map(fn (PortfolioSaleLot $soldLot) => [
            'lot' => new UserCard(['purchase_price_cents' => $soldLot->unit_purchase_price_cents]),
            'quantity' => $soldLot->quantity,
        ]);
    }

    /**
     * Date used to order lots: the acquisition date, or when the lot was recorded.
     */
    private function lotDate(UserCard $lot): Carbon
    {
        $lotDate = $lot->acquired_at ?? $lot->created_at ?? now();

        return $lotDate->copy()->startOfDay();
    }

    /**
     * A lot is deleted once it's in no portfolio and no sale refers to it.
     */
    private function deleteLotIfUnused(UserCard $lot): void
    {
        $isInAPortfolio = DB::table('portfolio_user_card')->where('user_card_id', $lot->id)->exists();
        $wasSold = PortfolioSaleLot::where('user_card_id', $lot->id)->exists();

        if (! $isInAPortfolio && ! $wasSold) {
            $lot->delete();
        }
    }

    /**
     * Rebuild a lot that was removed after one of its copies was sold, from the
     * snapshot kept by the sale, so that the copies can go back to the portfolio.
     */
    private function recreateRemovedLot(Portfolio $portfolio, PortfolioSale $sale, PortfolioSaleLot $soldLot): UserCard
    {
        return UserCard::create([
            'user_id' => $portfolio->user_id,
            'blueprint_id' => $sale->blueprint_id,
            'condition' => $sale->condition,
            'language' => $sale->language,
            'purchase_price_cents' => $soldLot->unit_purchase_price_cents,
            'purchase_currency' => 'EUR',
            'acquired_at' => $soldLot->acquired_at,
        ]);
    }

    /**
     * Portfolios holding copies of the lot or having sold some of them.
     *
     * @return Collection<int, Portfolio>
     */
    private function portfoliosOfLot(UserCard $lot): Collection
    {
        $portfolioIdsWithSales = PortfolioSale::query()
            ->whereHas('lots', fn ($soldLotsQuery) => $soldLotsQuery->where('user_card_id', $lot->id))
            ->pluck('portfolio_id');

        $portfolioIdsHoldingTheLot = $lot->portfolios()->pluck('portfolios.id');

        return Portfolio::query()
            ->whereIn('id', $portfolioIdsHoldingTheLot->merge($portfolioIdsWithSales)->unique())
            ->get();
    }
}
