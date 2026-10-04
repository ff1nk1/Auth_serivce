<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function show_categories()
    {
        return Cache::tags(['categories'])->remember('all_categories', 3600, function () {
            // ВАЖНО: Добавляем ->toArray(), чтобы кэшировать простые массивы, а не тяжелые объекты моделей
            return Category::all()->toArray();
        });
    }

    public function show_products_by_cat_id(Request $request, $cat_slug)
    {
        // 1. Больше не кэшируем саму модель. Просто достаем из БД (это очень быстро!)
        $category = Category::where('slug', $cat_slug)->firstOrFail();

        // 2. Метод getTreeIds() вернет массив [ID, ID, ID]. Вот он кэшируется безопасно!
        $allIds = $category->getTreeIds();

        // 3. Загружаем товары
        $query = Product::whereIn('category_id', $allIds);
        $query = $this->applyFilters($query, $request);
        $products = $query->paginate(20);

        return response()->json([
            'category' => $category,
            'products' => $products
        ]);
    }

    public function show_products_by_store_id(Request $request, $store_id)
    {
        $query = Product::where('store_id', $store_id);
        $query = $this->applyFilters($query, $request);
        $products = $query->paginate(20);

        return response()->json([
            'products' => $products
        ]);
    }

    private function applyFilters($query, Request $request)
    {
        $query->when($request->query('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        $query->when($request->query('min_price'), function ($q, $minPrice) {
            $q->where('price', '>=', $minPrice);
        });

        $query->when($request->query('max_price'), function ($q, $maxPrice) {
            $q->where('price', '<=', $maxPrice);
        });

        $sort = $request->query('sort', 'new');
        if ($sort === 'price_asc') {
            $query->orderBy('price', 'asc');
        } elseif ($sort === 'price_desc') {
            $query->orderBy('price', 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query;
    }
}