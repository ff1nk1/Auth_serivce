<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Kafka\Handlers\OrderCreatedHandler;
use Junges\Kafka\Facades\Kafka;
use App\Kafka\MainHandlers\OrderEventsHandler;


class KafkaConsumeOrderEvents extends Command
{
    protected $signature = 'kafka:consume:order-events';
    
    protected $description = 'Консьюмер Catalog Service для обработки событий заказов';

    public function handle(): int
    {
        $this->info("Запуск консьюмера для топика order.events...");

        $consumer = Kafka::consumer(['order.events'])
            ->withBrokers(config('kafka.brokers', 'kafka:29092'))
            
            ->withConsumerGroupId('catalog-service-orders-v1') 
            ->withHandler(app(OrderEventsHandler::class))            
            ->withAutoCommit()
            ->withOptions([
                'auto.offset.reset' => 'earliest',
            ])
            ->build();

        try {
            $consumer->consume();
        } catch (\Throwable $e) {
            $this->error("ОШИБКА KAFKA: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}