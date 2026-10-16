<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use Throwable;

class SearchIndexService
{
    private bool $indicesEnsured = false;

    public function __construct(
        private readonly Client $client,
    ) {}

    /**
     * Hot path: create missing concrete indices only. Never putMapping / never touch aliases.
     */
    public function ensureIndices(): void
    {
        if ($this->indicesEnsured) {
            return;
        }

        $products = $this->productsIndex();
        if (! $this->nameExists($products)) {
            $this->createProductsIndex($products);
        }

        $categories = $this->categoriesIndex();
        if (! $this->nameExists($categories)) {
            $this->createCategoriesIndex($categories);
        }

        $this->indicesEnsured = true;
    }

    /**
     * Drop products/categories (including alias targets) and recreate with full settings + mappings.
     */
    public function recreateIndices(): void
    {
        $this->deleteIndexOrAlias($this->productsIndex());
        $this->deleteIndexOrAlias($this->categoriesIndex());

        // Also clean leftover concrete names from synonym reindexes
        foreach (['products_new', 'products_v1', 'categories_new'] as $legacy) {
            if ($this->nameExists($legacy)) {
                $this->deleteIndexOrAlias($legacy);
            }
        }

        $this->createProductsIndex($this->productsIndex());
        $this->createCategoriesIndex($this->categoriesIndex());

        $this->indicesEnsured = true;
    }

    private function nameExists(string $name): bool
    {
        try {
            return (bool) $this->client->indices()->exists(['index' => $name]);
        } catch (Throwable) {
            return false;
        }
    }

    private function deleteIndexOrAlias(string $name): void
    {
        if (! $this->nameExists($name)) {
            return;
        }

        try {
            $aliasInfo = $this->client->indices()->getAlias(['name' => $name]);
            if (is_array($aliasInfo) && $aliasInfo !== []) {
                foreach (array_keys($aliasInfo) as $concreteIndex) {
                    $this->client->indices()->delete(['index' => (string) $concreteIndex]);
                }

                return;
            }
        } catch (Throwable) {
            // not an alias — fall through to delete as index
        }

        try {
            $this->client->indices()->delete(['index' => $name]);
        } catch (Throwable $e) {
            if (! $this->isNotFound($e)) {
                throw $e;
            }
        }
    }

    private function createProductsIndex(string $name): void
    {
        $this->client->indices()->create([
            'index' => $name,
            'body' => [
                'settings' => $this->productIndexSettings(),
                'mappings' => [
                    'properties' => $this->productMappings(),
                ],
            ],
        ]);
    }

    private function createCategoriesIndex(string $name): void
    {
        $this->client->indices()->create([
            'index' => $name,
            'body' => [
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'parent_id' => ['type' => 'integer'],
                        'name' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
                        'slug' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productIndexSettings(): array
    {
        $synonyms = config('opensearch.synonyms', []);
        if (! is_array($synonyms) || $synonyms === []) {
            $synonyms = [
                'ноутбук, ноутбуки, laptop, notebook',
                'телефон, смартфон, phone, smartphone',
            ];
        }

        return [
            'analysis' => [
                'filter' => [
                    'product_synonyms' => [
                        'type' => 'synonym_graph',
                        'lenient' => true,
                        'synonyms' => array_values($synonyms),
                    ],
                ],
                'analyzer' => [
                    'product_index' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase'],
                    ],
                    'product_search' => [
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'product_synonyms'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productMappings(): array
    {
        return [
            'id' => ['type' => 'integer'],
            'store_id' => ['type' => 'integer'],
            'category_id' => ['type' => 'integer'],
            'name' => [
                'type' => 'text',
                'analyzer' => 'product_index',
                'search_analyzer' => 'product_search',
                'fields' => [
                    'keyword' => ['type' => 'keyword'],
                    'autocomplete' => ['type' => 'search_as_you_type'],
                ],
            ],
            'description' => [
                'type' => 'text',
                'analyzer' => 'product_index',
                'search_analyzer' => 'product_search',
            ],
            'image_url' => ['type' => 'keyword', 'index' => false],
            'price' => ['type' => 'float'],
            'category_name' => [
                'type' => 'text',
                'fields' => ['keyword' => ['type' => 'keyword']],
            ],
            'store_name' => [
                'type' => 'text',
                'fields' => ['keyword' => ['type' => 'keyword']],
            ],
            'quantity' => ['type' => 'integer'],
            'in_stock' => ['type' => 'boolean'],
            'attributes' => [
                'type' => 'nested',
                'properties' => [
                    'name' => ['type' => 'keyword'],
                    'value' => ['type' => 'keyword'],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function indexProduct(array $data, bool $preserveStock = true): void
    {
        $this->ensureIndices();

        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $document = [
            'id' => $id,
            'store_id' => isset($data['store_id']) ? (int) $data['store_id'] : null,
            'category_id' => isset($data['category_id']) ? (int) $data['category_id'] : (
                isset($data['category']['id']) ? (int) $data['category']['id'] : null
            ),
            'name' => (string) ($data['name'] ?? ''),
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'price' => isset($data['price']) ? (float) $data['price'] : null,
            'category_name' => $data['category']['name'] ?? ($data['category_name'] ?? null),
            'store_name' => $data['store']['name'] ?? ($data['store_name'] ?? null),
            'attributes' => $this->normalizeAttributes($data['attributes'] ?? []),
        ];

        if ($preserveStock && $this->productExists($id)) {
            $existing = $this->client->get([
                'index' => $this->productsIndex(),
                'id' => (string) $id,
            ]);
            $source = $existing['_source'] ?? [];
            $document['quantity'] = (int) ($source['quantity'] ?? 0);
            $document['in_stock'] = (bool) ($source['in_stock'] ?? false);
        } else {
            $document['quantity'] = (int) ($data['quantity'] ?? 0);
            $document['in_stock'] = (bool) ($data['in_stock'] ?? ($document['quantity'] > 0));
        }

        $this->client->index([
            'index' => $this->productsIndex(),
            'id' => (string) $id,
            'body' => $document,
            'refresh' => true,
        ]);
    }

    /**
     * @return list<array{name: string, value: string}>
     */
    private function normalizeAttributes(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $normalized = [];
        foreach ($attributes as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        return $normalized;
    }

    public function deleteProduct(int $id): void
    {
        $this->ensureIndices();

        try {
            $this->client->delete([
                'index' => $this->productsIndex(),
                'id' => (string) $id,
                'refresh' => true,
            ]);
        } catch (Missing404Exception) {
            // already gone
        } catch (Throwable $e) {
            if ($this->isNotFound($e)) {
                return;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function indexCategory(array $data): void
    {
        $this->ensureIndices();

        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            return;
        }

        $this->client->index([
            'index' => $this->categoriesIndex(),
            'id' => (string) $id,
            'body' => [
                'id' => $id,
                'parent_id' => isset($data['parent_id']) ? (int) $data['parent_id'] : null,
                'name' => (string) ($data['name'] ?? ''),
                'slug' => (string) ($data['slug'] ?? ''),
            ],
            'refresh' => true,
        ]);
    }

    public function deleteCategory(int $id): void
    {
        $this->ensureIndices();

        try {
            $this->client->delete([
                'index' => $this->categoriesIndex(),
                'id' => (string) $id,
                'refresh' => true,
            ]);
        } catch (Missing404Exception) {
            // already gone
        } catch (Throwable $e) {
            if ($this->isNotFound($e)) {
                return;
            }

            throw $e;
        }
    }

    public function syncCategoryNameOnProducts(int $categoryId, string $categoryName): void
    {
        $this->ensureIndices();

        $this->client->updateByQuery([
            'index' => $this->productsIndex(),
            'refresh' => true,
            'conflicts' => 'proceed',
            'body' => [
                'query' => [
                    'term' => ['category_id' => $categoryId],
                ],
                'script' => [
                    'source' => 'ctx._source.category_name = params.category_name',
                    'lang' => 'painless',
                    'params' => [
                        'category_name' => $categoryName,
                    ],
                ],
            ],
        ]);
    }

    public function updateProductStock(int $productId, int $quantity): void
    {
        $this->ensureIndices();

        if (! $this->productExists($productId)) {
            Log::warning('OpenSearch: stock.changed for missing product document', [
                'product_id' => $productId,
                'quantity' => $quantity,
            ]);

            return;
        }

        $this->client->update([
            'index' => $this->productsIndex(),
            'id' => (string) $productId,
            'refresh' => true,
            'body' => [
                'doc' => [
                    'quantity' => $quantity,
                    'in_stock' => $quantity > 0,
                ],
            ],
        ]);
    }

    private function productExists(int $id): bool
    {
        try {
            return (bool) $this->client->exists([
                'index' => $this->productsIndex(),
                'id' => (string) $id,
            ]);
        } catch (Throwable) {
            return false;
        }
    }

    private function productsIndex(): string
    {
        return (string) config('opensearch.indices.products', 'products');
    }

    private function categoriesIndex(): string
    {
        return (string) config('opensearch.indices.categories', 'categories');
    }

    private function isNotFound(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'not_found') || str_contains($message, '404');
    }
}
