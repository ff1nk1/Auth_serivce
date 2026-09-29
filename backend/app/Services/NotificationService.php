<?php

namespace App\Services;

use App\Http\Requests\NotificationsRequest;
use App\Models\EmailLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class NotificationService
{
    /**
     * Формирование фильтрованного запроса и получение пагинированных данных
     */
    public function getFilteredNotifications(NotificationsRequest $request, int $perPage = 20): LengthAwarePaginator
    {
        $query = EmailLog::query();

        // 1. Фильтр по статусу
        $query->when($request->filled('status'), function ($q) use ($request) {
            $q->where('status', $request->status);
        });

        // 2. Фильтр по типу
        $query->when($request->filled('email'), function ($q) use ($request) {
            $q->where('email', $request->email);
        });


        // 4. Фильтр по дате "От"
        $query->when($request->filled('date_from'), function ($q) use ($request) {
            $date = Carbon::parse($request->date_from)->startOfDay();
            $q->where('created_at', '>=', $date);
        });

        // 5. Фильтр по дате "До"
        $query->when($request->filled('date_to'), function ($q) use ($request) {
            $date = Carbon::parse($request->date_to)->endOfDay();
            $q->where('created_at', '<=', $date);
        });

        // Возвращаем результат с пагинацией и сортировкой
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
}