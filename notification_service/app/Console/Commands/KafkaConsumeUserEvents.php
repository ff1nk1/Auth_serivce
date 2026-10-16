<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\UserEventsHandler;

class KafkaConsumeUserEvents extends AbstractKafkaConsumeCommand
{
    protected $signature = 'kafka:consume:user-events';

    protected $description = 'Консьюмер для топика user.events';

    protected function topic(): string
    {
        return (string) config('kafka.topics.user_events', 'user.events');
    }

    protected function groupId(): string
    {
        return (string) config('kafka.consumer_groups.user', 'notification-service-consumers-v1');
    }

    protected function handlerClass(): string
    {
        return UserEventsHandler::class;
    }
}
