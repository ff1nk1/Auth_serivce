<?php
use App\Http\Controllers\AuthController;

Route::middleware('guest')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/registration', [AuthController::class, 'registration']); // По REST лучше /register
});

Route::post('/refresh', [AuthController::class, 'refresh']);
