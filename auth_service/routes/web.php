<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Catalog Filament panel — JWT + admin role, then HTML proxy with identity headers
Route::middleware(['ensure.token', 'auth:api', 'role:admin'])->group(function () {
    Route::any('/admin-catalog/{path?}', [GatewayController::class, 'catalogAdminPanel'])
        ->where('path', '.*');
});
