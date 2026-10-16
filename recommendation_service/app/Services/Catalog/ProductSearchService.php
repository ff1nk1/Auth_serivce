<?php

namespace App\Services\Catalog;

use App\Models\ProductCatalog;
use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\DTOs\SearchFilters;

class ProductSearchService
{
    public function __construct(
        private readonly LlmClient $llm,
    ) {}

    /**
     * @return list<ProductCatalog>
     */
    public function search(SearchFilters $filters, int $limit = 5): array
    {
        $limit = max(1, min($limit, 20));
        $queryText = $filters->query ?: 'recommended products';
        $vector = $this->llm->embed($queryText, 'RETRIEVAL_QUERY');
        $literal = '['.implode(',', array_map(
            static fn (float $v): string => sprintf('%.8F', $v),
            $vector
        )).']';

        $builder = ProductCatalog::query()
            ->where('is_published', true)
            ->whereNotNull('embedding');

        if ($filters->storeId !== null) {
            $builder->where('store_id', $filters->storeId);
        }
        if ($filters->categoryId !== null) {
            $builder->where('category_id', $filters->categoryId);
        }
        if ($filters->inStock !== null) {
            $builder->where('in_stock', $filters->inStock);
        }
        if ($filters->priceMin !== null) {
            $builder->where('price', '>=', $filters->priceMin);
        }
        if ($filters->priceMax !== null) {
            $builder->where('price', '<=', $filters->priceMax);
        }

        foreach ($filters->attributes as $attr) {
            $builder->whereJsonContains('attributes', [
                'name' => $attr['name'],
                'value' => $attr['value'],
            ]);
        }

        $ids = $builder
            ->select('id')
            ->orderByRaw('embedding <=> ?::vector', [$literal])
            ->limit($limit)
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return [];
        }

        $products = ProductCatalog::query()->whereIn('id', $ids)->get()->keyBy('id');

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($products[$id])) {
                $ordered[] = $products[$id];
            }
        }

        return $ordered;
    }
}
