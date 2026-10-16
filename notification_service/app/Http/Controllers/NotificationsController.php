<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationsRequest;
use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationsController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function get_notifications(
        NotificationsRequest $request,
    ): JsonResponse {
        $notifications = $this->notificationService->getFilteredNotifications($request);

        return response()->json($notifications);
    }

    public function get_notification_by_id(string $id): JsonResponse
    {
        $notification = EmailLog::findOrFail($id);

        return response()->json($notification);
    }

    public function resend(string $id): JsonResponse
    {
        $notification = EmailLog::findOrFail($id);

        if ($notification->status !== 'failed') {
            return response()->json([
                'message' => 'Повторная отправка доступна только для записей со статусом failed.',
            ], 422);
        }

        if ($this->notificationService->resolveEventTypeForResend($notification) === null) {
            return response()->json([
                'message' => 'У записи нет event_type, переотправка невозможна.',
            ], 422);
        }

        $isSent = $this->notificationService->send_email($notification);

        if ($isSent) {
            return response()->json([
                'message' => 'Уведомление успешно отправлено!',
                'data' => $notification->fresh(), // Возвращаем обновленный документ
            ], 200);
        }

        return response()->json([
            'message' => $notification->error_message ?? 'Не удалось отправить уведомление',
        ], 400);
    }
}
