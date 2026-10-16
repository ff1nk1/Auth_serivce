<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\OrderEventsHandler;

class KafkaConsumeOrderEvents extends AbstractKafkaConsumeCommand
{
    protected $signature = 'kafka:consume:order-events';

    protected $description = 'Консьюмер для топика order.events';

    protected function topic(): string
    {
        return (string) config('kafka.topics.order_events', 'order.events');
    }

    protected function groupId(): string
    {
        return (string) config('kafka.consumer_groups.order', 'notification-service-order-v1');
    }

    protected function handlerClass(): string
    {
        return OrderEventsHandler::class;
    }
}
