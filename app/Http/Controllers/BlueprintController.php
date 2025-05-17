<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
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
            'category_id' => 'nullable|integer|exists:blueprints,ct_category_id', // Validate if category_id is present
        ]);

        $selectedCategoryId = $request->input('category_id');
        $defaultCategoryId = 192; // Default to "single cards" category ID

        $blueprints = Blueprint::with(['expansion', 'latestPrice'])
            ->when($selectedCategoryId !== null, function ($query) use ($selectedCategoryId) {
                // If a category_id is provided in the request, use it
                return $query->where('ct_category_id', $selectedCategoryId);
            }, function ($query) use ($defaultCategoryId) {
                // Otherwise, apply the default category_id filter
                return $query->where('ct_category_id', $defaultCategoryId);
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

        return Inertia::render('Dashboard/Blueprints/Index', [
            'blueprints' => $blueprints,
            'filters' => ['category_id' => $selectedCategoryId ?? $defaultCategoryId], // Pass current or default filter back to view
            'availableCategories' => $availableCategories,
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
