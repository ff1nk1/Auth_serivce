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
    Route::get('/login', function () {abort(403);});
    Route::post('/refresh', [AuthController::class,'refresh']);
    Route::post('/registration',[AuthController::class,'registration']);
    Route::get('/registration',[AuthController::class,'registration_page']);

});


Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
// 2. Защищенные маршруты (доступны ТОЛЬКО после входа)
Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});