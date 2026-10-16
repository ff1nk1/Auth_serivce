<?php

namespace App\Kafka\Handlers;

use App\Services\KafkaMailPipeline;
use Junges\Kafka\Contracts\ConsumerMessage;

class UserEventsHandler
{
    public const ALLOWED_TYPES = ['user.registered'];

    public function __construct(
        private KafkaMailPipeline $pipeline,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $this->pipeline->handle(
            $message,
            self::ALLOWED_TYPES,
            (string) config('kafka.topics.user_events', 'user.events'),
            (string) config('kafka.topics.user_events_dlq', 'user.events.dlq'),
        );
    }
}
