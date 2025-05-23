<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\UserCard;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PortfolioController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the user's portfolios.
     */
    public function index(Request $request): InertiaResponse
    {
        $user = Auth::user();
        $portfolios = $user->portfolios()->withCount('userCards')->latest()->get(); // Basic query, add pagination if needed

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
    public function show(Request $request, Portfolio $portfolio): InertiaResponse
    {
        $this->authorize('view', $portfolio);

        // Eager load necessary relationships for the detailed view
        $portfolio->load([
            'userCards.blueprint.latestPrice',
            // 'userCards.user' // if you need to display user info per card, usually not for own portfolio view
        ]);
        // If you need userCards to be paginated within the show view, adjust query accordingly.
        // For example, an alternative to loading all user_cards:
        // $userCards = $portfolio->userCards()->with('blueprint.latestPrice')->paginate(15);

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

        if (!$userCard) {
            return response()->json(['message' => 'UserCard not found or not owned by user.'], 404);
        }

        // Aggiungi o aggiorna la carta nel portafoglio
        $portfolio->userCards()->syncWithoutDetaching([
            $validated['user_card_id'] => ['quantity' => $validated['quantity']]
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
            $portfolio->userCards()->updateExistingPivot($userCard->id, ['quantity' => $validated['quantity']]);
            return response()->json($portfolio->load('userCards'));
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
