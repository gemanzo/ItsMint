<?php

namespace App\Http\Controllers;

use App\Models\UserCard;
use App\Models\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class UserCardController extends Controller
{
    /**
     * Display a listing of the resource.
     * Questa rotta potrebbe non essere usata direttamente se le card sono sempre viste nel contesto di un portfolio.
     */
    public function index(Request $request): JsonResponse
    {
        $userCards = Auth::user()->userCards()->with(['blueprint.expansion', 'blueprint.latestPrice'])->latest()->get();
        return response()->json($userCards);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blueprint_id' => 'required|exists:blueprints,id',
            'condition' => 'required|string|max:50',
            'language' => 'required|string|max:10',
            'quantity' => 'required|integer|min:1|max:50',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_currency' => 'nullable|string|max:3',
            'acquired_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $blueprint = Blueprint::find($validated['blueprint_id']);
        if (!$blueprint) {
            return response()->json(['message' => 'Blueprint not found.'], 404);
        }

        $quantity = $validated['quantity'];

        // Auto-populate purchase_price if not provided
        if (!isset($validated['purchase_price']) || is_null($validated['purchase_price'])) {
            $blueprint = Blueprint::with('latestPrice')->find($validated['blueprint_id']);
            if ($blueprint && $blueprint->latestPrice && !is_null($blueprint->latestPrice->price_cents)) {
                $validated['purchase_price'] = $blueprint->latestPrice->price_cents / 100; // Convert to euros
            }
        }

        $userCardData = [
            'blueprint_id' => $validated['blueprint_id'],
            'condition' => $validated['condition'],
            'language' => $validated['language'],
            'purchase_price' => $validated['purchase_price'] ?? null,
            'purchase_currency' => $validated['purchase_currency'] ?? 'EUR',
            'acquired_at' => $validated['acquired_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $createdCards = [];

        // Create multiple UserCard records based on quantity
        for ($i = 0; $i < $quantity; $i++) {
            $userCard = Auth::user()->userCards()->create($userCardData);
            $userCard->load(['blueprint.expansion', 'blueprint.latestPrice']);
            $createdCards[] = $userCard;
        }

        // Return single card if quantity = 1, array if multiple
        if ($quantity === 1) {
            return response()->json($createdCards[0], 201);
        }

        return response()->json($createdCards, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, UserCard $userCard): JsonResponse
    {
        if ($request->user()->id !== $userCard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return response()->json($userCard->load(['blueprint.expansion', 'blueprint.latestPrice', 'portfolios']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, UserCard $userCard): JsonResponse
    {
        if ($request->user()->id !== $userCard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'condition' => 'sometimes|required|string|max:50',
            'language' => 'sometimes|required|string|max:10',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_currency' => 'nullable|string|max:3',
            'acquired_at' => 'nullable|date',
            'sold_price' => 'nullable|numeric|min:0',
            'sold_currency' => 'nullable|string|max:3',
            'sold_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $updateData = [
            'condition' => $validated['condition'] ?? $userCard->condition,
            'language' => $validated['language'] ?? $userCard->language,
            'acquired_at' => $validated['acquired_at'] ?? $userCard->acquired_at,
            'notes' => $validated['notes'] ?? $userCard->notes,
        ];

        if (isset($validated['purchase_price'])) {
            $updateData['purchase_price'] = $validated['purchase_price'];
            $updateData['purchase_currency'] = $validated['purchase_currency'] ?? $userCard->purchase_currency ?? 'EUR';
        } elseif (array_key_exists('purchase_price', $validated) && is_null($validated['purchase_price'])) {
            $updateData['purchase_price'] = null;
            $updateData['purchase_currency'] = null;
        }

        if (isset($validated['sold_price'])) {
            $updateData['sold_price'] = $validated['sold_price'];
            $updateData['sold_currency'] = $validated['sold_currency'] ?? $userCard->sold_currency ?? 'EUR';
            $updateData['sold_at'] = $validated['sold_at'] ?? now();
        } elseif (array_key_exists('sold_price', $validated) && is_null($validated['sold_price'])) {
            $updateData['sold_price'] = null;
            $updateData['sold_currency'] = null;
            $updateData['sold_at'] = null;
        }

        $userCard->update($updateData);

        return response()->json($userCard->load(['blueprint.expansion', 'blueprint.latestPrice']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, UserCard $userCard): JsonResponse
    {
        if ($request->user()->id !== $userCard->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Prima di eliminare la UserCard, dissociala da tutti i portafogli
        $userCard->portfolios()->detach();
        $userCard->delete();

        return response()->json(null, 204);
    }
}
