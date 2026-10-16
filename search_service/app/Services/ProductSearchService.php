<?php

namespace App\Services;

use App\Events\SearchPerformed;
use OpenSearch\Client;

class ProductSearchService
{
    /** @var list<array{key: string, from: float|null, to: float|null, label: string}> */
    private const PRICE_RANGES = [
        ['key' => '0-500', 'from' => 0.0, 'to' => 500.0, 'label' => '0–500'],
        ['key' => '500-1000', 'from' => 500.0, 'to' => 1000.0, 'label' => '500–1000'],
        ['key' => '1000-5000', 'from' => 1000.0, 'to' => 5000.0, 'label' => '1000–5000'],
        ['key' => '5000+', 'from' => 5000.0, 'to' => null, 'label' => '5000+'],
    ];

    public function __construct(
        private readonly Client $client,
        private readonly SearchIndexService $index,
    ) {}

    /**
     * @param  array{
     *     q: string|null,
     *     category_ids: list<int>,
     *     min_price: float|null,
     *     max_price: float|null,
     *     in_stock: bool|null,
     *     attributes: array<string, list<string>>,
     *     page: int,
     *     per_page: int,
     *     sort: string
     * }  $params
     * @param  bool  $skipAnalytics  When true, still publishes search.performed but marks skip_analytics for consumers.
     * @return array<string, mixed>
     */
    public function search(array $params, bool $skipAnalytics = false): array
    {
        $this->index->ensureIndices();

        $page = $params['page'];
        $perPage = $params['per_page'];
        $from = ($page - 1) * $perPage;

        $textQuery = $this->buildTextQuery($params['q']);
        $allFilters = $this->buildFilters($params);

        $body = [
            'from' => $from,
            'size' => $perPage,
            'track_total_hits' => true,
            'query' => [
                'bool' => [
                    'must' => [$textQuery],
                ],
            ],
            'sort' => $this->buildSort($params['sort'], $params['q']),
            // Facets are global (full catalog) so filter options stay stable across queries.
            'aggs' => $this->buildFacetAggregations(),
        ];

        if ($allFilters !== []) {
            $body['post_filter'] = [
                'bool' => [
                    'filter' => $allFilters,
                ],
            ];
        }

        $response = $this->client->search([
            'index' => (string) config('opensearch.indices.products', 'products'),
            'body' => $body,
        ]);

        $total = (int) ($response['hits']['total']['value'] ?? 0);
        $lastPage = max(1, (int) ceil($total / $perPage));

        $products = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $products[] = [
                'id' => $source['id'] ?? (int) ($hit['_id'] ?? 0),
                'name' => $source['name'] ?? '',
                'description' => $source['description'] ?? null,
                'price' => $source['price'] ?? null,
                'image_url' => $source['image_url'] ?? null,
                'category_id' => $source['category_id'] ?? null,
                'category_name' => $source['category_name'] ?? null,
                'store_id' => $source['store_id'] ?? null,
                'store_name' => $source['store_name'] ?? null,
                'in_stock' => (bool) ($source['in_stock'] ?? false),
                'quantity' => (int) ($source['quantity'] ?? 0),
                'attributes' => $source['attributes'] ?? [],
            ];
        }

        $result = [
            'products' => [
                'data' => $products,
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'facets' => $this->mapFacets($response['aggregations'] ?? []),
            'applied' => [
                'q' => $params['q'],
                'filters' => [
                    'category_ids' => $params['category_ids'],
                    'min_price' => $params['min_price'],
                    'max_price' => $params['max_price'],
                    'in_stock' => $params['in_stock'],
                    'attributes' => $params['attributes'],
                ],
                'sort' => $params['sort'],
            ],
        ];

        event(new SearchPerformed($params, $total, $skipAnalytics));

        return $result;
    }

    /**
     * Prefix-friendly product name suggestions (no facets / no search.performed).
     *
     * @return array{suggestions: list<array{id: int, name: string, price: float|null, image_url: string|null}>}
     */
    public function autocomplete(?string $q, int $limit = 8, string|int $fuzziness = 'AUTO'): array
    {
        $this->index->ensureIndices();

        $q = is_string($q) ? trim($q) : '';
        if (mb_strlen($q) < 2) {
            return ['suggestions' => []];
        }

        $limit = max(1, min(20, $limit));

        $response = $this->client->search([
            'index' => (string) config('opensearch.indices.products', 'products'),
            'body' => [
                'size' => $limit,
                '_source' => ['id', 'name', 'price', 'image_url'],
                'query' => [
                    'multi_match' => [
                        'query' => $q,
                        'type' => 'bool_prefix',
                        'fields' => [
                            'name.autocomplete',
                            'name.autocomplete._2gram',
                            'name.autocomplete._3gram',
                        ],
                        'fuzziness' => $fuzziness,
                    ],
                ],
            ],
        ]);

        $suggestions = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $suggestions[] = [
                'id' => (int) ($source['id'] ?? ($hit['_id'] ?? 0)),
                'name' => (string) ($source['name'] ?? ''),
                'price' => isset($source['price']) ? (float) $source['price'] : null,
                'image_url' => $source['image_url'] ?? null,
            ];
        }

        return ['suggestions' => $suggestions];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTextQuery(?string $q): array
    {
        if ($q === null || $q === '') {
            return ['match_all' => (object) []];
        }

        return [
            'multi_match' => [
                'query' => $q,
                'fields' => ['name^3', 'description', 'category_name', 'store_name'],
                'type' => 'best_fields',
                'operator' => 'and',
                'fuzziness' => 'AUTO',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  list<string>|null  $exclude
     * @return list<array<string, mixed>>
     */
    private function buildFilters(array $params, ?array $exclude = null): array
    {
        $exclude = $exclude ?? [];
        $filters = [];

        if (! in_array('category', $exclude, true) && $params['category_ids'] !== []) {
            $filters[] = ['terms' => ['category_id' => $params['category_ids']]];
        }

        if (! in_array('price', $exclude, true)) {
            $range = [];
            if ($params['min_price'] !== null) {
                $range['gte'] = $params['min_price'];
            }
            if ($params['max_price'] !== null) {
                $range['lte'] = $params['max_price'];
            }
            if ($range !== []) {
                $filters[] = ['range' => ['price' => $range]];
            }
        }

        if (! in_array('availability', $exclude, true) && $params['in_stock'] !== null) {
            $filters[] = ['term' => ['in_stock' => $params['in_stock']]];
        }

        if (! in_array('attributes', $exclude, true)) {
            foreach ($params['attributes'] as $name => $values) {
                foreach ($values as $value) {
                    $filters[] = [
                        'nested' => [
                            'path' => 'attributes',
                            'query' => [
                                'bool' => [
                                    'filter' => [
                                        ['term' => ['attributes.name' => $name]],
                                        ['term' => ['attributes.value' => $value]],
                                    ],
                                ],
                            ],
                        ],
                    ];
                }
            }
        }

        return $filters;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildSort(string $sort, ?string $q): array
    {
        return match ($sort) {
            'price_asc' => [['price' => ['order' => 'asc']], ['id' => ['order' => 'desc']]],
            'price_desc' => [['price' => ['order' => 'desc']], ['id' => ['order' => 'desc']]],
            'new' => [['id' => ['order' => 'desc']]],
            default => $q
                ? [['_score' => ['order' => 'desc']], ['id' => ['order' => 'desc']]]
                : [['id' => ['order' => 'desc']]],
        };
    }

    /**
     * Global catalog facets — independent of q / applied filters so the sidebar
     * always lists the same options for every search.
     *
     * @return array<string, mixed>
     */
    private function buildFacetAggregations(): array
    {
        $priceFilters = [];
        foreach (self::PRICE_RANGES as $range) {
            $rangeBody = [];
            if ($range['from'] !== null) {
                $rangeBody['gte'] = $range['from'];
            }
            if ($range['to'] !== null) {
                $rangeBody['lt'] = $range['to'];
            }
            $priceFilters[$range['key']] = ['range' => ['price' => $rangeBody]];
        }

        return [
            'catalog_facets' => [
                'global' => (object) [],
                'aggs' => [
                    'categories' => [
                        'terms' => ['field' => 'category_id', 'size' => 100],
                        'aggs' => [
                            'name' => [
                                'terms' => ['field' => 'category_name.keyword', 'size' => 1],
                            ],
                        ],
                    ],
                    'price_ranges' => [
                        'filters' => [
                            'filters' => $priceFilters,
                        ],
                    ],
                    'availability' => [
                        'terms' => ['field' => 'in_stock', 'size' => 2],
                    ],
                    'attributes' => [
                        'nested' => ['path' => 'attributes'],
                        'aggs' => [
                            'names' => [
                                'terms' => ['field' => 'attributes.name', 'size' => 50],
                                'aggs' => [
                                    'values' => [
                                        'terms' => ['field' => 'attributes.value', 'size' => 50],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $aggregations
     * @return array<string, mixed>
     */
    private function mapFacets(array $aggregations): array
    {
        $root = $aggregations['catalog_facets'] ?? $aggregations;

        $categories = [];
        foreach ($root['categories']['buckets'] ?? [] as $bucket) {
            $nameBucket = $bucket['name']['buckets'][0]['key'] ?? null;
            $categories[] = [
                'id' => (int) $bucket['key'],
                'name' => $nameBucket !== null ? (string) $nameBucket : (string) $bucket['key'],
                'count' => (int) ($bucket['doc_count'] ?? 0),
            ];
        }

        $priceRanges = [];
        $priceBuckets = $root['price_ranges']['buckets'] ?? [];
        foreach (self::PRICE_RANGES as $range) {
            $bucket = $priceBuckets[$range['key']] ?? null;
            $priceRanges[] = [
                'key' => $range['key'],
                'label' => $range['label'],
                'from' => $range['from'],
                'to' => $range['to'],
                'count' => (int) ($bucket['doc_count'] ?? 0),
            ];
        }

        $availability = [];
        foreach ($root['availability']['buckets'] ?? [] as $bucket) {
            $key = $bucket['key'];
            $availability[] = [
                'value' => is_bool($key) ? $key : ((int) $key === 1 || $key === 'true' || $key === true),
                'count' => (int) ($bucket['doc_count'] ?? 0),
            ];
        }

        $attributes = [];
        foreach ($root['attributes']['names']['buckets'] ?? [] as $nameBucket) {
            $name = (string) $nameBucket['key'];
            $values = [];
            foreach ($nameBucket['values']['buckets'] ?? [] as $valueBucket) {
                $values[] = [
                    'value' => (string) $valueBucket['key'],
                    'count' => (int) ($valueBucket['doc_count'] ?? 0),
                ];
            }
            $attributes[$name] = $values;
        }

        return [
            'categories' => $categories,
            'price_ranges' => $priceRanges,
            'availability' => $availability,
            'attributes' => $attributes,
        ];
    }
}
