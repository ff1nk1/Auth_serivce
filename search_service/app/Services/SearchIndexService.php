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

    public function ensureIndices(): void
    {
        if ($this->indicesEnsured) {
            return;
        }

        $products = $this->productsIndex();
        if (! $this->client->indices()->exists(['index' => $products])) {
            $this->client->indices()->create([
                'index' => $products,
                'body' => [
                    'mappings' => [
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'store_id' => ['type' => 'integer'],
                            'category_id' => ['type' => 'integer'],
                            'name' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
                            'description' => ['type' => 'text'],
                            'image_url' => ['type' => 'keyword', 'index' => false],
                            'price' => ['type' => 'float'],
                            'category_name' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
                            'store_name' => ['type' => 'text', 'fields' => ['keyword' => ['type' => 'keyword']]],
                            'quantity' => ['type' => 'integer'],
                            'in_stock' => ['type' => 'boolean'],
                        ],
                    ],
                ],
            ]);
        }

        $categories = $this->categoriesIndex();
        if (! $this->client->indices()->exists(['index' => $categories])) {
            $this->client->indices()->create([
                'index' => $categories,
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

        $this->indicesEnsured = true;
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
