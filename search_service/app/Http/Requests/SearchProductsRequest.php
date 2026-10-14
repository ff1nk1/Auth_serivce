<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable'],
            'category_id.*' => ['integer'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'in_stock' => ['nullable'],
            'attr' => ['nullable', 'array'],
            'attr.*' => ['nullable'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', 'string', Rule::in(['relevance', 'price_asc', 'price_desc', 'new'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     category_ids: list<int>,
     *     min_price: float|null,
     *     max_price: float|null,
     *     in_stock: bool|null,
     *     attributes: array<string, list<string>>,
     *     page: int,
     *     per_page: int,
     *     sort: string
     * }
     */
    public function searchParams(): array
    {
        $categoryIds = $this->input('category_id', []);
        if (! is_array($categoryIds)) {
            $categoryIds = $categoryIds === null || $categoryIds === '' ? [] : [$categoryIds];
        }
        $categoryIds = array_values(array_unique(array_map('intval', array_filter($categoryIds, fn ($v) => $v !== null && $v !== ''))));

        $inStockRaw = $this->input('in_stock');
        $inStock = null;
        if ($inStockRaw !== null && $inStockRaw !== '') {
            $inStock = filter_var($inStockRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($inStock === null && in_array((string) $inStockRaw, ['1', '0'], true)) {
                $inStock = (string) $inStockRaw === '1';
            }
        }

        $attributes = [];
        $attrInput = $this->input('attr', []);
        if (is_array($attrInput)) {
            foreach ($attrInput as $name => $value) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }
                $values = is_array($value) ? $value : [$value];
                $normalized = [];
                foreach ($values as $v) {
                    $v = trim((string) $v);
                    if ($v !== '') {
                        $normalized[] = $v;
                    }
                }
                if ($normalized !== []) {
                    $attributes[$name] = array_values(array_unique($normalized));
                }
            }
        }

        $q = $this->input('q');
        $q = is_string($q) ? trim($q) : null;
        if ($q === '') {
            $q = null;
        }

        $sort = (string) ($this->input('sort') ?: ($q ? 'relevance' : 'new'));

        return [
            'q' => $q,
            'category_ids' => $categoryIds,
            'min_price' => $this->filled('min_price') ? (float) $this->input('min_price') : null,
            'max_price' => $this->filled('max_price') ? (float) $this->input('max_price') : null,
            'in_stock' => $inStock,
            'attributes' => $attributes,
            'page' => max(1, (int) $this->input('page', 1)),
            'per_page' => min(50, max(1, (int) $this->input('per_page', 20))),
            'sort' => $sort,
        ];
    }

    /**
     * Loadtest / synthetic traffic: still hits Kafka, but analytic_service skips ClickHouse.
     */
    public function shouldSkipAnalytics(): bool
    {
        $raw = strtolower(trim((string) $this->header('X-Skip-Analytics', '')));

        return in_array($raw, ['1', 'true', 'yes'], true);
    }
}
