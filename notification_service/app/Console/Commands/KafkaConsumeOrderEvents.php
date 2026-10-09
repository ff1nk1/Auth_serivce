<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\OrderEventsHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class KafkaConsumeOrderEvents extends Command
{
    protected $signature = 'kafka:consume:order-events';

    protected $description = 'Консьюмер для топика order.events';

    public function handle(): int
    {
        $this->info('Запуск консьюмера для топика order.events...');

        $consumer = Kafka::consumer(['order.events'])
            ->withBrokers(config('kafka.brokers', 'kafka:29092'))
            ->withConsumerGroupId('notification-service-order-v1')
            ->withHandler(app(OrderEventsHandler::class))
            ->withAutoCommit()
            ->withOptions([
                'auto.offset.reset' => 'earliest',
            ])
            ->build();

        try {
            $consumer->consume();
        } catch (\Throwable $e) {
            $this->error('ОШИБКА KAFKA: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
