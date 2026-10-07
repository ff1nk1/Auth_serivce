<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationsController;
use App\Http\Controllers\AdminCatalogController;


Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/registration', [AuthController::class, 'registration']); // По REST лучше /register
});

Route::post('/refresh', [AuthController::class, 'refresh']);

// 2. Все маршруты, требующие авторизации
Route::middleware(['cookie.token', 'auth:api'])->group(function () {
    
    Route::post('/get-upload-url', [UploadController::class, 'getUploadUrl']);   

    // -- Профиль пользователя --
    Route::get('/user', [AuthController::class, 'get_user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/profile', [AuthController::class, 'editData']);
    Route::patch('/profile/password', [AuthController::class, 'change_password']);

    // -- Публичный каталог (Витрина) --
    Route::prefix('catalog')->group(function () {
        Route::get('/categories', [CatalogController::class, 'show_categories']);
        // По REST логичнее искать товары ВНУТРИ категории/магазина:
        Route::get('/categories/{cat_slug}/products', [CatalogController::class, 'show_products_by_cat_id']);
        Route::get('/stores/{store_id}/products', [CatalogController::class, 'show_products_by_store_id']);
        Route::get('/products/{id}',[ProductController::class,'show']);
    });

    // -- Зона Аналитиков и Админов --
    Route::middleware('role:admin,analyst')->group(function () {
        Route::get('/notifications', [NotificationsController::class, 'get_notifications']);
        Route::get('/notifications/{id}', [NotificationsController::class, 'get_notification_by_id']);
        Route::post('/notifications/{id}/resend', [NotificationsController::class, 'resend']);
    });

    // -- Зона ТОЛЬКО для Админов (Управление данными) --
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        
        // Управление пользователями
        Route::get('/users', [AdminController::class, 'index']);
        Route::get('/roles', [AdminController::class, 'getRoles']);
        Route::patch('/users/{id}/role', [AdminController::class, 'changeRole']);
        
        // Управление каталогом (CRUD)
        Route::apiResource('products', ProductController::class);
        Route::apiResource('categories', AdminCatalogController::class);
        //Route::apiResource('stores', StoreController::class);
        
    });
});