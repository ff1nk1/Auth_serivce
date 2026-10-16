<?php

namespace App\Kafka\Handlers;

use App\Services\KafkaMailPipeline;
use Junges\Kafka\Contracts\ConsumerMessage;

class OrderEventsHandler
{
    public const ALLOWED_TYPES = ['order.paid', 'order.confirmed', 'order.cancelled'];

    public function __construct(
        private KafkaMailPipeline $pipeline,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $this->pipeline->handle(
            $message,
            self::ALLOWED_TYPES,
            (string) config('kafka.topics.order_events', 'order.events'),
            (string) config('kafka.topics.order_events_dlq', 'order.events.dlq'),
        );
    }
}
