<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\ProductService;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    // GET /api/admin/products
    public function index(): JsonResponse
    {
        // Подгружаем связанные сущности для админской таблицы
        return response()->json(Product::with(['category', 'store'])->paginate(20));
    }

    // POST /api/admin/products
    public function store(StoreProductRequest $request): JsonResponse
    {
        // Сервис создаст товар и отправит событие в Kafka
        $product = $this->productService->createProduct($request->validated());

        return response()->json(['message' => 'Товар добавлен', 'product' => $product], 201);
    }

    // GET /api/admin/products/{product}
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'product' => $product->load(['category', 'store'])
        ]);
    }

    // PUT/PATCH /api/admin/products/{product}
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {

        // Сервис обновит товар и отправит событие в Kafka
        $product = $this->productService->updateProduct($product, $request->validated());

        return response()->json(['message' => 'Товар обновлен', 'product' => $product]);
    }

    // DELETE /api/admin/products/{product}
    public function destroy(Product $product): JsonResponse
    {
        // Сервис удалит товар и отправит старые данные в Kafka
        $this->productService->deleteProduct($product);
        
        return response()->json(['message' => 'Товар удален']);
    }
}