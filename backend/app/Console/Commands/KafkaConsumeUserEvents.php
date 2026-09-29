<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Kafka\Handlers\UserEventsHandler;
use Junges\Kafka\Facades\Kafka;

class KafkaConsumeUserEvents extends Command
{
    protected $signature = 'kafka:consume:user-events';
    protected $description = 'Консьюмер для топика user.events';

    public function handle(): int
    {
        $this->info("Запуск консьюмера для топика user.events...");

        $consumer = Kafka::consumer(['user.events'])
            ->withBrokers(config('kafka.brokers', 'kafka:29092'))
            ->withConsumerGroupId('auth-service-consumers-v1') // Оставляем стабильное имя группы
            ->withHandler(new UserEventsHandler())
            ->withAutoCommit()
            ->build();

        try {
            $consumer->consume();
        } catch (\Throwable $e) {
            $this->error("ОШИБКА KAFKA: " . $e->getMessage());
        }

        return self::SUCCESS;
    }
}