<?php

use App\Http\Controllers\NotificationsController;
use Illuminate\Support\Facades\Route;

// Open management API for admin frontend — no auth middleware

Route::get('/notifications', [NotificationsController::class, 'get_notifications']);
Route::get('/notifications/{id}', [NotificationsController::class, 'get_notification_by_id']);
Route::post('/notifications/{id}/resend', [NotificationsController::class, 'resend']);
