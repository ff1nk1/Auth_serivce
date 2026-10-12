<?php

namespace App\Services;

use App\Http\Requests\NotificationsRequest;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderPaidMail;
use App\Mail\WelcomeMail;
use App\Models\EmailLog;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

    /**
     * Admin resend: same retry/backoff path as primary Kafka delivery, on the existing EmailLog.
     */
    public function send_email(EmailLog $notification): bool
    {
        $eventType = $this->resolveEventTypeForResend($notification);
        if ($eventType === null) {
            throw new \InvalidArgumentException(
                'У записи нет event_type: переотправка невозможна (order.paid и order.confirmed неразличимы по payload).'
            );
        }

        $email = $notification->email;
        $payload = is_array($notification->payload) ? $notification->payload : [];
        $payload['email'] = $email;
        $payload['event_type'] = $eventType;
        if ($notification->name !== null && $notification->name !== '') {
            $payload['name'] = $notification->name;
        }

        $notification->update([
            'status' => 'pending',
            'error_message' => null,
            'moved_to_dlq' => false,
            'retries' => 0,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);

        $result = $this->attemptSendWithRetry(
            $notification,
            $email,
            fn () => $this->mailableForEventType($eventType, $payload),
        );

        if (! $result['isSent']) {
            $notification->update([
                'status' => 'failed',
                'error_message' => 'Ошибка отправки из админки: '.$result['lastError'],
            ]);
        }

        return $result['isSent'];
    }

    /**
     * Resolve mail template type for admin resend.
     * Order paid/confirmed share the same payload — never guess between them.
     *
     * @return string|null null when order mail cannot be uniquely typed
     */
    public function resolveEventTypeForResend(EmailLog $notification): ?string
    {
        if (is_string($notification->event_type) && $notification->event_type !== '') {
            return $notification->event_type;
        }

        $payload = is_array($notification->payload) ? $notification->payload : [];
        $fromPayload = $payload['event_type'] ?? null;
        if (is_string($fromPayload) && $fromPayload !== '') {
            return $fromPayload;
        }

        // Ambiguous: order.paid vs order.confirmed have identical bodies
        if (isset($payload['order_id'])) {
            return null;
        }

        return 'user.registered';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function mailableForEventType(?string $eventType, array $payload): Mailable
    {
        $resolved = $eventType;
        if ($resolved === null || $resolved === '') {
            $fromPayload = $payload['event_type'] ?? null;
            $resolved = is_string($fromPayload) && $fromPayload !== '' ? $fromPayload : null;
        }

        return match ($resolved) {
            'order.paid' => new OrderPaidMail($payload),
            'order.confirmed' => new OrderConfirmedMail($payload),
            'order.cancelled' => new OrderCancelledMail($payload),
            'user.registered', null => new WelcomeMail($payload),
            default => new WelcomeMail($payload),
        };
    }

    /**
     * Отправка Welcome-письма с логикой повторных попыток и записью в MongoDB
     */
    public function sendWelcomeMailWithRetry(array $body, ?string $eventType = 'user.registered'): array
    {
        return $this->sendMailWithRetry(
            fn () => new WelcomeMail($body),
            $body,
            $eventType
        );
    }

    /**
     * Отправка письма с ретраями и записью в MongoDB.
     *
     * @param  callable(): Mailable  $mailableFactory
     * @return array{isSent: bool, emailLog: EmailLog, lastError: string}
     */
    public function sendMailWithRetry(callable $mailableFactory, array $body, ?string $eventType = null): array
    {
        $emailAddress = $body['email'];

        $payload = $body;
        if ($eventType !== null && $eventType !== '') {
            $payload['event_type'] = $eventType;
        }

        $emailLog = EmailLog::create([
            'name' => $body['name'] ?? null,
            'email' => $emailAddress,
            'status' => 'pending',
            'retries' => 0,
            'moved_to_dlq' => false,
            'payload' => $payload,
            'event_type' => $eventType,
        ]);

        $result = $this->attemptSendWithRetry($emailLog, $emailAddress, $mailableFactory);

        return [
            'isSent' => $result['isSent'],
            'emailLog' => $emailLog,
            'lastError' => $result['lastError'],
        ];
    }

    /**
     * Shared SMTP send loop with exponential backoff (primary Kafka path + admin resend).
     *
     * @param  callable(): Mailable  $mailableFactory
     * @return array{isSent: bool, lastError: string}
     */
    protected function attemptSendWithRetry(EmailLog $emailLog, string $emailAddress, callable $mailableFactory): array
    {
        $attempt = 0;
        $isSent = false;
        $lastError = '';

        while ($attempt < self::MAX_RETRIES && ! $isSent) {
            $attempt++;

            try {
                Mail::to($emailAddress)->send($mailableFactory());

                $emailLog->update([
                    'status' => 'sent',
                    'retries' => $attempt,
                    'moved_to_dlq' => false,
                    'error_message' => null,
                    'time' => time(),
                ]);

                $isSent = true;
                Log::info("[KAFKA УСПЕХ] Письмо отправлено на {$emailAddress} с попытки {$attempt}");
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                $emailLog->update(['retries' => $attempt]);

                if ($attempt < self::MAX_RETRIES) {
                    $sleepTime = self::INITIAL_BACKOFF_SEC * (2 ** ($attempt - 1));

                    Log::warning("SMTP Сбой (Попытка {$attempt}/".self::MAX_RETRIES."). Ждем {$sleepTime} сек...", [
                        'email' => $emailAddress,
                        'error' => $lastError,
                    ]);

                    $this->sleepInTest($sleepTime);
                }
            }
        }

        return [
            'isSent' => $isSent,
            'lastError' => $lastError,
        ];
    }

    /**
     * Вынесено в отдельный метод для переопределения в юнит-тестах (чтобы не ждать реальные секунды)
     */
    protected function sleepInTest(int $seconds): void
    {
        // Skip real sleep under PHPUnit (Docker may keep APP_ENV=local over phpunit.xml).
        if (defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__') || app()->runningUnitTests()) {
            return;
        }

        sleep($seconds);
    }
}
