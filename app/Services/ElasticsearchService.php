<?php

namespace App\Services;

use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Client;

class ElasticsearchService
{
    private Client $client;
    private string $indexName = 'blueprints';

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->setHosts([config('services.elasticsearch.host', 'elasticsearch:9200')])
            ->build();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    /**
     * Create the blueprints index with proper mapping
     */
    public function createIndex(): bool
    {
        $params = [
            'index' => $this->indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'blueprint_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding', 'synonym_filter']
                            ],
                            'expansion_code_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'keyword',
                                'filter' => ['lowercase', 'expansion_normalizer']
                            ]
                        ],
                        'filter' => [
                            'synonym_filter' => [
                                'type' => 'synonym',
                                'synonyms' => [
                                    'nm,near mint',
                                    'lp,lightly played',
                                    'mp,moderately played',
                                    'hp,heavily played',
                                    'dmg,damaged'
                                ]
                            ],
                            'expansion_normalizer' => [
                                'type' => 'pattern_replace',
                                'pattern' => '[\\-\\.]',
                                'replacement' => ''
                            ]
                        ]
                    ]
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'blueprint_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                                'completion' => [
                                    'type' => 'completion',
                                    'analyzer' => 'simple'
                                ]
                            ]
                        ],
                        'expansion' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => [
                                    'type' => 'text',
                                    'analyzer' => 'blueprint_analyzer',
                                    'fields' => ['keyword' => ['type' => 'keyword']]
                                ],
                                'code' => [
                                    'type' => 'text',
                                    'analyzer' => 'expansion_code_analyzer',
                                    'fields' => [
                                        'keyword' => ['type' => 'keyword'],
                                        'normalized' => [
                                            'type' => 'text',
                                            'analyzer' => 'expansion_code_analyzer'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'category' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'keyword']
                            ]
                        ],
                        'image_url' => ['type' => 'keyword', 'index' => false],
                        'latest_price' => [
                            'properties' => [
                                'price_cents' => ['type' => 'integer'],
                                'currency' => ['type' => 'keyword'],
                                'language' => ['type' => 'keyword'],
                                'condition' => ['type' => 'keyword'],
                                'is_foil' => ['type' => 'boolean'],
                                'fetched_at' => ['type' => 'date']
                            ]
                        ],
                        'properties' => ['type' => 'object', 'enabled' => false],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date']
                    ]
                ]
            ]
        ];

        try {
            $response = $this->client->indices()->create($params);
            $result = $response->asArray();
            return isset($result['acknowledged']) && $result['acknowledged'];
        } catch (\Exception $e) {
            \Log::error('Failed to create Elasticsearch index', [
                'error' => $e->getMessage(),
                'index' => $this->indexName
            ]);
            return false;
        }
    }

    /**
     * Delete the index
     */
    public function deleteIndex(): bool
    {
        try {
            if ($this->indexExists()) {
                $response = $this->client->indices()->delete(['index' => $this->indexName]);
                $result = $response->asArray();
                return isset($result['acknowledged']) && $result['acknowledged'];
            }
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to delete Elasticsearch index', [
                'error' => $e->getMessage(),
                'index' => $this->indexName
            ]);
            return false;
        }
    }

    /**
     * Check if index exists
     */
    public function indexExists(): bool
    {
        try {
            return $this->client->indices()->exists(['index' => $this->indexName])->asBool();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Index a single blueprint document
     */
    public function indexBlueprint(array $blueprint): bool
    {
        $params = [
            'index' => $this->indexName,
            'id' => $blueprint['id'],
            'body' => $blueprint
        ];

        try {
            $response = $this->client->index($params);
            $result = $response->asArray();
            return isset($result['result']) && in_array($result['result'], ['created', 'updated']);
        } catch (\Exception $e) {
            \Log::error('Failed to index blueprint', [
                'error' => $e->getMessage(),
                'blueprint_id' => $blueprint['id'] ?? 'unknown'
            ]);
            return false;
        }
    }

    /**
     * Bulk index blueprints
     */
    public function bulkIndexBlueprints(array $blueprints): bool
    {
        if (empty($blueprints)) {
            return true;
        }

        $params = ['body' => []];

        foreach ($blueprints as $blueprint) {
            $params['body'][] = [
                'index' => [
                    '_index' => $this->indexName,
                    '_id' => $blueprint['id']
                ]
            ];
            $params['body'][] = $blueprint;
        }

        try {
            $response = $this->client->bulk($params);
            $result = $response->asArray();

            // Enhanced error logging
            if (isset($result['errors']) && $result['errors']) {
                \Log::error('Bulk indexing has errors', [
                    'items_with_errors' => collect($result['items'])->filter(function ($item) {
                        return isset($item['index']['error']);
                    })->map(function ($item) {
                        return [
                            'id' => $item['index']['_id'] ?? 'unknown',
                            'error' => $item['index']['error'] ?? 'unknown error'
                        ];
                    })->take(5)->toArray() // Show first 5 errors
                ]);
                return false;
            }

            return !isset($result['errors']) || !$result['errors'];
        } catch (\Exception $e) {
            \Log::error('Failed to bulk index blueprints', [
                'error' => $e->getMessage(),
                'count' => count($blueprints),
                'first_blueprint_id' => $blueprints[0]['id'] ?? 'unknown'
            ]);
            return false;
        }
    }

    /**
     * Search blueprints
     */
    public function searchBlueprints(
        string $query,
        int $from = 0,
        int $size = 30,
        ?string $language = null,
        ?int $expansionId = null,
        ?int $categoryId = null
    ): array {
        $searchQuery = trim($query);
        $expansionCode = null;

        // Extract expansion code from the query string
        if (preg_match('/\\b([A-Z]{2,3}-?[0-9]{1,2})\\b$/i', $searchQuery, $matches)) {
            $expansionCode = strtoupper(str_replace(['-', '.'], '', $matches[1]));
            $searchQuery = trim(preg_replace('/\\b' . preg_quote($matches[0], '/') . '\\b$/i', '', $searchQuery));
        }

        // Build the main query part
        $mustClauses = [];
        if (!empty($searchQuery)) {
            $mustClauses[] = [
                'multi_match' => [
                    'query' => $searchQuery,
                    'fields' => ['name^3', 'expansion.name'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // Build the filter part
        $filterClauses = [];
        if ($language) {
            $filterClauses[] = ['term' => ['latest_price.language' => $language]];
        }

        if ($expansionId) {
            $filterClauses[] = ['term' => ['expansion.id' => $expansionId]];
        } elseif ($expansionCode) {
            // This part is tricky because the field is analyzed.
            // Using a match query on the normalized field.
            $filterClauses[] = ['match' => ['expansion.code.normalized' => $expansionCode]];
        }

        if ($categoryId) {
            $filterClauses[] = ['term' => ['category.id' => $categoryId]];
        }

        $params = [
            'index' => $this->indexName,
            'body' => [
                'from' => $from,
                'size' => $size,
                'query' => [
                    'bool' => [
                        'must' => !empty($mustClauses) ? $mustClauses : [['match_all' => new \stdClass()]],
                        'filter' => $filterClauses,
                    ],
                ],
            ],
        ];

        // If there's no text query, sort by name. Otherwise, rely on relevance score.
        if (empty($searchQuery)) {
            $params['body']['sort'] = [
                ['name.keyword' => 'asc']
            ];
        }

        try {
            $response = $this->client->search($params)->asArray();
            return $this->formatSearchResponse($response);
        } catch (\Exception $e) {
            \Log::error('Elasticsearch search failed', [
                'error' => $e->getMessage(),
                'query' => $params
            ]);
            throw $e; // Re-throw to be handled by the controller
        }
    }

    /**
     * Format search response for API consumption
     */
    private function formatSearchResponse(array $response): array
    {
        $hits = [];

        if (isset($response['hits']['hits'])) {
            foreach ($response['hits']['hits'] as $hit) {
                $source = $hit['_source'];
                $source['_score'] = $hit['_score'] ?? 0;

                // Add highlighting if available
                if (isset($hit['highlight'])) {
                    $source['_highlight'] = $hit['highlight'];
                }

                $hits[] = $source;
            }
        }

        return [
            'hits' => $hits,
            'total' => $response['hits']['total']['value'] ?? 0,
            'max_score' => $response['hits']['max_score'] ?? 0
        ];
    }

    /**
     * Delete a blueprint from index
     */
    public function deleteBlueprint(int $blueprintId): bool
    {
        $params = [
            'index' => $this->indexName,
            'id' => $blueprintId
        ];

        try {
            $response = $this->client->delete($params);
            $result = $response->asArray();
            return isset($result['result']) && $result['result'] === 'deleted';
        } catch (\Exception $e) {
            \Log::error('Failed to delete blueprint from index', [
                'error' => $e->getMessage(),
                'blueprint_id' => $blueprintId
            ]);
            return false;
        }
    }
}
