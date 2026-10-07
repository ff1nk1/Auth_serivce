<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/registration', [AuthController::class, 'registration']);
});

Route::post('/refresh', [AuthController::class, 'refresh']);

Route::middleware(['ensure.token', 'cookie.token', 'auth:api'])->group(function () {
    Route::get('/user', [AuthController::class, 'get_user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/profile', [AuthController::class, 'editData']);
    Route::patch('/profile/password', [AuthController::class, 'change_password']);

    // Storefront catalog — any authenticated role
    Route::any('/catalog/{path?}', [GatewayController::class, 'catalog'])
        ->where('path', '.*');

    // Notifications — admin + analyst
    Route::middleware('role:admin,analyst')->group(function () {
        Route::any('/notifications/{path?}', [GatewayController::class, 'notifications'])
            ->where('path', '.*');
    });

    // Admin zone
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'index']);
        Route::get('/roles', [AdminController::class, 'getRoles']);
        Route::patch('/users/{id}/role', [AdminController::class, 'changeRole']);

        Route::any('/{path?}', [GatewayController::class, 'catalogAdmin'])
            ->where('path', '.*');
    });

    Route::middleware('role:admin')->post('/get-upload-url', [GatewayController::class, 'uploadUrl']);
});
