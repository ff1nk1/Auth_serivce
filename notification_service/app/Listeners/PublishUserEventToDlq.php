<?php

namespace App\Listeners;

use App\Events\UserEventMovedToDlq;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;

class PublishUserEventToDlq
{
    public function handle(UserEventMovedToDlq $event): void
    {
        $message = $event->message;

        try {
            Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                ->onTopic('user.events.dlq')
                ->withKafkaKey((string) $message->getKey())
                ->withHeaders(array_merge($message->getHeaders() ?? [], [
                    'dlq-reason' => $event->errorMessage,
                    'dlq-failed-at' => now()->toIso8601String(),
                ]))
                ->withBody($message->getBody())
                ->send();

            Log::error('Сообщение не обработано и перенаправлено в DLQ (user.events.dlq)', [
                'email' => $event->email,
                'error' => $event->errorMessage,
            ]);
        } catch (\Throwable $e) {
            Log::critical('Не удалось опубликовать сообщение в DLQ топик: '.$e->getMessage());
        }
    }
}
