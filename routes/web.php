<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;

Route::get('/', function () {
    return view('welcome');
});

// 1. Маршруты для гостей (доступны ТОЛЬКО когда пользователь НЕ залогинен)
Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});


Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
// 2. Защищенные маршруты (доступны ТОЛЬКО после входа)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});