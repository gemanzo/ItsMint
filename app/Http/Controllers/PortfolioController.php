<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\UserCard;
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

        if ($portfolio->userCards()->detach($userCard->id)) {
            return response()->json(null, 204);
        }

        return response()->json(['message' => 'Card not found in portfolio'], 404);
    }
}
