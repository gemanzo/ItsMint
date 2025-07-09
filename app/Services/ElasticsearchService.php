<?php

namespace App\Services;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;

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
                        'tokenizer' => [
                            'edge_ngram_tokenizer' => [
                                'type' => 'edge_ngram',
                                'min_gram' => 2,
                                'max_gram' => 20,
                                'token_chars' => ['letter', 'digit']
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
                                    'dmg,damaged',
                                ],
                            ],
                            'expansion_normalizer' => [
                                'type' => 'pattern_replace',
                                'pattern' => '[\\-\\.:\\s]',
                                'replacement' => '',
                            ],
                        ],
                        'analyzer' => [
                            'autocomplete_indexing' => [
                                'type' => 'custom',
                                'tokenizer' => 'edge_ngram_tokenizer',
                                'filter' => ['lowercase', 'asciifolding']
                            ],
                            'autocomplete_search' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding']
                            ],
                            'expansion_code_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'keyword',
                                'filter' => ['lowercase', 'expansion_normalizer']
                            ]
                        ]
                    ]
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete_indexing',
                            'search_analyzer' => 'autocomplete_search',
                            'fields' => [
                                'keyword' => ['type' => 'keyword']
                            ],
                        ],
                        'collector_number' => [
                            'type' => 'keyword',
                            'index' => true,
                        ],
                        'search_text' => [
                            'type' => 'text',
                            'analyzer' => 'autocomplete_indexing',
                            'search_analyzer' => 'autocomplete_search',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'expansion' => [
                            'properties' => [
                                'name' => [
                                    'type' => 'text',
                                    'analyzer' => 'autocomplete_indexing',
                                    'search_analyzer' => 'autocomplete_search',
                                    'fields' => ['keyword' => ['type' => 'keyword']],
                                ],
                                'code' => [
                                    'type' => 'text',
                                    'analyzer' => 'expansion_code_analyzer',
                                    'fields' => [
                                        'keyword' => ['type' => 'keyword'],
                                        'normalized' => [
                                            'type' => 'text',
                                            'analyzer' => 'expansion_code_analyzer',
                                        ],
                                    ],
                                ],
                                'normalized_name' => [
                                    'type' => 'text',
                                    'analyzer' => 'expansion_code_analyzer',
                                    'fields' => [
                                        'keyword' => ['type' => 'keyword'],
                                    ],
                                ],
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
                                'fetched_at' => ['type' => 'date'],
                            ],
                        ],
                        'properties' => ['type' => 'object', 'enabled' => false],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->client->indices()->create($params);
            $result = $response->asArray();

            return isset($result['acknowledged']) && $result['acknowledged'];
        } catch (\Exception $e) {
            \Log::error('Failed to create Elasticsearch index', [
                'error' => $e->getMessage(),
                'index' => $this->indexName,
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
                'index' => $this->indexName,
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
            'body' => $blueprint,
        ];

        try {
            $response = $this->client->index($params);
            $result = $response->asArray();

            return isset($result['result']) && in_array($result['result'], ['created', 'updated']);
        } catch (\Exception $e) {
            \Log::error('Failed to index blueprint', [
                'error' => $e->getMessage(),
                'blueprint_id' => $blueprint['id'] ?? 'unknown',
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
                    '_id' => $blueprint['id'],
                ],
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
                            'error' => $item['index']['error'] ?? 'unknown error',
                        ];
                    })->take(5)->toArray(), // Show first 5 errors
                ]);

                return false;
            }

            return ! isset($result['errors']) || ! $result['errors'];
        } catch (\Exception $e) {
            \Log::error('Failed to bulk index blueprints', [
                'error' => $e->getMessage(),
                'count' => count($blueprints),
                'first_blueprint_id' => $blueprints[0]['id'] ?? 'unknown',
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
        ?int $expansionId = null
    ): array {
        $mustClauses = [];
        $filterClauses = [];

        // Main search query using hybrid approach: wildcard for recall + multi_match for scoring
        if (! empty($query)) {
            $shouldClauses = [];
            $queryLower = strtolower($query);

            // 1. Exact matches get highest boost
            $shouldClauses[] = [
                'term' => [
                    'name.keyword' => [
                        'value' => $query,
                        'boost' => 5.0,
                    ]
                ]
            ];

            $shouldClauses[] = [
                'term' => [
                    'collector_number' => [
                        'value' => strtoupper($query),
                        'boost' => 4.5,
                    ]
                ]
            ];

            // 2. Smart collector number pattern matching
            if (preg_match('/^[a-z]{2,3}[\-]?\d{2,3}[\-]?\w*$/i', $query)) {
                // This looks like a collector number (op05-119, st01-011, etc.)
                $normalizedQuery = strtoupper(preg_replace('/[\-]/', '-', $query));

                $shouldClauses[] = [
                    'term' => [
                        'collector_number' => [
                            'value' => $normalizedQuery,
                            'boost' => 4.0,
                        ]
                    ]
                ];

                // Also try variations (with/without dash)
                $withoutDash = str_replace('-', '', $normalizedQuery);
                $withDash = preg_replace('/([a-z]+)(\d+)/i', '$1-$2', strtoupper($query));

                $shouldClauses[] = [
                    'wildcard' => [
                        'collector_number' => [
                            'value' => "*{$withoutDash}*",
                            'boost' => 3.5,
                        ]
                    ]
                ];

                $shouldClauses[] = [
                    'wildcard' => [
                        'collector_number' => [
                            'value' => "*{$withDash}*",
                            'boost' => 3.5,
                        ]
                    ]
                ];
            } else {
                // 3. Regular name matching for non-collector queries
                $shouldClauses[] = [
                    'wildcard' => [
                        'name' => [
                            'value' => "*{$queryLower}*",
                            'case_insensitive' => true,
                            'boost' => 2.0,
                        ]
                    ]
                ];

                $shouldClauses[] = [
                    'wildcard' => [
                        'search_text' => [
                            'value' => "*{$queryLower}*",
                            'case_insensitive' => true,
                            'boost' => 1.8,
                        ]
                    ]
                ];
            }

            // 4. Multi-match for better scoring
            $shouldClauses[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => [
                        'name^3',
                        'search_text^2.5',
                        'collector_number^2',
                        'expansion.name^2',
                        'expansion.code^2',
                    ],
                    'type' => 'best_fields',
                    'operator' => 'or',
                    'boost' => 1.5,
                ],
            ];

            // 5. Handle multi-term queries (like "luffy op05")
            $terms = explode(' ', trim($query));
            if (count($terms) > 1) {
                foreach ($terms as $term) {
                    $termLower = strtolower(trim($term));
                    if (strlen($termLower) >= 2) {
                        // Check if term looks like collector number
                        if (preg_match('/^[a-z]{2,3}[\-]?\d{2,3}[\-]?\w*$/i', $term)) {
                            $shouldClauses[] = [
                                'wildcard' => [
                                    'collector_number' => [
                                        'value' => "*{$termLower}*",
                                        'case_insensitive' => true,
                                        'boost' => 2.5,
                                    ]
                                ]
                            ];
                        } else {
                            $shouldClauses[] = [
                                'wildcard' => [
                                    'name' => [
                                        'value' => "*{$termLower}*",
                                        'case_insensitive' => true,
                                        'boost' => 1.5,
                                    ]
                                ]
                            ];
                        }
                    }
                }
            }

            $mustClauses[] = [
                'bool' => [
                    'should' => $shouldClauses,
                    'minimum_should_match' => 1,
                ]
            ];
        }

        // Filters for exact matches (language filter removed)
        if ($expansionId) {
            $filterClauses[] = ['term' => ['expansion.id' => $expansionId]];
        }

        // Determine minimum score based on query type
        $minScore = 2.0; // Default for name searches
        $queryLower = strtolower($query);

        // Higher threshold for collector number searches (more precision needed)
        if (preg_match('/^[a-z]{2,3}[\-]?\d{2,3}[\-]?\w*$/i', $query)) {
            $minScore = 25.0; // High precision for collector numbers
        }

        $params = [
            'index' => $this->indexName,
            'body' => [
                'from' => $from,
                'size' => $size,
                'min_score' => $minScore,
                'query' => [
                    'bool' => [
                        'must' => $mustClauses,
                        'filter' => $filterClauses,
                    ],
                ],
                'sort' => [
                    '_score' => ['order' => 'desc'], // Sort by relevance first
                    'name.keyword' => ['order' => 'asc'], // Then alphabetically
                ],
            ],
        ];

        try {
            $response = $this->client->search($params)->asArray();

            return $this->formatSearchResponse($response);
        } catch (\Exception $e) {
            \Log::error('Elasticsearch search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            // Return empty result set on failure
            return [
                'total' => 0,
                'max_score' => 0,
                'hits' => [],
            ];
        }
    }

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
            'max_score' => $response['hits']['max_score'] ?? 0,
        ];
    }

    /**
     * Delete a blueprint from index
     */
    public function deleteBlueprint(int $blueprintId): bool
    {
        $params = [
            'index' => $this->indexName,
            'id' => $blueprintId,
        ];

        try {
            $response = $this->client->delete($params);
            $result = $response->asArray();

            return isset($result['result']) && $result['result'] === 'deleted';
        } catch (\Exception $e) {
            \Log::error('Failed to delete blueprint from index', [
                'error' => $e->getMessage(),
                'blueprint_id' => $blueprintId,
            ]);

            return false;
        }
    }
}
