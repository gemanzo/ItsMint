<?php

namespace App\Http\Controllers;

use App\Enums\CardCondition;
use App\Enums\CardLanguage;
use App\Models\Blueprint;
use App\Models\UserCard;
use App\Rules\ValidCardCondition;
use App\Rules\ValidCardLanguage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserCardController extends Controller
{
    /**
     * Display a listing of the resource.
     * Questa rotta potrebbe non essere usata direttamente se le card sono sempre viste nel contesto di un portfolio.
     */
    public function index(Request $request): JsonResponse
    {
        $userCards = Auth::user()->userCards()->with([
            'blueprint.expansion',
            'blueprint.latestPrice',
            'blueprint.cardPrices', // Eager load all prices to avoid N+1 in estimated_value_cents accessor
        ])->latest()->get();

        return response()->json($userCards);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blueprint_id' => 'required|exists:blueprints,id',
            'condition' => ['required', 'string', new ValidCardCondition],
            'language' => ['required', 'string', new ValidCardLanguage],
            'quantity' => 'required|integer|min:1|max:50',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_currency' => 'nullable|string|max:3',
            'acquired_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Store the canonical vocabulary, whatever spelling the client sent ("Near Mint", "EN", ...)
        $validated['condition'] = CardCondition::normalize($validated['condition']);
        $validated['language'] = CardLanguage::normalize($validated['language']);

        $blueprint = Blueprint::find($validated['blueprint_id']);
        if (! $blueprint) {
            return response()->json(['message' => 'Blueprint not found.'], 404);
        }

        $quantity = $validated['quantity'];

        // Auto-populate purchase_price only if not provided by user (null, empty, or not set)
        // Note: 0 is a valid purchase price (free card), so we don't override it
        if (
            ! array_key_exists('purchase_price', $validated) ||
            is_null($validated['purchase_price']) ||
            $validated['purchase_price'] === ''
        ) {

            $userLanguage = $validated['language'];
            $userCondition = $validated['condition'];

            // Try to get exact match for language and condition first.
            // Market prices use CardTrader's condition names (e.g. LP = "Slightly Played").
            $specificPrice = $blueprint->cardPrices()
                ->where('language', $userLanguage)
                ->where('condition', CardCondition::from($userCondition)->marketConditionName())
                ->orderBy('fetched_at', 'desc')
                ->first();

            if ($specificPrice && ! is_null($specificPrice->price_cents)) {
                $validated['purchase_price'] = $specificPrice->price_cents / 100; // Convert to euros
            } else {
                // Fallback to latest price for the language (any condition)
                $languagePrice = $blueprint->cardPrices()
                    ->where('language', $userLanguage)
                    ->orderBy('fetched_at', 'desc')
                    ->first();

                if ($languagePrice && ! is_null($languagePrice->price_cents)) {
                    // Apply condition adjustment to the language-specific price
                    $adjustedPriceCents = UserCard::calculateEstimatedPriceCents(
                        $languagePrice->price_cents,
                        $userCondition
                    );
                    $validated['purchase_price'] = $adjustedPriceCents / 100; // Convert to euros
                } else {
                    // Final fallback to latest price (any language/condition) with condition adjustment
                    $blueprint = Blueprint::with('latestPrice')->find($validated['blueprint_id']);
                    if ($blueprint && $blueprint->latestPrice && ! is_null($blueprint->latestPrice->price_cents)) {
                        $adjustedPriceCents = UserCard::calculateEstimatedPriceCents(
                            $blueprint->latestPrice->price_cents,
                            $userCondition
                        );
                        $validated['purchase_price'] = $adjustedPriceCents / 100; // Convert to euros
                    }
                }
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
            $userCard->load([
                'blueprint.expansion',
                'blueprint.latestPrice',
                'blueprint.cardPrices', // Eager load all prices to avoid N+1 in estimated_value_cents accessor
            ]);
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

        return response()->json($userCard->load([
            'blueprint.expansion',
            'blueprint.latestPrice',
            'blueprint.cardPrices', // Eager load all prices to avoid N+1 in estimated_value_cents accessor
            'portfolios',
        ]));
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
            'condition' => ['sometimes', 'required', 'string', new ValidCardCondition],
            'language' => ['sometimes', 'required', 'string', new ValidCardLanguage],
            // Preferred: integer cents, no float conversion involved
            'purchase_price_cents' => 'nullable|integer|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_currency' => 'nullable|string|max:3',
            'acquired_at' => 'nullable|date',
            'sold_price' => 'nullable|numeric|min:0',
            'sold_currency' => 'nullable|string|max:3',
            'sold_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Store the canonical vocabulary, whatever spelling the client sent
        if (isset($validated['condition'])) {
            $validated['condition'] = CardCondition::normalize($validated['condition']);
        }
        if (isset($validated['language'])) {
            $validated['language'] = CardLanguage::normalize($validated['language']);
        }

        $updateData = [
            'condition' => $validated['condition'] ?? $userCard->condition,
            'language' => $validated['language'] ?? $userCard->language,
            'acquired_at' => $validated['acquired_at'] ?? $userCard->acquired_at,
            'notes' => $validated['notes'] ?? $userCard->notes,
        ];

        if (array_key_exists('purchase_price_cents', $validated)) {
            // null clears the purchase price (unknown cost)
            $updateData['purchase_price_cents'] = $validated['purchase_price_cents'];
            $updateData['purchase_currency'] = $validated['purchase_currency'] ?? $userCard->purchase_currency ?? 'EUR';
        } elseif (isset($validated['purchase_price'])) {
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
