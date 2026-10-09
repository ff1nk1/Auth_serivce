<?php

namespace App\Kafka\Handlers;

use App\Mail\OrderCancelledMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderPaidMail;
use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class OrderEventsHandler
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders() ?? [];
        $eventType = $headers['event-type'] ?? null;
        $body = $message->getBody();

        if (! is_array($body)) {
            Log::warning('Kafka order.events: тело сообщения не массив', ['event-type' => $eventType]);

            return;
        }

        $factory = $this->mailableFactoryFor($eventType, $body);
        if ($factory === null) {
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

        $result = $this->notificationService->sendMailWithRetry($factory, $body);

        if (! $result['isSent']) {
            $this->sendToDlq($message, $result['emailLog'], $result['lastError']);
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return (callable(): Mailable)|null
     */
    private function mailableFactoryFor(?string $eventType, array $body): ?callable
    {
        return match ($eventType) {
            'order.paid' => fn () => new OrderPaidMail($body),
            'order.confirmed' => fn () => new OrderConfirmedMail($body),
            'order.cancelled' => fn () => new OrderCancelledMail($body),
            default => null,
        };
    }

    private function sendToDlq(ConsumerMessage $message, EmailLog $emailLog, string $errorMessage): void
    {
        $emailLog->update([
            'status' => 'failed',
            'moved_to_dlq' => true,
            'error_message' => $errorMessage,
        ]);

        try {
            Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                ->onTopic('order.events.dlq')
                ->withKafkaKey((string) $message->getKey())
                ->withHeaders(array_merge($message->getHeaders() ?? [], [
                    'dlq-reason' => $errorMessage,
                    'dlq-failed-at' => now()->toIso8601String(),
                ]))
                ->withBody($message->getBody())
                ->send();

            Log::error('Сообщение не обработано и перенаправлено в DLQ (order.events.dlq)', [
                'email' => $emailLog->email,
                'error' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            Log::critical('Не удалось опубликовать сообщение в DLQ топик: '.$e->getMessage());
        }
    }
}
