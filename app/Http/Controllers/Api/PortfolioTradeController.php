<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Models\PortfolioSale;
use App\Models\PortfolioTransaction;
use App\Models\UserCard;
use App\Services\PortfolioTradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PortfolioTradeController extends Controller
{
    public function __construct(private readonly PortfolioTradingService $service) {}

    public function sell(Request $request, Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('update', $portfolio);

        $validated = $request->validate([
            'user_card_id' => 'required|exists:user_cards,id',
            'quantity' => 'required|integer|min:1',
            'unit_sale_price_cents' => 'required|integer|min:0',
            'platform' => 'required|string|in:ebay,cardmarket,private,cardtrader,custom',
            'notes' => 'nullable|string',
            'sold_at' => 'nullable|date',
        ]);

        $userCard = UserCard::where('id', $validated['user_card_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $sale = $this->service->sell(
            $portfolio,
            $userCard,
            (int) $validated['quantity'],
            (int) $validated['unit_sale_price_cents'],
            $validated['platform'],
            $validated['notes'] ?? null,
            isset($validated['sold_at']) ? \Carbon\Carbon::parse($validated['sold_at']) : null
        );

        // Clear portfolio value cache after sale
        Cache::forget("portfolio_total_value_cents_{$portfolio->id}");

        $portfolio->load([
            'userCards.blueprint.expansion',
            'userCards.blueprint.latestPrice',
        ])->loadCount('userCards');

        return response()->json([
            'sale' => $sale->load('userCard.blueprint'),
            'portfolio' => $portfolio,
        ], 201);
    }

    public function salesIndex(Request $request, Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('view', $portfolio);

        $validated = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'min_sale_price' => 'nullable|numeric|min:0',
            'max_sale_price' => 'nullable|numeric|min:0',
            'pnl' => 'nullable|string|in:all,gain,loss',
            'sort_by' => 'nullable|string|in:date,price',
            'sort_dir' => 'nullable|string|in:asc,desc',
        ]);

        $query = PortfolioSale::with('userCard.blueprint.expansion')
            ->where('portfolio_id', $portfolio->id);

        // Date range filter
        if (! empty($validated['date_from'])) {
            $query->where('sold_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('sold_at', '<=', \Carbon\Carbon::parse($validated['date_to'])->endOfDay());
        }

        // Price range filter (unit sale price)
        if (isset($validated['min_sale_price'])) {
            $minCents = (int) round($validated['min_sale_price'] * 100);
            $query->where('unit_sale_price_cents', '>=', $minCents);
        }
        if (isset($validated['max_sale_price'])) {
            $maxCents = (int) round($validated['max_sale_price'] * 100);
            $query->where('unit_sale_price_cents', '<=', $maxCents);
        }

        // P&L filter
        if (! empty($validated['pnl']) && $validated['pnl'] !== 'all') {
            if ($validated['pnl'] === 'gain') {
                $query->whereRaw('unit_sale_price_cents > unit_purchase_price_cents');
            } else {
                $query->whereRaw('unit_sale_price_cents < unit_purchase_price_cents');
            }
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'date';
        $sortDir = $validated['sort_dir'] ?? 'desc';

        if ($sortBy === 'price') {
            $query->orderBy('unit_sale_price_cents', $sortDir);
        } else {
            $query->orderBy('sold_at', $sortDir);
        }

        // Pagination
        $perPage = (int) $request->get('per_page', 50);
        $sales = $query->paginate($perPage);

        return response()->json($sales);
    }

    public function salesUpdate(Request $request, Portfolio $portfolio, PortfolioSale $sale): JsonResponse
    {
        Gate::authorize('update', $portfolio);
        if ($sale->portfolio_id !== $portfolio->id) {
            abort(404);
        }

        $validated = $request->validate([
            'quantity' => 'sometimes|required|integer|min:1',
            'unit_sale_price_cents' => 'sometimes|required|integer|min:0',
            'platform' => 'sometimes|required|string|in:ebay,cardmarket,private,cardtrader,custom',
            'notes' => 'nullable|string',
            'sold_at' => 'nullable|date',
        ]);

        $sale->fill($validated);
        if (isset($validated['sold_at'])) {
            $sale->sold_at = \Carbon\Carbon::parse($validated['sold_at']);
        }
        $sale->save();

        if ($sale->portfolio_transaction_id) {
            $tx = PortfolioTransaction::find($sale->portfolio_transaction_id);
            if ($tx) {
                $tx->amount_cents = $sale->unit_sale_price_cents * $sale->quantity;
                if (isset($validated['platform'])) $tx->platform = $sale->platform;
                if (array_key_exists('notes', $validated)) $tx->notes = $sale->notes;
                if (isset($validated['sold_at'])) $tx->occurred_at = $sale->sold_at;
                $tx->save();
            }
        }

        return response()->json($sale->fresh('userCard.blueprint'));
    }

    public function salesDestroy(Request $request, Portfolio $portfolio, PortfolioSale $sale): JsonResponse
    {
        Gate::authorize('update', $portfolio);
        if ($sale->portfolio_id !== $portfolio->id) {
            abort(404);
        }

        $userCard = $sale->userCard;
        $pivot = $portfolio->userCards()->where('user_card_id', $userCard->id)->first();
        if ($pivot) {
            $newQty = $pivot->pivot->quantity + $sale->quantity;
            $portfolio->userCards()->updateExistingPivot($userCard->id, ['quantity' => $newQty]);
        } else {
            $portfolio->userCards()->attach($userCard->id, ['quantity' => $sale->quantity]);
        }

        if ($sale->portfolio_transaction_id) {
            PortfolioTransaction::where('id', $sale->portfolio_transaction_id)->delete();
        }

        $sale->delete();

        // Clear portfolio value cache after sale deletion
        Cache::forget("portfolio_total_value_cents_{$portfolio->id}");

        return response()->json(null, 204);
    }

    public function cashHistory(Request $request, Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('view', $portfolio);

        $tx = PortfolioTransaction::where('portfolio_id', $portfolio->id)
            ->orderBy('occurred_at')
            ->get(['amount_cents', 'type', 'occurred_at']);

        $balance = 0;
        $timeline = $tx->map(function ($t) use (&$balance) {
            $balance += $t->amount_cents;
            return [
                'occurred_at' => $t->occurred_at->toISOString(),
                'balance_cents' => $balance,
                'type' => $t->type,
            ];
        });

        return response()->json($timeline);
    }

    public function transactionsStore(Request $request, Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('update', $portfolio);

        $validated = $request->validate([
            'type' => 'required|string|in:deposit,withdraw,adjustment',
            'amount_cents' => 'required|integer|min:1',
            'notes' => 'nullable|string',
            'occurred_at' => 'nullable|date',
        ]);

        $amount = (int) $validated['amount_cents'];
        if ($validated['type'] === PortfolioTransaction::TYPE_WITHDRAW) {
            $amount = -$amount;
        }

        $tx = PortfolioTransaction::create([
            'portfolio_id' => $portfolio->id,
            'type' => $validated['type'],
            'amount_cents' => $amount,
            'currency' => 'EUR',
            'notes' => $validated['notes'] ?? null,
            'occurred_at' => isset($validated['occurred_at']) ? \Carbon\Carbon::parse($validated['occurred_at']) : now(),
        ]);

        return response()->json($tx, 201);
    }

    public function salesExport(Request $request, Portfolio $portfolio): StreamedResponse
    {
        Gate::authorize('view', $portfolio);

        $query = \App\Models\PortfolioSale::with('userCard.blueprint')
            ->where('portfolio_id', $portfolio->id)
            ->orderBy('sold_at');

        // Optional selection by IDs (comma-separated)
        if ($ids = $request->query('ids')) {
            $idsArray = collect(explode(',', (string) $ids))
                ->map(fn($v) => (int) trim((string) $v))
                ->filter(fn($v) => $v > 0)
                ->values()
                ->all();
            if (! empty($idsArray)) {
                $query->whereIn('id', $idsArray);
            }
        }

        $sales = $query->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="portfolio_sales.csv"',
        ];

        return response()->stream(function () use ($sales) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Sold At', 'Blueprint', 'Condition', 'Language', 'Qty', 'Unit Purchase (€)', 'Unit Sale (€)', 'P&L (€)', 'Platform', 'Notes']);
            foreach ($sales as $s) {
                $bp = $s->userCard->blueprint;
                $pnl = ($s->unit_sale_price_cents - $s->unit_purchase_price_cents) * $s->quantity;
                fputcsv($out, [
                    $s->sold_at?->toDateString(),
                    $bp?->name,
                    $s->userCard->condition,
                    $s->userCard->language,
                    $s->quantity,
                    number_format($s->unit_purchase_price_cents / 100, 2, '.', ''),
                    number_format($s->unit_sale_price_cents / 100, 2, '.', ''),
                    number_format($pnl / 100, 2, '.', ''),
                    $s->platform,
                    $s->notes,
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    public function piggyBankMetrics(Request $request, Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('view', $portfolio);

        // Balance from ledger
        $balanceCents = (int) \App\Models\PortfolioTransaction::where('portfolio_id', $portfolio->id)->sum('amount_cents');

        // Avg hold time (weighted by qty)
        $sales = PortfolioSale::where('portfolio_id', $portfolio->id)->get(['quantity', 'sold_at', 'acquired_at', 'unit_purchase_price_cents', 'unit_sale_price_cents']);
        $totalQty = 0;
        $weightedDays = 0.0;
        $best = null; // ['pnl_cents' => int, 'roi' => float, 'sale' => PortfolioSale]
        $worst = null;
        foreach ($sales as $s) {
            $qty = (int) $s->quantity;
            $totalQty += $qty;
            if ($s->acquired_at && $s->sold_at) {
                $days = max(0, $s->sold_at->diffInDays($s->acquired_at));
                $weightedDays += $days * $qty;
            }
            $pnlCents = ($s->unit_sale_price_cents - $s->unit_purchase_price_cents) * $qty;
            $roi = $s->unit_purchase_price_cents > 0 ? ($s->unit_sale_price_cents - $s->unit_purchase_price_cents) / $s->unit_purchase_price_cents : 0.0;
            if ($best === null || $pnlCents > $best['pnl_cents']) {
                $best = ['pnl_cents' => $pnlCents, 'roi' => $roi, 'sale' => $s];
            }
            if ($worst === null || $pnlCents < $worst['pnl_cents']) {
                $worst = ['pnl_cents' => $pnlCents, 'roi' => $roi, 'sale' => $s];
            }
        }
        $avgHoldDays = $totalQty > 0 ? $weightedDays / $totalQty : 0.0;

        // Turnover: choose A) last 12 months sales value / average inventory value
        $twelveMonthsAgo = now()->subYear();
        $sales12 = PortfolioSale::where('portfolio_id', $portfolio->id)
            ->where('sold_at', '>=', $twelveMonthsAgo)
            ->get(['quantity', 'unit_sale_price_cents']);
        $sales12ValueCents = $sales12->reduce(fn($c, $s) => $c + ($s->unit_sale_price_cents * $s->quantity), 0);

        // Approximate avg inventory value: mean of monthly snapshots of cost basis of current holdings (fallback: current cost basis)
        // For simplicity now: use current holdings cost basis as proxy
        $currentHoldings = $portfolio->userCards()->get(['purchase_price_cents']);
        $currentPivot = $portfolio->userCards()->get();
        $currentCostBasisCents = 0;
        foreach ($currentPivot as $card) {
            $qty = (int) ($card->pivot->quantity ?? 1);
            $currentCostBasisCents += (int) ($card->purchase_price_cents ?? 0) * $qty;
        }
        $turnover = $currentCostBasisCents > 0 ? ($sales12ValueCents / $currentCostBasisCents) : 0.0;

        return response()->json([
            'balance_cents' => (int) $balanceCents,
            'balance_eur' => number_format($balanceCents / 100, 2, '.', ''),
            'avg_hold_days' => round($avgHoldDays, 1),
            'best_trade' => $best ? [
                'pnl_cents' => $best['pnl_cents'],
                'pnl_eur' => number_format($best['pnl_cents'] / 100, 2, '.', ''),
                'roi_percentage' => round($best['roi'] * 100, 1),
            ] : null,
            'worst_trade' => $worst ? [
                'pnl_cents' => $worst['pnl_cents'],
                'pnl_eur' => number_format($worst['pnl_cents'] / 100, 2, '.', ''),
                'roi_percentage' => round($worst['roi'] * 100, 1),
            ] : null,
            'turnover_12m' => round($turnover, 2),
        ]);
    }
}
