<?php

namespace App\Listeners;

use App\Events\OrderEventMovedToDlq;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;

class PublishOrderEventToDlq
{
    public function handle(OrderEventMovedToDlq $event): void
    {
        $message = $event->message;

        try {
            Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                ->onTopic('order.events.dlq')
                ->withKafkaKey((string) $message->getKey())
                ->withHeaders(array_merge($message->getHeaders() ?? [], [
                    'dlq-reason' => $event->errorMessage,
                    'dlq-failed-at' => now()->toIso8601String(),
                ]))
                ->withBody($message->getBody())
                ->send();

            Log::error('Сообщение не обработано и перенаправлено в DLQ (order.events.dlq)', [
                'email' => $event->email,
                'error' => $event->errorMessage,
            ]);
        } catch (\Throwable $e) {
            Log::critical('Не удалось опубликовать сообщение в DLQ топик: '.$e->getMessage());
        }
    }
}
