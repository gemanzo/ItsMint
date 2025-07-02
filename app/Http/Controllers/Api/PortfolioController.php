<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response; // Added for Response status codes
use Illuminate\Support\Facades\Auth;

class PortfolioController extends Controller
{
    /**
     * Display a listing of the user's portfolios.
     */
    public function index(): JsonResource
    {
        $user = Auth::user();
        $portfolios = $user->portfolios()->with([
            'userCards.blueprint.latestPrice', // Ensure blueprint.latestPrice is loaded
            'userCards.user', // Keep user relationship if needed, or remove if not directly used by client here
        ])->withCount('userCards')->get();

        return PortfolioResource::collection($portfolios);
    }

    /**
     * Store a newly created portfolio in storage.
     */
    // ... existing code ...

    public function show(Portfolio $portfolio): JsonResource
    {
        $this->authorize('view', $portfolio); // Ensure user owns the portfolio

        $portfolio->load([
            'userCards.blueprint.latestPrice', // Ensure blueprint.latestPrice is loaded
            'userCards.user', // Keep user relationship if needed
        ])->loadCount('userCards');

        return new PortfolioResource($portfolio);
    }

    /**
     * Update the specified portfolio in storage.
     */
    // ... existing code ...

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Portfolio $portfolio): JsonResponse
    {
        $this->authorize('delete', $portfolio);

        $portfolio->delete();

        return response()->json(null, 204);
    }
}
