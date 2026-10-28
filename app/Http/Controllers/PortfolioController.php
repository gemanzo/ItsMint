<?php

namespace App\Http\Controllers;

use App\Enums\CardCondition;
use App\Enums\CardLanguage;
use App\Models\Portfolio;
use App\Models\UserCard;
use App\Rules\ValidCardCondition;
use App\Rules\ValidCardLanguage;
use App\Services\PortfolioTradingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            'condition' => ['required', 'string', new ValidCardCondition],
            'language' => ['required', 'string', new ValidCardLanguage],
            'quantity' => 'required|integer|min:1',
            // 0 is a valid cost: pulled from a pack, received as a gift, ...
            'purchase_price_cents' => 'required|integer|min:0',
            // Optional, defaults to today; a card can't be acquired in the future
            'acquired_at' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Store the canonical vocabulary, whatever spelling the client sent ("Near Mint", "EN", ...)
        $validated['condition'] = CardCondition::normalize($validated['condition']);
        $validated['language'] = CardLanguage::normalize($validated['language']);

        $user = $request->user();

        if (empty($validated['acquired_at'])) {
            $acquiredAt = today();
        } else {
            $acquiredAt = \Carbon\Carbon::parse($validated['acquired_at']);
        }

        // Check card limit for free plan users (50 cards per portfolio)
        $currentTotal = $this->getTotalCardCount($portfolio);
        if (! $this->hasUnlimitedPortfolio($user) && $currentTotal + $validated['quantity'] > 50) {
            return response()->json([
                'message' => 'Card limit reached for Free plan. Maximum 50 cards per portfolio allowed.',
                'current_total' => $currentTotal,
                'limit' => 50,
                'upgrade_url' => '/subscribe',
            ], 403);
        }

        // Every addition is a new purchase lot with its own price and date: the copies
        // already owned keep their purchase price (it used to be overwritten here)
        $purchaseLot = app(PortfolioTradingService::class)->addLot(
            $portfolio,
            $user,
            (int) $validated['blueprint_id'],
            $validated['condition'],
            $validated['language'],
            (int) $validated['quantity'],
            (int) $validated['purchase_price_cents'],
            $acquiredAt,
            $validated['notes'] ?? null,
        );

        // Clear portfolio value cache after modification
        $this->clearPortfolioValueCache($portfolio);

        return response()->json($purchaseLot->load('blueprint'), 201);
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

        // The purchase transaction of the lot follows its quantity in this portfolio
        app(PortfolioTradingService::class)->syncLotPurchaseTransaction($portfolio, $userCard);

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

        if (! $portfolio->userCards()->where('user_card_id', $userCard->id)->exists()) {
            return response()->json(['message' => 'Card not in portfolio'], 404);
        }

        // Only this purchase lot changes: the other lots of the card keep their own
        // quantity, price and date (they used to be merged into this one)
        app(PortfolioTradingService::class)->changeLotQuantity($portfolio, $userCard, (int) $validated['quantity']);

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
            'condition' => ['required', 'string', new ValidCardCondition],
            'language' => ['required', 'string', new ValidCardLanguage],
            'quantity' => 'required|integer|min:1',
            // When given, the copies are removed from this purchase lot only
            'user_card_id' => 'nullable|integer',
        ]);

        // Store the canonical vocabulary, whatever spelling the client sent ("Near Mint", "EN", ...)
        $validated['condition'] = CardCondition::normalize($validated['condition']);
        $validated['language'] = CardLanguage::normalize($validated['language']);

        if (! empty($validated['user_card_id'])) {
            $cardToRemove = $user->userCards()->find($validated['user_card_id']);
        } else {
            // Any lot of the card: the service takes the most recent lots first
            $cardToRemove = $user->userCards()
                ->where('blueprint_id', $validated['blueprint_id'])
                ->where('condition', $validated['condition'])
                ->where('language', $validated['language'])
                ->first();
        }

        if (! $cardToRemove) {
            return response()->json(['message' => 'No matching cards found'], 404);
        }

        try {
            app(PortfolioTradingService::class)->removeCopies(
                $portfolio,
                $cardToRemove,
                (int) $validated['quantity'],
                onlyFromThisLot: ! empty($validated['user_card_id']),
            );
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 400);
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

        // Remove every copy of this lot from the portfolio; the lot is deleted when no
        // other portfolio holds it and no sale refers to it (sales keep their history)
        $quantityInPortfolio = (int) $portfolio->userCards()->where('user_card_id', $userCard->id)->first()->pivot->quantity;
        app(PortfolioTradingService::class)->removeCopies($portfolio, $userCard, $quantityInPortfolio, onlyFromThisLot: true);

        // Clear portfolio value cache after modification
        $this->clearPortfolioValueCache($portfolio);

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
