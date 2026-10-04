<?php

namespace App\Kafka\Handlers;

use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;

class UserEventsHandler
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $body = $message->getBody();
        $emailAddress = $body['email'] ?? null;

        if (!$emailAddress) {
            Log::warning('Kafka: Сообщение пропущено, отсутствует email', ['body' => $body]);
            return;
        }

        // Вызываем сервис отправки писем
        $result = $this->notificationService->sendWelcomeMailWithRetry($body);

        // Если все попытки исчерпаны — отправляем в DLQ
        if (!$result['isSent']) {
            $this->sendToDlq($message, $result['emailLog'], $result['lastError']);
        }
    }

    /**
     * Пересылка необработанного сообщения в DLQ топик
     */
    private function sendToDlq(ConsumerMessage $message, EmailLog $emailLog, string $errorMessage): void
    {
        // Помечаем документ в MongoDB
        $emailLog->update([
            'status'        => 'failed',
            'moved_to_dlq'  => true,
            'error_message' => $errorMessage,
        ]);

        try {
            Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                ->onTopic('user.events.dlq')
                ->withKafkaKey((string) $message->getKey())
                ->withHeaders(array_merge($message->getHeaders() ?? [], [
                    'dlq-reason' => $errorMessage,
                    'dlq-failed-at' => now()->toIso8601String(),
                ]))
                ->withBody($message->getBody())
                ->send();

            Log::error("Сообщение не обработано и перенаправлено в DLQ (user.events.dlq)", [
                'email' => $emailLog->email,
                'error' => $errorMessage,
            ]);

        } catch (\Throwable $e) {
            Log::critical('Не удалось опубликовать сообщение в DLQ топик: ' . $e->getMessage());
        }
    }
}