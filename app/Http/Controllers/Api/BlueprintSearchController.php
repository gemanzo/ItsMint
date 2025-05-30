<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ElasticsearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

final class BlueprintSearchController extends Controller
{
    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Search blueprints using Elasticsearch
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:255',
            'size' => 'integer|min:1|max:100',
            'from' => 'integer|min:0',
        ]);

        $query = $request->input('q');
        $size = $request->input('size', 20);
        $from = $request->input('from', 0);

        try {
            $results = $this->elasticsearch->searchBlueprints($query, $size, $from);

            return response()->json([
                'success' => true,
                'query' => $query,
                'pagination' => [
                    'total' => $results['total'],
                    'size' => $size,
                    'from' => $from,
                    'has_more' => ($from + $size) < $results['total']
                ],
                'max_score' => $results['max_score'],
                'data' => $results['hits'],
                'took' => microtime(true) - LARAVEL_START
            ]);
        } catch (\Exception $e) {
            \Log::error('Blueprint search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Search temporarily unavailable',
                'error' => app()->isLocal() ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get search suggestions (autocomplete)
     */
    public function suggestions(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
            'limit' => 'integer|min:1|max:10'
        ]);

        $query = $request->input('q');
        $limit = $request->input('limit', 5);

        try {
            // For now, use regular search with smaller size
            // TODO: Implement proper completion suggester in Elasticsearch
            $results = $this->elasticsearch->searchBlueprints($query, $limit);

            $suggestions = collect($results['hits'])->map(function ($hit) {
                return [
                    'id' => $hit['id'],
                    'name' => $hit['name'],
                    'expansion' => $hit['expansion']['name'] ?? null,
                    'expansion_code' => $hit['expansion']['code'] ?? null,
                    'image_url' => $hit['image_url'] ?? null,
                ];
            });

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions
            ]);
        } catch (\Exception $e) {
            \Log::error('Blueprint suggestions failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'suggestions' => []
            ]);
        }
    }

    /**
     * Get search stats and health
     */
    public function stats(): JsonResponse
    {
        try {
            // Check if Elasticsearch is healthy
            $indexExists = $this->elasticsearch->indexExists();

            return response()->json([
                'success' => true,
                'elasticsearch' => [
                    'available' => true,
                    'index_exists' => $indexExists,
                    'index_name' => $this->elasticsearch->getIndexName()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'elasticsearch' => [
                    'available' => false,
                    'error' => app()->isLocal() ? $e->getMessage() : 'Service unavailable'
                ]
            ], 503);
        }
    }
}
