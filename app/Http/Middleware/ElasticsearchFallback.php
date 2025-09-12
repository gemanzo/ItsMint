<?php

namespace App\Http\Middleware;

use App\Services\ElasticsearchService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ElasticsearchFallback
{
    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if this is a blueprint search request
        if ($request->is('api/blueprints/search*')) {
            try {
                // Test Elasticsearch availability
                if (! $this->elasticsearch->indexExists()) {
                    return $this->fallbackToDatabase($request);
                }
            } catch (\Exception $e) {
                \Log::error('Elasticsearch unavailable, falling back to database', [
                    'error' => $e->getMessage(),
                    'route' => $request->path(),
                ]);

                return $this->fallbackToDatabase($request);
            }
        }

        return $next($request);
    }

    /**
     * Fallback to database search when Elasticsearch is unavailable
     */
    private function fallbackToDatabase(Request $request): Response
    {
        // Redirect to legacy database search controller
        $query = $request->input('q', '');
        $language = $request->input('language');
        $size = $request->input('size', 20);

        try {
            $blueprintController = app(\App\Http\Controllers\BlueprintController::class);

            // Create a new request with the expected format for legacy search
            $fallbackRequest = new Request([
                'query' => $query,
                'language' => $language,
            ]);

            $response = $blueprintController->search($fallbackRequest);
            $data = $response->getData(true);

            // Transform legacy response to match Elasticsearch format
            return response()->json([
                'success' => true,
                'query' => $query,
                'pagination' => [
                    'total' => count($data),
                    'size' => $size,
                    'from' => 0,
                    'has_more' => false,
                ],
                'max_score' => 1.0,
                'data' => array_slice($data, 0, $size),
                'fallback' => true,
                'message' => 'Using database fallback (Elasticsearch unavailable)',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search service temporarily unavailable',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 503);
        }
    }
}
