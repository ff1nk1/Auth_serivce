<?php

namespace App\Services;

use App\DTO\NotificationFilters;
use App\Models\EmailLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class EmailLogQueryService
{
    public function getFiltered(NotificationFilters $filters): LengthAwarePaginator
    {
        $query = EmailLog::query();

        $query->when($filters->status !== null && $filters->status !== '', function ($q) use ($filters) {
            $q->where('status', $filters->status);
        });

        $query->when($filters->email !== null && $filters->email !== '', function ($q) use ($filters) {
            $q->where('email', $filters->email);
        });

        $query->when($filters->eventType !== null && $filters->eventType !== '', function ($q) use ($filters) {
            $q->where('event_type', $filters->eventType);
        });

        $query->when($filters->dateFrom !== null && $filters->dateFrom !== '', function ($q) use ($filters) {
            $date = Carbon::parse($filters->dateFrom)->startOfDay();
            $q->where('created_at', '>=', $date);
        });

        $query->when($filters->dateTo !== null && $filters->dateTo !== '', function ($q) use ($filters) {
            $date = Carbon::parse($filters->dateTo)->endOfDay();
            $q->where('created_at', '<=', $date);
        });

        return $query->orderBy('created_at', 'desc')->paginate($filters->perPage);
    }
}
