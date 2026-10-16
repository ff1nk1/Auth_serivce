<?php

namespace App\Kafka\Handlers;

use App\Events\UserEventMovedToDlq;
use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class UserEventsHandler
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $body = $message->getBody();
        if (! is_array($body)) {
            Log::warning('Kafka user.events: тело сообщения не массив');

            return;
        }

        $emailAddress = $body['email'] ?? null;

        if (! $emailAddress) {
            Log::warning('Kafka: Сообщение пропущено, отсутствует email', ['body' => $body]);

            return;
        }

        $headers = $message->getHeaders() ?? [];
        $eventType = $headers['event-type']
            ?? ($body['event_type'] ?? 'user.registered');

        $result = $this->notificationService->sendWelcomeMailWithRetry($body, $eventType);

        // Если все попытки исчерпаны — отправляем в DLQ
        if (! $result['isSent']) {
            $this->sendToDlq($message, $result['emailLog'], $result['lastError']);
        }
    }

    /**
     * Пересылка необработанного сообщения в DLQ топик
     */
    private function sendToDlq(ConsumerMessage $message, EmailLog $emailLog, string $errorMessage): void
    {
        $emailLog->update([
            'status' => 'failed',
            'moved_to_dlq' => true,
            'error_message' => $errorMessage,
        ]);

        event(new UserEventMovedToDlq($message, $errorMessage, (string) $emailLog->email));
    }
}
