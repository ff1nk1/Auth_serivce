<?php

namespace App\Http\Controllers;

use App\Models\EmailLog; 
use Illuminate\Support\Carbon;
use App\Http\Requests\NotificationsRequest;
use App\Services\NotificationService;   
use Illuminate\Http\JsonResponse;
class NotificationsController extends Controller
{
    public function get_notifications(
        NotificationsRequest $request, 
        NotificationService $notificationService
    ): JsonResponse {
        $notifications = $notificationService->getFilteredNotifications($request);

        return response()->json($notifications);
    }

    public function get_notification_by_id($id)
    {
        return EmailLog::findOrFail($id);
    }

}
