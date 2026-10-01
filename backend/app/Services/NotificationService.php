<?php

namespace App\Services;

use App\Http\Requests\NotificationsRequest;
use App\Models\EmailLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Log;

class NotificationService
{

    public const MAX_RETRIES = 3;         // Максимальное кол-во попыток
    public const INITIAL_BACKOFF_SEC = 1;  // Базовая задержка в секундах
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

    public function send_email($notification)
    {
        $email = $notification->email;
        $name = $notification->name;
        try{
            Mail::to($email)->send(new WelcomeMail([
                "email"=> $email,
                "name"=>$name,
                ]));

            $notification->update([
            'status'        => 'sent',
            'error_message' => null,
            'time'          => time(),
            ]);
            return true;

        }catch (\Exception $e)
        {
            $lastError = $e->getMessage();
            $notification->update([
                'status'        => 'failed',
                'error_message' => 'Ошибка отправки из админки: ' . $e->getMessage(),
        ]);
            return false;
        }
        
    }

    /**
     * Отправка Welcome-письма с логикой повторных попыток и записью в MongoDB
     */
    public function sendWelcomeMailWithRetry(array $body): array
    {
        $emailAddress = $body['email'];

        // 1. Создаем начальную запись в MongoDB
        $emailLog = EmailLog::create([
            'name'         => $body['name'] ?? null,
            'email'        => $emailAddress,
            'status'       => 'pending',
            'retries'      => 0,
            'moved_to_dlq' => false,
            'payload'      => $body,
        ]);

        $attempt = 0;
        $isSent = false;
        $lastError = '';

        // 2. Цикл ретраев с экспоненциальной задержкой
        while ($attempt < self::MAX_RETRIES && !$isSent) {
            $attempt++;

            try {
                Mail::to($emailAddress)->send(new WelcomeMail($body));

                // Успешная отправка
                $emailLog->update([
                    'status'  => 'sent',
                    'retries' => $attempt,
                    'moved_to_dlq'  => false, // Сбрасываем флаг DLQ
                    'error_message' => null,  // Очищаем прошлую ошибку
                    'time'    => time(),
                ]);

                $isSent = true;
                Log::info("[KAFKA УСПЕХ] Письмо отправлено на {$emailAddress} с попытки {$attempt}");

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $emailLog->update(['retries' => $attempt]);

                if ($attempt < self::MAX_RETRIES) {
                    $sleepTime = self::INITIAL_BACKOFF_SEC * (2 ** ($attempt - 1));

                    Log::warning("SMTP Сбой (Попытка {$attempt}/" . self::MAX_RETRIES . "). Ждем {$sleepTime} сек...", [
                        'email' => $emailAddress,
                        'error' => $lastError,
                    ]);

                    $this->sleepInTest($sleepTime);
                }
            }
        }

        return [
            'isSent'    => $isSent,
            'emailLog'  => $emailLog,
            'lastError' => $lastError,
        ];
    }

    /**
     * Вынесено в отдельный метод для переопределения в юнит-тестах (чтобы не ждать реальные секунды)
     */
    protected function sleepInTest(int $seconds): void
    {
        sleep($seconds);
    }

}