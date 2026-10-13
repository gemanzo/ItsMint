<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\UserCard;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PortfolioController extends Controller
{
    use AuthorizesRequests;

    /**
     * Check if user has unlimited portfolio feature.
     */
    private function hasUnlimitedPortfolio(\Illuminate\Contracts\Auth\Authenticatable $user): bool
    {
        return \Laravel\Pennant\Feature::for($user)->active('portfolio.unlimited');
    }

    /**
     * Get the oldest portfolio ID for a user.
     */
    private function getOldestPortfolioId(\Illuminate\Contracts\Auth\Authenticatable $user): int
    {
        return (int) ($user->portfolios()->oldest('created_at')->value('id') ?? 0);
    }

    /**
     * Check if a portfolio is locked for free plan users.
     */
    private function isPortfolioLocked(\Illuminate\Contracts\Auth\Authenticatable $user, Portfolio $portfolio, ?int $oldestPortfolioId = null): bool
    {
        if ($this->hasUnlimitedPortfolio($user)) {
            return false;
        }

        $oldestId = $oldestPortfolioId ?? $this->getOldestPortfolioId($user);
        return $portfolio->id !== $oldestId;
    }

    /**
     * Get total card count for a specific portfolio (sum of quantities).
     */
    private function getTotalCardCount(Portfolio $portfolio): int
    {
        return (int) DB::table('portfolio_user_card')
            ->where('portfolio_id', $portfolio->id)
            ->sum('quantity');
    }

    /**
     * Get total value in cents for a portfolio with caching.
     * Cache TTL: 5 minutes
     */
    private function getPortfolioTotalValueCents(Portfolio $portfolio): int
    {
        $cacheKey = "portfolio_total_value_cents_{$portfolio->id}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($portfolio) {
            $portfolio->load([
                'userCards.blueprint.latestPrice',
                'userCards.blueprint.cardPrices',
            ]);

            return $portfolio->userCards->reduce(function ($carry, $userCard) {
                $cardValue = $userCard->estimated_value_cents ?? 0;
                $quantity = $userCard->pivot->quantity ?? 1;

                return $carry + ($cardValue * $quantity);
            }, 0);
        });
    }

    /**
     * Clear portfolio value cache.
     */
    private function clearPortfolioValueCache(Portfolio $portfolio): void
    {
        Cache::forget("portfolio_total_value_cents_{$portfolio->id}");
    }

    /**
     * Check if user can add more cards to a portfolio (Free plan limit: 50 cards per portfolio).
     */
    private function canAddCards(\Illuminate\Contracts\Auth\Authenticatable $user, Portfolio $portfolio, int $quantityToAdd): bool
    {
        if ($this->hasUnlimitedPortfolio($user)) {
            return true; // Premium users have no limit
        }

        $currentTotal = $this->getTotalCardCount($portfolio);
        return ($currentTotal + $quantityToAdd) <= 50;
    }

    /**
     * Check card limit availability for a specific portfolio.
     * Returns the current card count, limit, and available slots.
     */
    public function checkCardLimit(Request $request, Portfolio $portfolio): JsonResponse
    {
        $user = $request->user();

        // Verify user owns the portfolio
        if ($user->id !== $portfolio->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $currentTotal = $this->getTotalCardCount($portfolio);
        $limit = $this->hasUnlimitedPortfolio($user) ? null : 50;
        $available = $limit === null ? null : max(0, $limit - $currentTotal);

        return response()->json([
            'current_total' => $currentTotal,
            'limit' => $limit,
            'available' => $available,
            'is_unlimited' => $limit === null,
        ]);
    }

    /**
     * Return a locked portfolio error response.
     */
    private function lockedPortfolioResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'This portfolio is locked on Free plan',
            'upgrade_url' => '/subscribe',
        ], 403);
    }

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
                ->with([
                    'userCards.blueprint.latestPrice',
                    'userCards.blueprint.cardPrices', // Eager load all prices to avoid N+1 in estimated_value_cents accessor
                ])
                ->latest()
                ->get();

            return response()->json([
                'data' => $portfolios,
            ]);
        }

        // For web requests (portfolios index page), calculate value efficiently
        $portfolios = $user->portfolios()
            ->with([
                'userCards.blueprint.latestPrice',
                'userCards.blueprint.cardPrices', // Eager load all prices to avoid N+1 in estimated_value_cents accessor
            ])
            ->withCount('userCards')
            ->latest()
            ->get();

        // Pre-calculate oldest portfolio ID once to avoid N+1 queries
        $oldestPortfolioId = $this->getOldestPortfolioId($user);

        $portfolios->each(function ($portfolio) use ($user, $oldestPortfolioId) {
            // Use cached total value calculation
            $portfolio->total_value_cents = $this->getPortfolioTotalValueCents($portfolio);

            // Add lock status for free plan users (pass pre-calculated oldest portfolio ID)
            $portfolio->is_locked = $this->isPortfolioLocked($user, $portfolio, $oldestPortfolioId);
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

        // Gating: limitare a 1 portfolio per piano Free
        $user = $request->user();
        if (! $this->hasUnlimitedPortfolio($user)) {
            $existingCount = (int) $user->portfolios()->count();
            if ($existingCount >= 1) {
                return response()->json([
                    'message' => 'Portfolio limit reached for Free plan',
                    'upgrade_url' => '/subscribe',
                ], 403);
            }
        }

        $portfolio = $user->portfolios()->create($validated);

        return response()->json($portfolio, 201);
    }

    /**
     * Display the specified portfolio.
     */
    public function show(Request $request, Portfolio $portfolio)
    {
        $this->authorize('view', $portfolio);

        // Check if pagination is requested (default: paginate for performance)
        $perPage = (int) $request->get('per_page', 50);
        $page = (int) $request->get('page', 1);

        // For web requests, paginate userCards to improve performance
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            // Paginate userCards for web view
            $userCardsPaginated = $portfolio->userCards()
                ->with([
                    'blueprint.expansion',
                    'blueprint.latestPrice',
                    'blueprint.cardPrices',
                ])
                ->paginate($perPage, ['*'], 'page', $page);

            // Load portfolio with paginated userCards
            $portfolio->setRelation('userCards', $userCardsPaginated->getCollection());
            $portfolio->user_cards_count = $userCardsPaginated->total();
        } else {
            // For API requests, load all userCards (backward compatibility)
            $portfolio->load([
                'userCards.blueprint.expansion',
                'userCards.blueprint.latestPrice',
                'userCards.blueprint.cardPrices',
            ])->loadCount('userCards');
        }

        // Check if this is an API request (JSON expected)
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $portfolio,
            ]);
        }

        // Determine lock status for Free plan users (only oldest portfolio is editable)
        $user = $request->user();
        $isLocked = $this->isPortfolioLocked($user, $portfolio);

        // For web requests, return Inertia view with pagination metadata
        return Inertia::render('Portfolios/Show', [
            'portfolio' => $portfolio,
            'locked' => $isLocked,
            'userCardsPagination' => isset($userCardsPaginated) ? [
                'current_page' => $userCardsPaginated->currentPage(),
                'last_page' => $userCardsPaginated->lastPage(),
                'per_page' => $userCardsPaginated->perPage(),
                'total' => $userCardsPaginated->total(),
            ] : null,
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

        // Block updates on locked portfolios for Free plan users
        $user = $request->user();
        if ($this->isPortfolioLocked($user, $portfolio)) {
            return $this->lockedPortfolioResponse();
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

        // Block delete on locked portfolios for Free plan users
        $user = $request->user();
        if ($this->isPortfolioLocked($user, $portfolio)) {
            return $this->lockedPortfolioResponse();
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

        // Block modifications on locked portfolios for Free plan users
        $user = $request->user();
        if ($this->isPortfolioLocked($user, $portfolio)) {
            return $this->lockedPortfolioResponse();
        }

        $validated = $request->validate([
            'blueprint_id' => 'required|exists:blueprints,id',
            'condition' => 'required|string|max:50',
            'language' => 'required|string|max:10',
            'quantity' => 'required|integer|min:1',
            // 0 is a valid cost: pulled from a pack, received as a gift, ...
            'purchase_price_cents' => 'required|integer|min:0',
            // Optional, defaults to today; a card can't be acquired in the future
            'acquired_at' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if (empty($validated['acquired_at'])) {
            $acquiredAt = today();
        } else {
            $acquiredAt = \Carbon\Carbon::parse($validated['acquired_at']);
        }

        // Prepare creation data
        $createData = [
            'purchase_price_cents' => $validated['purchase_price_cents'],
            'purchase_currency' => 'EUR',
            'acquired_at' => $acquiredAt,
        ];

        // Add notes if provided
        if (isset($validated['notes']) && ! empty($validated['notes'])) {
            $createData['notes'] = $validated['notes'];
        }

        // Find an existing UserCard or create a new one
        $userCard = $user->userCards()->firstOrCreate(
            [
                'blueprint_id' => $validated['blueprint_id'],
                'condition' => $validated['condition'],
                'language' => $validated['language'],
            ],
            $createData
        );

        // If UserCard already exists but we want to update acquired_at, purchase_price, or notes, update it
        if ($userCard->wasRecentlyCreated === false) {
            if (isset($validated['purchase_price_cents'])) {
                $userCard->purchase_price_cents = $validated['purchase_price_cents'];
            }
            $userCard->acquired_at = $acquiredAt;
            if (isset($validated['notes'])) {
                $userCard->notes = $validated['notes'];
            }
            $userCard->save();
        }

        // Check if the card is already in the portfolio
        $pivot = $portfolio->userCards()->where('user_card_id', $userCard->id)->first();

        // Calculate actual quantity increase
        $currentQuantity = $pivot ? ($pivot->pivot->quantity ?? 0) : 0;
        $quantityIncrease = $validated['quantity'];
        $newQuantity = $currentQuantity + $quantityIncrease;

        // Check card limit for free plan users (50 cards per portfolio)
        // Calculate the actual increase in total card count
        $currentTotal = $this->getTotalCardCount($portfolio);
        $totalAfterAdd = $currentTotal - $currentQuantity + $newQuantity;

        if (! $this->hasUnlimitedPortfolio($user) && $totalAfterAdd > 50) {
            return response()->json([
                'message' => 'Card limit reached for Free plan. Maximum 50 cards per portfolio allowed.',
                'current_total' => $currentTotal,
                'limit' => 50,
                'upgrade_url' => '/subscribe',
            ], 403);
        }

        if ($pivot) {
            // If it exists, update the quantity
            $portfolio->userCards()->updateExistingPivot($userCard->id, ['quantity' => $newQuantity]);
        } else {
            // If not, attach it with the new quantity
            $portfolio->userCards()->attach($userCard->id, ['quantity' => $validated['quantity']]);
        }

        // Clear portfolio value cache after modification
        $this->clearPortfolioValueCache($portfolio);

        // Record purchase in ledger (cash out)
        app(\App\Services\PortfolioTradingService::class)->recordPurchase($portfolio, $userCard, (int) $validated['quantity']);

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

        // Block modifications on locked portfolios for Free plan users
        $user = $request->user();
        if ($this->isPortfolioLocked($user, $portfolio)) {
            return $this->lockedPortfolioResponse();
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

        // Check if this card is already in the portfolio to calculate the actual quantity increase
        $existingPivot = $portfolio->userCards()->where('user_card_id', $userCard->id)->first();
        $currentQuantity = $existingPivot ? ($existingPivot->pivot->quantity ?? 0) : 0;
        $quantityIncrease = $validated['quantity'] - $currentQuantity;

        // Only check limit if we're actually adding cards (not just updating existing ones)
        if ($quantityIncrease > 0) {
            // Check card limit for free plan users (50 cards per portfolio)
            if (! $this->canAddCards($user, $portfolio, $quantityIncrease)) {
                $currentTotal = $this->getTotalCardCount($portfolio);
                return response()->json([
                    'message' => 'Card limit reached for Free plan. Maximum 50 cards per portfolio allowed.',
                    'current_total' => $currentTotal,
                    'limit' => 50,
                    'upgrade_url' => '/subscribe',
                ], 403);
            }
        }

        // Aggiungi o aggiorna la carta nel portafoglio
        $portfolio->userCards()->syncWithoutDetaching([
            $validated['user_card_id'] => ['quantity' => $validated['quantity']],
        ]);

        // Clear portfolio value cache after modification
        $this->clearPortfolioValueCache($portfolio);

        // Record purchase in ledger (cash out) - only record the actual increase, not the total quantity
        if ($quantityIncrease > 0) {
            app(\App\Services\PortfolioTradingService::class)->recordPurchase($portfolio, $userCard, (int) $quantityIncrease);
        }

        return response()->json($portfolio->load('userCards'));
    }

    public function updateUserCardQuantity(Request $request, Portfolio $portfolio, UserCard $userCard): JsonResponse
    {
        if ($request->user()->id !== $portfolio->user_id || $request->user()->id !== $userCard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Block modifications on locked portfolios for Free plan users
        $user = $request->user();
        if ($this->isPortfolioLocked($user, $portfolio)) {
            return $this->lockedPortfolioResponse();
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        // Check if updating quantity would exceed free plan limit
        if (! $this->hasUnlimitedPortfolio($user)) {
            $existingPivot = $portfolio->userCards()->where('user_card_id', $userCard->id)->first();
            if ($existingPivot) {
                $currentQuantity = $existingPivot->pivot->quantity ?? 1;
                $quantityDifference = $validated['quantity'] - $currentQuantity;

                if ($quantityDifference > 0 && ! $this->canAddCards($user, $portfolio, $quantityDifference)) {
                    $currentTotal = $this->getTotalCardCount($portfolio);
                    return response()->json([
                        'message' => 'Card limit reached for Free plan. Maximum 50 cards per portfolio allowed.',
                        'current_total' => $currentTotal,
                        'limit' => 50,
                        'upgrade_url' => '/subscribe',
                    ], 403);
                }
            }
        }

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

            // Clear portfolio value cache after modification
            $this->clearPortfolioValueCache($portfolio);

            // Load all necessary relationships that the frontend expects
            $portfolio->load([
                'userCards.blueprint.expansion',
                'userCards.blueprint.latestPrice',
                'userCards.blueprint.cardPrices', // Eager load all prices to avoid N+1 in estimated_value_cents accessor
            ])->loadCount('userCards');

            return response()->json($portfolio);
        }

        return response()->json(['message' => 'Card not in portfolio'], 404);
    }

    /**
     * Remove a quantity of cards from a portfolio by blueprint, condition, and language.
     */
    public function removeBlueprint(Request $request, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);

        // Block modifications on locked portfolios for Free plan users
        $user = $request->user();
        if ($this->isPortfolioLocked($user, $portfolio)) {
            return $this->lockedPortfolioResponse();
        }

        $validated = $request->validate([
            'blueprint_id' => 'required|exists:blueprints,id',
            'condition' => 'required|string|max:50',
            'language' => 'required|string|max:10',
            'quantity' => 'required|integer|min:1',
        ]);

        // Find all UserCards matching the criteria
        $matchingCards = $user->userCards()
            ->where('blueprint_id', $validated['blueprint_id'])
            ->where('condition', $validated['condition'])
            ->where('language', $validated['language'])
            ->get();

        if ($matchingCards->isEmpty()) {
            return response()->json(['message' => 'No matching cards found'], 404);
        }

        // Find all cards in the portfolio that match the group
        $portfolioCards = $portfolio->userCards()
            ->whereIn('user_card_id', $matchingCards->pluck('id'))
            ->get();

        if ($portfolioCards->isEmpty()) {
            return response()->json(['message' => 'No matching cards found in portfolio'], 404);
        }

        // Calculate total available quantity in portfolio
        $totalAvailable = $portfolioCards->sum(function ($card) {
            return $card->pivot->quantity ?? 0;
        });

        if ($validated['quantity'] > $totalAvailable) {
            return response()->json([
                'message' => 'Insufficient quantity. Only ' . $totalAvailable . ' card(s) available.',
                'available' => $totalAvailable,
            ], 400);
        }

        // Remove the specified quantity from matching cards
        $remainingToRemove = $validated['quantity'];

        foreach ($portfolioCards as $portfolioCard) {
            if ($remainingToRemove <= 0) {
                break;
            }

            $currentQuantity = $portfolioCard->pivot->quantity ?? 0;
            $quantityToRemoveFromThisCard = min($remainingToRemove, $currentQuantity);
            $newQuantity = $currentQuantity - $quantityToRemoveFromThisCard;

            if ($newQuantity <= 0) {
                // Remove completely from portfolio
                $portfolio->userCards()->detach($portfolioCard->id);

                // Check if UserCard is still in other portfolios
                $otherPortfoliosCount = $portfolioCard->portfolios()
                    ->where('portfolios.id', '!=', $portfolio->id)
                    ->count();

                // If not in any other portfolio, delete the UserCard
                if ($otherPortfoliosCount === 0) {
                    $portfolioCard->delete();
                }
            } else {
                // Update quantity
                $portfolio->userCards()->updateExistingPivot($portfolioCard->id, [
                    'quantity' => $newQuantity,
                ]);
            }

            $remainingToRemove -= $quantityToRemoveFromThisCard;
        }

        // Clear portfolio value cache after modification
        $this->clearPortfolioValueCache($portfolio);

        // Load updated portfolio with relationships
        $portfolio->load([
            'userCards.blueprint.expansion',
            'userCards.blueprint.latestPrice',
            'userCards.blueprint.cardPrices',
        ])->loadCount('userCards');

        return response()->json($portfolio);
    }

    public function removeUserCard(Request $request, Portfolio $portfolio, UserCard $userCard): JsonResponse
    {
        if ($request->user()->id !== $portfolio->user_id || $request->user()->id !== $userCard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Block modifications on locked portfolios for Free plan users
        $user = $request->user();
        if ($this->isPortfolioLocked($user, $portfolio)) {
            return $this->lockedPortfolioResponse();
        }

        // Check if the UserCard is in this portfolio
        if (! $portfolio->userCards()->where('user_card_id', $userCard->id)->exists()) {
            return response()->json(['message' => 'Card not found in portfolio'], 404);
        }

        // Remove the card from this portfolio (pivot table)
        $portfolio->userCards()->detach($userCard->id);

        // Clear portfolio value cache after modification
        $this->clearPortfolioValueCache($portfolio);

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
        $validated = $request->validate([
            'period' => 'nullable|string|in:1D,1W,1M,3M,1Y,MAX',
            'metric' => 'nullable|string|in:market_value,cost_basis,performance',
        ]);

        // Check authorization
        if ($request->user()->id !== $portfolio->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $period = $validated['period'] ?? '1M';
        $metric = $validated['metric'] ?? 'market_value';

        $service = app(\App\Services\PortfolioValueHistoryService::class);
        $valueHistory = $service->calculateHistory($portfolio, $period, $metric);

        return response()->json($valueHistory);
    }
}
