<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Builder;

class CatalogController extends Controller
{
    public function __construct(
        private CatalogService $catalogService
    ) {}

    // GET /api/catalog/categories
    public function show_categories(): JsonResponse
    {
        return response()->json(
            $this->catalogService->getAllCategories()
        );
    }

    // GET /api/catalog/categories/{cat_slug}/products
    public function show_products_by_cat_id(Request $request, string $cat_slug): JsonResponse
    {
        $category = Category::where('slug', $cat_slug)->firstOrFail();
        
        // Получаем ID самой категории и всех её дочерних
        $allIds = $category->getTreeIds();

        $query = Product::with(['store', 'category'])
            ->whereIn('category_id', $allIds);

        $products = $this->applyFilters($query, $request)->paginate(20);

        return response()->json([
            'category' => $category,
            'products' => $products
        ]);
    }

    // GET /api/catalog/stores/{store_id}/products
    public function show_products_by_store_id(Request $request, int $store_id): JsonResponse
    {
        $query = Product::with(['store', 'category'])
            ->where('store_id', $store_id);

        $products = $this->applyFilters($query, $request)->paginate(20);

        return response()->json([
            'products' => $products
        ]);
    }

    /**
     * Приватный метод для фильтрации товаров на витрине
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        $query->when($request->input('search'), function (Builder $q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        $query->when($request->input('min_price'), function (Builder $q, $minPrice) {
            $q->where('price', '>=', $minPrice);
        });

        $query->when($request->input('max_price'), function (Builder $q, $maxPrice) {
            $q->where('price', '<=', $maxPrice);
        });

        $sort = $request->input('sort', 'new');
        
        match ($sort) {
            'price_asc'  => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            default      => $query->orderBy('created_at', 'desc'),
        };

        return $query;
    }
}