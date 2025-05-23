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
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_currency' => 'nullable|string|max:3',
            'acquired_at' => 'nullable|date',
            'notes' => 'nullable|string',
            // 'portfolio_id' => 'nullable|exists:portfolios,id', // Opzionale: aggiungi direttamente a un portfolio
            // 'quantity' => 'required_with:portfolio_id|integer|min:1' // Opzionale: quantità se si aggiunge a un portfolio
        ]);

        // Verifica che il blueprint esista
        $blueprint = Blueprint::find($validated['blueprint_id']);
        if (!$blueprint) {
            return response()->json(['message' => 'Blueprint not found.'], 404);
        }

        $userCard = Auth::user()->userCards()->create([
            'blueprint_id' => $validated['blueprint_id'],
            'condition' => $validated['condition'],
            'language' => $validated['language'],
            'purchase_price_cents' => isset($validated['purchase_price']) ? (int)round($validated['purchase_price'] * 100) : null,
            'purchase_currency' => $validated['purchase_currency'] ?? 'EUR',
            'acquired_at' => $validated['acquired_at'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Logica per aggiungere a un portfolio se portfolio_id è fornito
        // if (isset($validated['portfolio_id'])) {
        //     $portfolio = Auth::user()->portfolios()->find($validated['portfolio_id']);
        //     if ($portfolio) {
        //         $portfolio->userCards()->attach($userCard->id, ['quantity' => $validated['quantity'] ?? 1]);
        //     }
        // }

        return response()->json($userCard->load(['blueprint.expansion', 'blueprint.latestPrice']), 201);
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
            $updateData['purchase_price_cents'] = (int)round($validated['purchase_price'] * 100);
            $updateData['purchase_currency'] = $validated['purchase_currency'] ?? $userCard->purchase_currency ?? 'EUR';
        } elseif (array_key_exists('purchase_price', $validated) && is_null($validated['purchase_price'])) {
            $updateData['purchase_price_cents'] = null;
            $updateData['purchase_currency'] = null;
        }

        if (isset($validated['sold_price'])) {
            $updateData['sold_price_cents'] = (int)round($validated['sold_price'] * 100);
            $updateData['sold_currency'] = $validated['sold_currency'] ?? $userCard->sold_currency ?? 'EUR';
            $updateData['sold_at'] = $validated['sold_at'] ?? now();
        } elseif (array_key_exists('sold_price', $validated) && is_null($validated['sold_price'])) {
            $updateData['sold_price_cents'] = null;
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
