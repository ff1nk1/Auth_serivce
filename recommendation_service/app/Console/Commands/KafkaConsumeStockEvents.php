<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\StockEventsHandler;

class KafkaConsumeStockEvents extends AbstractKafkaConsumeCommand
{
    protected $signature = 'kafka:consume:stock-events';

    protected $description = 'Консьюмер Recommendation Service для stock.changed на catalog.events';

    protected function topics(): array
    {
        return [
            (string) config('kafka.topics.catalog_events', 'catalog.events'),
        ];
    }

    protected function groupId(): string
    {
        return (string) config('kafka.consumer_groups.stock', 'recommendation-service-stock-v1');
    }

    protected function handlerClass(): string
    {
        return StockEventsHandler::class;
    }
}
