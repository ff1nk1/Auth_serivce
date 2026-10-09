<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\StockEventsHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class KafkaConsumeStockEvents extends Command
{
    protected $signature = 'kafka:consume:stock-events';

    protected $description = 'Консьюмер Order Service для stock.reserved / stock.reservation_failed';

    public function handle(): int
    {
        $this->info('Запуск консьюмера для топика stock.events...');

        $consumer = Kafka::consumer(['stock.events'])
            ->withBrokers(config('kafka.brokers', 'kafka:29092'))
            ->withConsumerGroupId('order-service-stock-v1')
            ->withHandler(app(StockEventsHandler::class))
            ->withAutoCommit()
            ->withOptions([
                'auto.offset.reset' => 'earliest',
            ])
            ->build();

        try {
            $consumer->consume();
        } catch (\Throwable $e) {
            $this->error('ОШИБКА KAFKA: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
