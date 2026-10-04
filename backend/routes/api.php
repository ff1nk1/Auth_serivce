<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationsController;


Route::get('/', function () {
    return view('welcome');
});

// 1. Маршруты для гостей (доступны ТОЛЬКО когда пользователь НЕ залогинен)
Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/registration', [AuthController::class, 'registration']);

});
Route::post('/refresh', [AuthController::class, 'refresh']);

Route::middleware([
    'cookie.token',
    'auth:api',
    'role:admin',
])->group(function () {
    Route::get('/users', [AdminController::class, 'index']);
    Route::get('/roles', [AdminController::class, 'getRoles']);
    Route::patch('/users/{id}/role', [AdminController::class, 'changeRole']);
});

// 2. Защищенные маршруты (доступны ТОЛЬКО после входа)
Route::middleware(
    [
        'cookie.token',
        'auth:api',
    ])->group(function () {
        Route::get('/user', [AuthController::class, 'get_user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::patch('/profile', [AuthController::class, 'editData']);
        Route::patch('/profile/password', [AuthController::class, 'change_password']);
    });


Route::middleware(
    [   
        'cookie.token',
        'auth:api',
        'role:admin,analyst'

    ])->group(function () 
    {
        Route::get('/notifications',[NotificationsController::class,'get_notifications']);
        Route::get('/notifications/{id}',[NotificationsController::class,'get_notification_by_id']);
        Route::post('/notifications/{id}/resend',[NotificationsController::class,'resend']);

    });

Route::middleware(
    [
        'cookie.token',
        'auth:api',
    ])->group(function (){
        Route::get('/categories', [CatalogController::class,'show_categories']);
        Route::get('/products/{cat_slug}',[CatalogController::class,'show_products_by_cat_id']);
        Route::get('/products/stores/{store_id}',[CatalogController::class,'show_products_by_store_id']);

    }
    );
