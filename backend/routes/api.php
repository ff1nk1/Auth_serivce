<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

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
    'jwt.refresh',
    'auth:api',
    'role:admin',
])->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});

// 2. Защищенные маршруты (доступны ТОЛЬКО после входа)
Route::middleware(['cookie.token', 'auth:api'])->group(function () {
    Route::get('/user', [AuthController::class,'get_user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/profile',[AuthController::class,'editData']);
    Route::patch('/profile/password',[AuthController::class,'change_password']);
});
