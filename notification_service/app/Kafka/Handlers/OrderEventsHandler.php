<?php

namespace App\Kafka\Handlers;

use App\Events\OrderEventMovedToDlq;
use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class OrderEventsHandler
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders() ?? [];
        $body = $message->getBody();

        if (! is_array($body)) {
            Log::warning('Kafka order.events: тело сообщения не массив', [
                'event-type' => $headers['event-type'] ?? null,
            ]);

            return;
        }

        $eventType = $headers['event-type'] ?? ($body['event_type'] ?? null);

        if (! in_array($eventType, ['order.paid', 'order.confirmed', 'order.cancelled'], true)) {
            Log::debug("Игнорируем событие order.events: {$eventType}");

            return;
        }

        $emailAddress = $body['email'] ?? null;
        if (! $emailAddress) {
            Log::warning('Kafka order.events: сообщение пропущено, отсутствует email', [
                'event-type' => $eventType,
                'body' => $body,
            ]);

            return;
        }

        $factory = fn () => $this->notificationService->mailableForEventType($eventType, $body);
        $result = $this->notificationService->sendMailWithRetry($factory, $body, $eventType);

        if (! $result['isSent']) {
            $this->sendToDlq($message, $result['emailLog'], $result['lastError']);
        }
    }

    private function sendToDlq(ConsumerMessage $message, EmailLog $emailLog, string $errorMessage): void
    {
        $emailLog->update([
            'status' => 'failed',
            'moved_to_dlq' => true,
            'error_message' => $errorMessage,
        ]);

        event(new OrderEventMovedToDlq($message, $errorMessage, (string) $emailLog->email));
    }
}
