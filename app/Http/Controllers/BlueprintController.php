<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
use App\Models\Expansion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Str; // Added for Str::title

class BlueprintController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'category_id' => 'nullable|integer|exists:blueprints,ct_category_id',
            'expansion_id' => 'nullable|integer|exists:expansions,id', // Validate expansion_id
        ]);

        $selectedCategoryId = $request->input('category_id');
        $selectedExpansionId = $request->input('expansion_id'); // Get expansion_id from request
        $defaultCategoryId = 192;

        $blueprints = Blueprint::with(['expansion', 'latestPrice'])
            ->when($selectedCategoryId !== null, function ($query) use ($selectedCategoryId) {
                return $query->where('ct_category_id', $selectedCategoryId);
            }, function ($query) use ($defaultCategoryId) {
                return $query->where('ct_category_id', $defaultCategoryId);
            })
            ->when($selectedExpansionId, function ($query, $expansionId) { // Add when clause for expansion_id
                return $query->where('expansion_id', $expansionId);
            })
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        // Get CardTrader category configurations
        $cardtraderCategoriesConfig = config('services.cardtrader');
        $categoryNameMapping = [];
        if (is_array($cardtraderCategoriesConfig)) {
            foreach ($cardtraderCategoriesConfig as $key => $value) {
                if (Str::endsWith($key, '_category_id') && is_numeric($value)) {
                    // Extract a readable name from the key
                    // e.g., 'one_piece_single_card_category_id' becomes 'Single Card'
                    $name = Str::title(str_replace('_', ' ', Str::remove('_category_id', Str::remove('one_piece_', $key))));
                    $categoryNameMapping[(int)$value] = $name;
                }
            }
        }

        $availableCategories = Blueprint::select('ct_category_id')
            ->distinct()
            ->orderBy('ct_category_id')
            ->pluck('ct_category_id')
            ->map(function ($id) use ($categoryNameMapping) {
                return [
                    'id' => $id,
                    'name' => $categoryNameMapping[$id] ?? (string) $id // Use mapped name or default to ID
                ];
            });

        // Get available expansions that have at least one blueprint
        $availableExpansions = Expansion::whereHas('blueprints')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Dashboard/Blueprints/Index', [
            'blueprints' => $blueprints,
            'filters' => [
                'category_id' => $selectedCategoryId ?? $defaultCategoryId,
                'expansion_id' => $selectedExpansionId, // Pass current expansion filter
            ],
            'availableCategories' => $availableCategories,
            'availableExpansions' => $availableExpansions, // Pass available expansions
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Blueprint $blueprint): Response
    {
        $blueprint->load(['expansion', 'latestPrice']);

        return Inertia::render('Dashboard/Blueprints/Show', [
            'blueprint' => $blueprint,
        ]);
    }
}
