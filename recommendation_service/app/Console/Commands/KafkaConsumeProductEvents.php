<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\ProductEventsHandler;

class KafkaConsumeProductEvents extends AbstractKafkaConsumeCommand
{
    protected $signature = 'kafka:consume:product-events';

    protected $description = 'Консьюмер Recommendation Service для product.* событий';

    protected function topics(): array
    {
        return [
            (string) config('kafka.topics.product_created', 'product.created'),
            (string) config('kafka.topics.product_updated', 'product.updated'),
            (string) config('kafka.topics.product_deleted', 'product.deleted'),
            (string) config('kafka.topics.product_published', 'product.published'),
            (string) config('kafka.topics.product_unpublished', 'product.unpublished'),
        ];
    }

    protected function groupId(): string
    {
        return (string) config('kafka.consumer_groups.products', 'recommendation-service-products-v1');
    }

    protected function handlerClass(): string
    {
        return ProductEventsHandler::class;
    }
}
