<?php

namespace App\Console\Commands;

use App\Models\OutboxEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;

class KafkaOutboxWorker extends Command
{
    protected $signature = 'kafka:outbox:work';

    protected $description = 'Читает события из таблицы outbox_events и отправляет их в Kafka';

    public function handle(): int
    {
        $this->info('Запуск Outbox Worker (order-service)...');

        while (true) {
            $processed = $this->processBatch();

            if ($processed === 0) {
                sleep(5);
            }
        }

        return self::SUCCESS;
    }

    public function processBatch(): int
    {
        $events = OutboxEvent::query()->orderBy('created_at', 'asc')->limit(50)->get();

        if ($events->isEmpty()) {
            return 0;
        }

        $published = 0;

        foreach ($events as $event) {
            try {
                $headers = [
                    'event-type' => $event->event_type,
                    'source' => 'order-service',
                ];
                $payload = is_array($event->payload) ? $event->payload : [];
                if (is_string($payload['saga_id'] ?? null) && $payload['saga_id'] !== '') {
                    $headers['saga-id'] = $payload['saga_id'];
                }

                Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                    ->onTopic($event->topic)
                    ->withConfigOptions([
                        'socket.timeout.ms' => 60000,
                        'message.timeout.ms' => 60000,
                        'request.timeout.ms' => 30000,
                        'retries' => 10,
                        'retry.backoff.ms' => 1000,
                    ])
                    ->withKafkaKey((string) $event->id)
                    ->withHeaders($headers)
                    ->withBody($event->payload)
                    ->send();

                $event->delete();
                $published++;

                if ($this->output !== null) {
                    $this->info("Отправлено событие: {$event->event_type} в топик {$event->topic}");
                }
            } catch (\Throwable $e) {
                Log::error('Ошибка отправки Outbox события в Kafka', [
                    'event_id' => $event->id,
                    'topic' => $event->topic,
                    'error' => $e->getMessage(),
                ]);

                if (! app()->environment('testing')) {
                    sleep(3);
                }
                break;
            }
        }

        return $published;
    }
}
