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
            'sort' => 'nullable|string|in:price_asc,price_desc,name_asc,name_desc',
            'language' => 'nullable|string',
        ]);

        $selectedExpansionId = $request->input('expansion_id');
        $sort = $request->input('sort');
        // Default EN when not provided
        $language = strtolower($request->input('language', 'en'));

        $blueprints = Blueprint::with(['expansion'])
            ->where('ct_category_id', 192) // Only show single cards
            ->when($selectedExpansionId, function ($query, $expansionId) {
                return $query->where('expansion_id', $expansionId);
            })
            ->when($language !== '' && $language !== null, function ($query) use ($language) {
                // Use MV presence instead of raw card_prices existence for performance
                return $query->whereExists(function ($sub) use ($language) {
                    $sub->selectRaw('1')
                        ->from('card_latest_prices_mv as mv')
                        ->whereColumn('mv.blueprint_id', 'blueprints.id')
                        ->where('mv.language', $language);
                });
            });

        // Apply sorting
        if ($sort) {
            switch ($sort) {
                case 'price_asc':
                    $blueprints->leftJoin('card_latest_prices_mv as mv', function ($join) use ($language) {
                        $join->on('blueprints.id', '=', 'mv.blueprint_id');
                        if (!empty($language)) {
                            $join->where('mv.language', $language);
                        }
                    })->orderByRaw('COALESCE(mv.price_cents, 999999999) ASC')
                        ->select('blueprints.*');
                    break;
                case 'price_desc':
                    $blueprints->leftJoin('card_latest_prices_mv as mv', function ($join) use ($language) {
                        $join->on('blueprints.id', '=', 'mv.blueprint_id');
                        if (!empty($language)) {
                            $join->where('mv.language', $language);
                        }
                    })->orderByRaw('COALESCE(mv.price_cents, 0) DESC')
                        ->select('blueprints.*');
                    break;
                case 'name_asc':
                    $blueprints->orderBy('name', 'asc');
                    break;
                case 'name_desc':
                    $blueprints->orderBy('name', 'desc');
                    break;
                default:
                    $blueprints->orderBy('name');
            }
        } else {
            $blueprints->orderBy('name');
        }

        $blueprints = $blueprints->paginate(30)->withQueryString();

        // Override latestPrice using MV when language is provided (default EN)
        if (! empty($language)) {
            $ids = $blueprints->getCollection()->pluck('id')->all();
            if (!empty($ids)) {
                $mv = \DB::table('card_latest_prices_mv')
                    ->whereIn('blueprint_id', $ids)
                    ->where('language', $language)
                    ->get()
                    ->keyBy('blueprint_id');

                $blueprints->getCollection()->transform(function (Blueprint $bp) use ($mv) {
                    $row = $mv->get($bp->id);
                    if ($row) {
                        // Create a lightweight object mimicking CardPrice attributes expected by FE
                        $bp->setRelation('latestPrice', new \App\Models\CardPrice([
                            'blueprint_id' => $row->blueprint_id,
                            'language' => $row->language,
                            'is_foil' => $row->is_foil,
                            'condition' => $row->condition,
                            'price_cents' => $row->price_cents,
                            'fetched_at' => $row->fetched_at,
                        ]));
                    }
                    return $bp;
                });
            }
        }

        // Get available expansions that have at least one blueprint
        $availableExpansions = Expansion::whereHas('blueprints')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Dashboard/Blueprints/Index', [
            'blueprints' => $blueprints,
            'filters' => [
                'expansion_id' => $selectedExpansionId,
                'sort' => $sort,
                'language' => $language,
            ],
            'availableExpansions' => $availableExpansions,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Blueprint $blueprint)
    {
        $blueprint->append('available_languages');

        // Eager load relationships for efficiency
        $blueprint->load(['expansion', 'latestPrice']);

        $pricesByLanguage = $this->getPricesByLanguage($blueprint);

        return Inertia::render('Dashboard/Blueprints/Show', [
            'blueprint' => $blueprint,
            'pricesByLanguage' => $pricesByLanguage,
            'userIsAdmin' => auth()->user()->hasRole('Admin'),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2',
            'expansion_id' => 'nullable|exists:expansions,id',
            'language' => 'nullable|string',
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

        $query = Blueprint::query()->with(['expansion']);

        // If language filter is provided, eager load language-specific prices
        if (! empty($validated['language'])) {
            $language = strtolower($validated['language']);
            $query->with(['cardPrices' => function ($q) use ($language) {
                $q->where('language', $language)
                    ->orderBy('condition_priority', 'asc')
                    ->orderBy('fetched_at', 'desc')
                    ->orderBy('price_cents', 'desc');
            }]);
        } else {
            // Otherwise, load the generic latestPrice
            $query->with('latestPrice');
        }

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

        $blueprints = $query->take(50)->get();

        // If language filter is provided, set the language-specific price as latestPrice relation
        if (! empty($validated['language'])) {
            $language = strtolower($validated['language']);
            $blueprints = $blueprints->filter(function (Blueprint $bp) use ($language) {
                // Use the eagerly loaded cardPrices relationship
                $lp = $bp->cardPrices->first();
                if ($lp) {
                    $bp->setRelation('latestPrice', $lp);
                    return true; // keep only if price exists for requested language
                }
                return false;
            })->values();
        }

        return response()->json($blueprints);
    }

    /**
     * Get prices grouped by language for a given blueprint.
     */
    private function getPricesByLanguage(Blueprint $blueprint)
    {
        // Eager load card prices with specific ordering
        $blueprint->load(['cardPrices' => function ($query) {
            $query->orderBy('language')->orderBy('condition')->orderBy('fetched_at', 'desc');
        }]);

        // Group prices by language for easier frontend consumption
        return $blueprint->cardPrices
            ->groupBy('language')
            ->map(function ($prices) {
                return $prices->groupBy('condition')->map(function ($conditionPrices) {
                    return $conditionPrices->first(); // Get latest for each condition
                });
            });
    }
}
