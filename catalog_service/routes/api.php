<?php

use App\Http\Controllers\AdminCatalogController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

// Open API — no auth middleware (admin frontend / storefront)

Route::post('/get-upload-url', [UploadController::class, 'getUploadUrl']);

Route::prefix('catalog')->group(function () {
    Route::get('/categories', [CatalogController::class, 'show_categories']);
    Route::get('/categories/{cat_slug}/products', [CatalogController::class, 'show_products_by_cat_id']);
    Route::get('/stores/{store_id}/products', [CatalogController::class, 'show_products_by_store_id']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
});

Route::prefix('admin')->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::apiResource('categories', AdminCatalogController::class);
});
