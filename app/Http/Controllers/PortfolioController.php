<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\UserCard;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PortfolioController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the user's portfolios.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Check if this is an API request (JSON expected) - for dashboard calculations
        if ($request->expectsJson() || $request->is('api/*')) {
            // For API requests (dashboard), load full data with relationships for calculations
            $portfolios = $user->portfolios()
                ->with(['userCards.blueprint.latestPrice'])
                ->latest()
                ->get();

            return response()->json([
                'data' => $portfolios,
            ]);
        }

        // For web requests (portfolios index page), calculate value efficiently
        $portfolios = $user->portfolios()
            ->with('userCards.blueprint.latestPrice') // Eager load for calculation
            ->withCount('userCards')
            ->latest()
            ->get();

        $portfolios->each(function ($portfolio) {
            $totalValueCents = $portfolio->userCards->reduce(function ($carry, $userCard) {
                // Use the estimated_value_cents from the UserCard model's accessor
                $cardValue = $userCard->estimated_value_cents ?? 0;
                $quantity = $userCard->pivot->quantity ?? 1; // Pivot data is available here

                return $carry + ($cardValue * $quantity);
            }, 0);
            $portfolio->total_value_cents = $totalValueCents;
        });

        return Inertia::render('Portfolios/Index', [
            'portfolios' => $portfolios,
            // You might want to use an API resource here for consistency and to control data shape
            // 'portfolios' => PortfolioResource::collection($portfolios),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $portfolio = Auth::user()->portfolios()->create($validated);

        return response()->json($portfolio, 201);
    }

    /**
     * Display the specified portfolio.
     */
    public function show(Request $request, Portfolio $portfolio)
    {
        $this->authorize('view', $portfolio);

        // Eager load necessary relationships for the detailed view
        $portfolio->load([
            'userCards.blueprint.expansion',
            'userCards.blueprint.latestPrice',
        ])->loadCount('userCards');
        // If you need userCards to be paginated within the show view, adjust query accordingly.
        // For example, an alternative to loading all user_cards:
        // $userCards = $portfolio->userCards()->with('blueprint.latestPrice')->paginate(15);

        // Check if this is an API request (JSON expected)
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $portfolio,
            ]);
        }

        // For web requests, return Inertia view
        return Inertia::render('Portfolios/Show', [
            'portfolio' => $portfolio, // Again, consider using a resource
            // 'userCards' => UserCardResource::collection($userCards), // If paginating separately
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Portfolio $portfolio): JsonResponse
    {
        if ($request->user()->id !== $portfolio->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $portfolio->update($validated);

        return response()->json($portfolio);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Portfolio $portfolio): JsonResponse
    {
        if ($request->user()->id !== $portfolio->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $portfolio->delete();

        return response()->json(null, 204);
    }

    /**
     * Finds or creates a UserCard and attaches it to a portfolio.
     */
    public function addBlueprint(Request $request, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);

        $validated = $request->validate([
            'blueprint_id' => 'required|exists:blueprints,id',
            'condition' => 'required|string|max:50',
            'language' => 'required|string|max:10',
            'quantity' => 'required|integer|min:1',
            'purchase_price_cents' => 'nullable|integer|min:0',
            'is_foil' => 'required|boolean',
        ]);

        $user = $request->user();

        // Find an existing UserCard or create a new one
        $userCard = $user->userCards()->firstOrCreate(
            [
                'blueprint_id' => $validated['blueprint_id'],
                'condition' => $validated['condition'],
                'language' => $validated['language'],
                'is_foil' => $validated['is_foil'],
            ],
            [
                'purchase_price_cents' => $validated['purchase_price_cents'],
                'purchase_currency' => 'EUR',
                'acquired_at' => now(),
            ]
        );

        // Check if the card is already in the portfolio
        $pivot = $portfolio->userCards()->where('user_card_id', $userCard->id)->first();

        if ($pivot) {
            // If it exists, update the quantity
            $newQuantity = $pivot->pivot->quantity + $validated['quantity'];
            $portfolio->userCards()->updateExistingPivot($userCard->id, ['quantity' => $newQuantity]);
        } else {
            // If not, attach it with the new quantity
            $portfolio->userCards()->attach($userCard->id, ['quantity' => $validated['quantity']]);
        }

        return response()->json($userCard->load('blueprint'), 201);
    }

    /**
     * Add a UserCard to a Portfolio.
     */
    public function addUserCard(Request $request, Portfolio $portfolio): JsonResponse
    {
        if ($request->user()->id !== $portfolio->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'user_card_id' => 'required|exists:user_cards,id',
            'quantity' => 'required|integer|min:1',
        ]);

        // Verifica che la user_card appartenga all'utente autenticato
        $userCard = UserCard::where('id', $validated['user_card_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $userCard) {
            return response()->json(['message' => 'UserCard not found or not owned by user.'], 404);
        }

        // Aggiungi o aggiorna la carta nel portafoglio
        $portfolio->userCards()->syncWithoutDetaching([
            $validated['user_card_id'] => ['quantity' => $validated['quantity']],
        ]);

        return response()->json($portfolio->load('userCards'));
    }

    public function updateUserCardQuantity(Request $request, Portfolio $portfolio, UserCard $userCard): JsonResponse
    {
        if ($request->user()->id !== $portfolio->user_id || $request->user()->id !== $userCard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        if ($portfolio->userCards()->where('user_card_id', $userCard->id)->exists()) {
            // Find all cards in the portfolio that match the same "group" 
            // (same blueprint, condition, language, purchase_price)
            $groupCards = $portfolio->userCards()
                ->where('blueprint_id', $userCard->blueprint_id)
                ->where('condition', $userCard->condition)
                ->where('language', $userCard->language)
                ->where('purchase_price_cents', $userCard->purchase_price_cents)
                ->get();

            // Remove all cards from this group in the portfolio
            foreach ($groupCards as $groupCard) {
                $portfolio->userCards()->detach($groupCard->id);
            }

            // Add the card back with the new quantity
            $portfolio->userCards()->attach($userCard->id, [
                'quantity' => $validated['quantity'],
                'added_to_portfolio_at' => now(),
            ]);

            // Load all necessary relationships that the frontend expects
            $portfolio->load([
                'userCards.blueprint.expansion',
                'userCards.blueprint.latestPrice',
            ])->loadCount('userCards');

            return response()->json($portfolio);
        }

        return response()->json(['message' => 'Card not in portfolio'], 404);
    }

    public function removeUserCard(Request $request, Portfolio $portfolio, UserCard $userCard): JsonResponse
    {
        if ($request->user()->id !== $portfolio->user_id || $request->user()->id !== $userCard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Check if the UserCard is in this portfolio
        if (!$portfolio->userCards()->where('user_card_id', $userCard->id)->exists()) {
            return response()->json(['message' => 'Card not found in portfolio'], 404);
        }

        // Remove the card from this portfolio (pivot table)
        $portfolio->userCards()->detach($userCard->id);

        // Check if the UserCard is still in other portfolios
        $otherPortfoliosCount = $userCard->portfolios()
            ->where('portfolios.id', '!=', $portfolio->id)
            ->count();

        // If the UserCard is not in any other portfolio, delete it completely
        if ($otherPortfoliosCount === 0) {
            $userCard->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * Get portfolio value history over time
     */
    public function valueHistory(Request $request, Portfolio $portfolio): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:1D,1W,1M,3M,1Y,MAX',
            'metric' => 'nullable|string|in:market_value,cost_basis,performance',
        ]);

        // Check authorization
        if ($request->user()->id !== $portfolio->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $period = $request->get('period', '1M');
        $metric = $request->get('metric', 'market_value');

        // Calculate date range based on period
        $endDate = now();
        $startDate = match ($period) {
            '1D' => $endDate->copy()->subDay(),
            '1W' => $endDate->copy()->subWeek(),
            '1M' => $endDate->copy()->subMonth(),
            '3M' => $endDate->copy()->subMonths(3),
            '1Y' => $endDate->copy()->subYear(),
            'MAX' => $portfolio->created_at ?: $endDate->copy()->subYears(2),
            default => $endDate->copy()->subMonth(),
        };

        // Get all user cards in this portfolio with their relationships
        $userCards = $portfolio->userCards()
            ->with(['blueprint.cardPrices' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('fetched_at', [$startDate, $endDate])
                    ->orderBy('fetched_at');
            }])
            ->get();

        if ($userCards->isEmpty()) {
            return response()->json([]);
        }

        // Calculate data points based on period
        $dataPoints = $this->generateDataPoints($startDate, $endDate, $period);
        $valueHistory = [];

        foreach ($dataPoints as $date) {
            $totalMarketValue = 0;
            $totalCostBasis = 0;

            foreach ($userCards as $userCard) {
                // Only include cards that were acquired before this date
                $acquiredAt = $userCard->acquired_at ? \Carbon\Carbon::parse($userCard->acquired_at) : $portfolio->created_at;
                if ($acquiredAt && $acquiredAt->gt($date)) {
                    continue;
                }

                $quantity = $userCard->pivot->quantity ?? 1;

                // Add to cost basis (purchase price * quantity)
                $totalCostBasis += ($userCard->purchase_price_cents ?? 0) * $quantity;

                // Find the closest market price to this date
                $closestPrice = $userCard->blueprint->cardPrices
                    ->filter(function ($price) use ($userCard, $date) {
                        // Match language and condition
                        return $price->language === $userCard->language &&
                            $price->condition === $userCard->condition &&
                            \Carbon\Carbon::parse($price->fetched_at)->lte($date);
                    })
                    ->sortByDesc('fetched_at')
                    ->first();

                if ($closestPrice) {
                    $totalMarketValue += ($closestPrice->price_cents * $quantity);
                } else {
                    // Fallback: Use the most recent price available for this card (regardless of date)
                    $fallbackPrice = $userCard->blueprint->cardPrices
                        ->filter(function ($price) use ($userCard) {
                            return $price->language === $userCard->language &&
                                $price->condition === $userCard->condition;
                        })
                        ->sortByDesc('fetched_at')
                        ->first();

                    if ($fallbackPrice) {
                        $totalMarketValue += ($fallbackPrice->price_cents * $quantity);
                    } else {
                        // Ultimate fallback: Use the estimated value from the UserCard
                        $totalMarketValue += ($userCard->estimated_value_cents ?? 0) * $quantity;
                    }
                }
            }

            // Calculate the value based on requested metric
            $value = match ($metric) {
                'market_value' => $totalMarketValue,
                'cost_basis' => $totalCostBasis,
                'performance' => $totalCostBasis > 0 ? (($totalMarketValue - $totalCostBasis) / $totalCostBasis) * 100 : 0,
                default => $totalMarketValue,
            };

            $valueHistory[] = [
                'date' => $date->toDateString(),
                'fetched_at' => $date->toISOString(),
                'value_cents' => $metric === 'performance' ? $value : $value, // Performance is already in percentage, others in cents
                'market_value_cents' => $totalMarketValue,
                'cost_basis_cents' => $totalCostBasis,
                'performance_percentage' => $totalCostBasis > 0 ? (($totalMarketValue - $totalCostBasis) / $totalCostBasis) * 100 : 0,
            ];
        }

        return response()->json($valueHistory);
    }

    /**
     * Generate appropriate data points for the given period
     */
    private function generateDataPoints(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate, string $period): array
    {
        $points = [];
        $current = $startDate->copy();

        // Determine interval based on period
        $interval = match ($period) {
            '1D' => 'hour',
            '1W' => 'day',
            '1M' => 'day',
            '3M' => 'day',
            '1Y' => 'week',
            'MAX' => 'week',
            default => 'day',
        };

        $step = match ($period) {
            '1D' => 2, // Every 2 hours
            '1W' => 1, // Daily
            '1M' => 2, // Every 2 days
            '3M' => 3, // Every 3 days
            '1Y' => 1, // Weekly
            'MAX' => 2, // Every 2 weeks
            default => 1,
        };

        while ($current->lte($endDate)) {
            $points[] = $current->copy();

            if ($interval === 'hour') {
                $current->addHours($step);
            } elseif ($interval === 'day') {
                $current->addDays($step);
            } elseif ($interval === 'week') {
                $current->addWeeks($step);
            }

            // Prevent infinite loops
            if (count($points) > 1000) {
                break;
            }
        }

        // Always include the end date
        if (!$points || $points[count($points) - 1]->lt($endDate)) {
            $points[] = $endDate->copy();
        }

        return $points;
    }
}
