<?php

namespace App\Http\Controllers;

use App\Models\EmailLog; 
use Illuminate\Support\Carbon;
use App\Http\Requests\NotificationsRequest;
use App\Services\NotificationService;   
use Illuminate\Http\JsonResponse;

class NotificationsController extends Controller
{
    public function __construct(private NotificationService $notificationService){}
    public function get_notifications(
        NotificationsRequest $request, 
    ): JsonResponse {
        $notifications = $this->notificationService->getFilteredNotifications($request);

        return response()->json($notifications);
    }

    public function get_notification_by_id($id)
    {
        return EmailLog::findOrFail($id);
    }

    public function resend($id)
    {   
        $notification = $this->get_notification_by_id($id);
        $isSent = $this->notificationService->send_email($notification);

        if ($isSent) {
            return response()->json([
                'message' => 'Уведомление успешно отправлено!',
                'data'    => $notification->fresh(), // Возвращаем обновленный документ
            ], 200);
        }

        return response()->json([
            'message' => $notification->error_message ?? 'Не удалось отправить уведомление',
        ], 400);
    }

}
