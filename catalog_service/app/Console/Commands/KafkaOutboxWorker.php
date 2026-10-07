<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OutboxEvent;
use Junges\Kafka\Facades\Kafka;
use Illuminate\Support\Facades\Log;

class KafkaOutboxWorker extends Command
{
    protected $signature = 'kafka:outbox:work';
    protected $description = 'Читает события из таблицы outbox_events и отправляет их в Kafka';

    public function handle(): int
    {
        $this->info('Запуск Outbox Worker...');

        while (true) {
            $processed = $this->processBatch();

            if ($processed === 0) {
                sleep(5);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Publish up to 50 pending outbox rows. Returns how many were published.
     * On Kafka failure the failing row is kept and processing stops for this batch.
     */
    public function processBatch(): int
    {
        $events = OutboxEvent::orderBy('created_at', 'asc')->limit(50)->get();

        if ($events->isEmpty()) {
            return 0;
        }

        $published = 0;

        foreach ($events as $event) {
            try {
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
                    ->withHeaders([
                        'event-type' => $event->event_type,
                        'source' => 'catalog-service',
                    ])
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