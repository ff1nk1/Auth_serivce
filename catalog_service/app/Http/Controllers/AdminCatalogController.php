<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\CatalogService;

class AdminCatalogController extends Controller
{
    public function __construct(
        private CatalogService $catalogService
    ) {}

    // GET /api/admin/categories
    public function index(): JsonResponse
    {
        // В админке отдаем без кэша, с пагинацией и связью с родителем
        return response()->json(Category::with('parent')->paginate(20));
    }

    // POST /api/admin/categories
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        // $request->validated() вернет только те данные, которые прошли проверку в StoreCategoryRequest
        $category = $this->catalogService->createCategory($request->validated());

        return response()->json(['message' => 'Категория создана', 'category' => $category], 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->catalogService->updateCategory($category, $request->validated());

        return response()->json(['message' => 'Категория обновлена', 'category' => $category]);
    }
    // GET /api/admin/categories/{category}
    public function show(Category $category): JsonResponse
    {
        return response()->json(['category' => $category->load('parent', 'children')]);
    }

    // DELETE /api/admin/categories/{category}
    public function destroy(Category $category): JsonResponse
    {
        $this->catalogService->deleteCategory($category);
        
        return response()->json(['message' => 'Категория удалена']);
    }
}