<?php

namespace App\Services\Kafka;

use Junges\Kafka\Facades\Kafka;

class KafkaPublisher
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    public function publish(string $topic, string $key, array $headers, array $body): void
    {
        Kafka::publish(config('kafka.brokers', 'kafka:29092'))
            ->onTopic($topic)
            ->withConfigOptions([
                'socket.timeout.ms' => 60000,
                'message.timeout.ms' => 60000,
                'request.timeout.ms' => 30000,
                'retries' => 10,
                'retry.backoff.ms' => 1000,
            ])
            ->withKafkaKey($key)
            ->withHeaders($headers)
            ->withBody($body)
            ->send();
    }
}
