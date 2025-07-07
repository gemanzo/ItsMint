<?php

namespace App\Http\Controllers;

use App\Models\Blueprint;
use App\Models\Expansion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia; // Added for Str::title
use Inertia\Response;

class BlueprintController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $request->validate([
            'expansion_id' => 'nullable|integer|exists:expansions,id',
        ]);

        $selectedExpansionId = $request->input('expansion_id');

        $blueprints = Blueprint::with(['expansion', 'latestPrice'])
            ->where('ct_category_id', 192) // Only show single cards
            ->when($selectedExpansionId, function ($query, $expansionId) {
                return $query->where('expansion_id', $expansionId);
            })
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        // Get available expansions that have at least one blueprint
        $availableExpansions = Expansion::whereHas('blueprints')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Dashboard/Blueprints/Index', [
            'blueprints' => $blueprints,
            'filters' => [
                'expansion_id' => $selectedExpansionId,
            ],
            'availableExpansions' => $availableExpansions,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Blueprint $blueprint): Response
    {
        $blueprint->load([
            'expansion',
            'latestPrice',
            'cardPrices' => function ($query) {
                $query->orderBy('language')->orderBy('condition')->orderBy('fetched_at', 'desc');
            },
        ]);

        // Group prices by language for easier frontend consumption
        $pricesByLanguage = $blueprint->cardPrices
            ->groupBy('language')
            ->map(function ($prices) {
                return $prices->groupBy('condition')->map(function ($conditionPrices) {
                    return $conditionPrices->first(); // Get latest for each condition
                });
            });

        return Inertia::render('Dashboard/Blueprints/Show', [
            'blueprint' => $blueprint,
            'pricesByLanguage' => $pricesByLanguage,
            'userIsAdmin' => auth()->user()?->hasRole('admin') ?? false,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'expansion_id' => 'nullable|exists:expansions,id',
        ]);

        $searchQuery = trim($validated['query']);
        $normalizedExpansionCodeForQuery = null;

        // Regex updated to better capture expansion codes like OP04, OP-04, ST1, ST-1 at the end of the query.
        // It looks for 2-3 letters, an optional hyphen, and 1-2 digits.
        if (preg_match('/\b([A-Z]{2,3}-?[0-9]{1,2})\b$/i', $searchQuery, $matches)) {
            $parsedCodeSegment = strtoupper($matches[1]); // e.g., "OP04" or "OP-04"
            // Normalize the parsed code by removing hyphens and dots (if any in future codes)
            $normalizedParsedCode = str_replace(['-', '.'], '', $parsedCodeSegment); // e.g., "OP04"

            // Attempt to find expansion by comparing normalized codes
            // Ensures DB codes like "OP-04" match search "op04" (normalized to "OP04")
            $expansion = Expansion::whereRaw('UPPER(REPLACE(REPLACE(code, \'-\', \'\'), \'.\', \'\')) = ?', [$normalizedParsedCode])->first();

            if ($expansion) {
                $normalizedExpansionCodeForQuery = $normalizedParsedCode; // Store the normalized code for the main query
                $searchQuery = trim(str_ireplace($matches[1], '', $searchQuery));
                // If searchQuery becomes empty, it means the user only searched for an expansion code.
                // The Blueprint query will then fetch all blueprints for that expansion.
            }
        }

        $query = Blueprint::query()->with(['expansion', 'latestPrice']);

        if (! empty($searchQuery)) {
            $query->where('name', 'ILIKE', '%' . $searchQuery . '%');
        }

        // Use the normalized expansion code for querying the relationship
        if ($normalizedExpansionCodeForQuery) {
            $query->whereHas('expansion', function ($q) use ($normalizedExpansionCodeForQuery) {
                // Compare against the normalized code in the database
                $q->whereRaw('UPPER(REPLACE(REPLACE(code, \'-\', \'\'), \'.\', \'\')) = ?', [$normalizedExpansionCodeForQuery]);
            });
        } elseif ($request->filled('expansion_id') && ! $normalizedExpansionCodeForQuery) {
            // Fallback to expansion_id if provided and no code was parsed from the query string
            $query->where('expansion_id', $validated['expansion_id']);
        }

        $blueprints = $query->take(15)->get();

        return response()->json($blueprints);
    }
}
