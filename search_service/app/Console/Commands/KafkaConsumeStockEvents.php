<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\StockEventsHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class KafkaConsumeStockEvents extends Command
{
    protected $signature = 'kafka:consume:stock-events';

    protected $description = 'Консьюмер Search Service для stock.changed на топике catalog.events';

    public function handle(): int
    {
        $this->info('Запуск консьюмера для топика catalog.events (stock.changed)...');

        $consumer = Kafka::consumer(['catalog.events'])
            ->withBrokers(config('kafka.brokers', 'kafka:29092'))
            ->withConsumerGroupId('search-service-stock-v1')
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
