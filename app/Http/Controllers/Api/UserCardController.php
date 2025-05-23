<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserCardResource;
use App\Models\Blueprint;
use App\Models\UserCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

class UserCardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Basic index, might need pagination or filtering in future
        $userCards = Auth::user()->userCards()->with('blueprint.latestPrice')->latest()->paginate(20);
        return UserCardResource::collection($userCards);
    }

    public function store(Request $request): UserCardResource
    {
        $validatedData = $request->validate([
            'blueprint_id' => 'required|exists:blueprints,id',
            'condition' => 'required|string|in:NM,LP,MP,HP,DMG',
            'language' => 'required|string|max:10',
            'purchase_price_cents' => 'nullable|integer|min:0',
            'purchase_currency' => 'nullable|string|max:3',
            'acquired_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        // Automatic purchase_price_cents population
        if (!isset($validatedData['purchase_price_cents']) || is_null($validatedData['purchase_price_cents'])) {
            $blueprint = Blueprint::with('latestPrice')->find($validatedData['blueprint_id']);
            if ($blueprint && $blueprint->latestPrice && !is_null($blueprint->latestPrice->price_cents)) {
                $validatedData['purchase_price_cents'] = $blueprint->latestPrice->price_cents;
                // Optionally set a default currency if price is auto-populated and currency is missing
                if (empty($validatedData['purchase_currency'])) {
                    $validatedData['purchase_currency'] = 'EUR'; // Or your default currency
                }
            }
        }

        $userCard = Auth::user()->userCards()->create($validatedData);
        $userCard->load('blueprint.latestPrice'); // Ensure relation is loaded for the response

        return new UserCardResource($userCard);
    }

    public function show(UserCard $userCard): UserCardResource
    {
        $this->authorize('view', $userCard);
        $userCard->load('blueprint.latestPrice');
        return new UserCardResource($userCard);
    }

    public function update(Request $request, UserCard $userCard): UserCardResource
    {
        $this->authorize('update', $userCard);

        $validatedData = $request->validate([
            // Do not allow blueprint_id to be updated directly here, handle via separate logic if needed
            'condition' => 'sometimes|required|string|in:NM,LP,MP,HP,DMG',
            'language' => 'sometimes|required|string|max:10',
            'purchase_price_cents' => 'nullable|integer|min:0',
            'purchase_currency' => 'nullable|string|max:3',
            'acquired_at' => 'nullable|date',
            'sold_price_cents' => 'nullable|integer|min:0',
            'sold_currency' => 'nullable|string|max:3',
            'sold_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $userCard->update($validatedData);
        $userCard->load('blueprint.latestPrice');

        return new UserCardResource($userCard);
    }

    public function destroy(UserCard $userCard): Response
    {
        $this->authorize('delete', $userCard);
        $userCard->delete();
        return response()->noContent();
    }
}
