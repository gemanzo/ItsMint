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
    public function searchBlueprints(string $query, int $size = 20, int $from = 0): array
    {
        // Normalize the query for expansion code matching
        $normalizedQuery = strtolower(str_replace(['-', '.'], '', $query));

        // Try to detect if query contains both name and expansion code
        $terms = explode(' ', trim($query));
        $nameTerms = [];
        $expansionTerms = [];

        foreach ($terms as $term) {
            $normalizedTerm = strtolower(str_replace(['-', '.'], '', $term));
            // Improved pattern to match expansion codes: 2-3 letters + 1-3 digits (supports op01, op05, etc.)
            if (preg_match('/^[a-z]{2,3}[0-9]{1,3}$/i', $normalizedTerm)) {
                $expansionTerms[] = $term;
            } else {
                $nameTerms[] = $term;
            }
        }

        $hasNameAndExpansion = !empty($nameTerms) && !empty($expansionTerms);
        $nameQuery = implode(' ', $nameTerms);
        $expansionQuery = implode(' ', $expansionTerms);

        $params = [
            'index' => $this->indexName,
            'body' => [
                'size' => $size,
                'from' => $from,
                'query' => [
                    'bool' => [
                        'should' => [
                            // HIGHEST PRIORITY: Combined name + expansion match
                            ...(($hasNameAndExpansion) ? [
                                [
                                    'bool' => [
                                        'must' => [
                                            [
                                                'bool' => [
                                                    'should' => [
                                                        // Exact phrase match for name
                                                        [
                                                            'match_phrase' => [
                                                                'name' => [
                                                                    'query' => $nameQuery,
                                                                    'boost' => 2.0
                                                                ]
                                                            ]
                                                        ],
                                                        // Fuzzy match for name with higher fuzziness
                                                        [
                                                            'match' => [
                                                                'name' => [
                                                                    'query' => $nameQuery,
                                                                    'fuzziness' => '2',
                                                                    'boost' => 1.5
                                                                ]
                                                            ]
                                                        ],
                                                        // Partial match for name (contains)
                                                        [
                                                            'wildcard' => [
                                                                'name.keyword' => [
                                                                    'value' => "*{$nameQuery}*",
                                                                    'boost' => 1.0,
                                                                    'case_insensitive' => true
                                                                ]
                                                            ]
                                                        ]
                                                    ],
                                                    'minimum_should_match' => 1
                                                ]
                                            ],
                                            [
                                                'bool' => [
                                                    'should' => [
                                                        // Normalized expansion code match (removes dashes/dots)
                                                        [
                                                            'match' => [
                                                                'expansion.code.normalized' => [
                                                                    'query' => str_replace(['-', '.', '_'], '', $expansionQuery),
                                                                    'boost' => 2.0
                                                                ]
                                                            ]
                                                        ],
                                                        // Exact keyword match for expansion code
                                                        [
                                                            'term' => [
                                                                'expansion.code.keyword' => [
                                                                    'value' => strtolower($expansionQuery),
                                                                    'boost' => 1.8
                                                                ]
                                                            ]
                                                        ],
                                                        // Wildcard match for expansion codes
                                                        [
                                                            'wildcard' => [
                                                                'expansion.code.keyword' => [
                                                                    'value' => "*{$expansionQuery}*",
                                                                    'boost' => 1.5,
                                                                    'case_insensitive' => true
                                                                ]
                                                            ]
                                                        ]
                                                    ],
                                                    'minimum_should_match' => 1
                                                ]
                                            ]
                                        ],
                                        'boost' => 15.0  // Much higher boost for combined matches
                                    ]
                                ]
                            ] : []),

                            // SUPER HIGH PRIORITY: Exact word match in name (case insensitive)
                            [
                                'bool' => [
                                    'should' => [
                                        // Full name exact match (highest priority)
                                        [
                                            'match' => [
                                                'name.keyword' => [
                                                    'query' => $query,
                                                    'boost' => 20.0
                                                ]
                                            ]
                                        ],
                                        // Case insensitive exact match
                                        [
                                            'term' => [
                                                'name.keyword' => [
                                                    'value' => $query,
                                                    'boost' => 18.0,
                                                    'case_insensitive' => true
                                                ]
                                            ]
                                        ],
                                        // Word-level exact match within name
                                        [
                                            'match' => [
                                                'name' => [
                                                    'query' => $query,
                                                    'operator' => 'and',
                                                    'boost' => 15.0
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],

                            // HIGH PRIORITY: Exact phrase match on name
                            [
                                'match_phrase' => [
                                    'name' => [
                                        'query' => $query,
                                        'boost' => 12.0
                                    ]
                                ]
                            ],

                            // MEDIUM-HIGH PRIORITY: Contains exact word (wildcard with word boundaries)
                            [
                                'bool' => [
                                    'should' => [
                                        // Start of name match
                                        [
                                            'wildcard' => [
                                                'name.keyword' => [
                                                    'value' => "{$query}*",
                                                    'boost' => 8.0,
                                                    'case_insensitive' => true
                                                ]
                                            ]
                                        ],
                                        // Contains word (with spaces around)
                                        [
                                            'wildcard' => [
                                                'name.keyword' => [
                                                    'value' => "* {$query} *",
                                                    'boost' => 10.0,
                                                    'case_insensitive' => true
                                                ]
                                            ]
                                        ],
                                        // Contains as part of compound word
                                        [
                                            'wildcard' => [
                                                'name.keyword' => [
                                                    'value' => "*{$query}*",
                                                    'boost' => 6.0,
                                                    'case_insensitive' => true
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],

                            // MEDIUM PRIORITY: Fuzzy match on name
                            [
                                'match' => [
                                    'name' => [
                                        'query' => $query,
                                        'fuzziness' => '1',  // Reduced fuzziness to prevent wrong matches
                                        'boost' => 4.0
                                    ]
                                ]
                            ],

                            // Expansion name match
                            [
                                'match' => [
                                    'expansion.name' => [
                                        'query' => $query,
                                        'fuzziness' => 'AUTO',
                                        'boost' => 3.0
                                    ]
                                ]
                            ],

                            // Expansion code exact match (original format)
                            [
                                'term' => [
                                    'expansion.code.keyword' => [
                                        'value' => strtolower($query),
                                        'boost' => 5.0
                                    ]
                                ]
                            ],

                            // Expansion code normalized match (op01 = op-01)
                            [
                                'match' => [
                                    'expansion.code.normalized' => [
                                        'query' => $normalizedQuery,
                                        'boost' => 4.8
                                    ]
                                ]
                            ],

                            // Expansion code case-insensitive match
                            [
                                'match' => [
                                    'expansion.code' => [
                                        'query' => $query,
                                        'fuzziness' => '1',
                                        'boost' => 4.5
                                    ]
                                ]
                            ],

                            // Wildcard search for expansion codes (handles both op01 and op-01)
                            [
                                'bool' => [
                                    'should' => [
                                        [
                                            'wildcard' => [
                                                'expansion.code.keyword' => [
                                                    'value' => "*{$query}*",
                                                    'boost' => 4.0,
                                                    'case_insensitive' => true
                                                ]
                                            ]
                                        ],
                                        [
                                            'wildcard' => [
                                                'expansion.code.keyword' => [
                                                    'value' => "*{$normalizedQuery}*",
                                                    'boost' => 3.8,
                                                    'case_insensitive' => true
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ],

                            // LOW PRIORITY: Very fuzzy match (last resort)
                            [
                                'match' => [
                                    'name' => [
                                        'query' => $query,
                                        'fuzziness' => '2',
                                        'boost' => 1.0  // Very low boost for fuzzy matches
                                    ]
                                ]
                            ]
                        ],
                        'minimum_should_match' => 1
                    ]
                ],
                'highlight' => [
                    'fields' => [
                        'name' => ['fragment_size' => 100],
                        'expansion.name' => ['fragment_size' => 50],
                        'expansion.code' => ['fragment_size' => 20]
                    ]
                ],
                'sort' => [
                    '_score' => ['order' => 'desc'],
                    'name.keyword' => ['order' => 'asc']
                ]
            ]
        ];

        try {
            $response = $this->client->search($params);
            return $this->formatSearchResponse($response->asArray());
        } catch (\Exception $e) {
            \Log::error('Elasticsearch search failed', [
                'error' => $e->getMessage(),
                'query' => $query,
                'normalized_query' => $normalizedQuery,
                'has_name_and_expansion' => $hasNameAndExpansion,
                'name_query' => $nameQuery ?? null,
                'expansion_query' => $expansionQuery ?? null
            ]);
            return ['hits' => [], 'total' => 0];
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
