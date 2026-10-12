<?php

namespace Tests\Feature;

use App\Console\Commands\KafkaOutboxWorker;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Junges\Kafka\Facades\Kafka;
use Tests\TestCase;

class KafkaOutboxWorkerTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_batch_publishes_with_order_service_source(): void
    {
        Kafka::fake();

        $event = OutboxEvent::query()->create([
            'topic' => 'order.events',
            'event_type' => 'order.created',
            'payload' => ['order_id' => 1, 'items' => []],
        ]);

        $this->assertSame(1, app(KafkaOutboxWorker::class)->processBatch());
        $this->assertDatabaseMissing('outbox_events', ['id' => $event->id]);

        Kafka::assertPublishedOn('order.events', null, function ($message) {
            $headers = $message->getHeaders();
            $body = $message->getBody();

            return ($headers['event-type'] ?? null) === 'order.created'
                && ($headers['source'] ?? null) === 'order-service'
                && ($body['order_id'] ?? null) === 1;
        });
    }

    public function test_outbox_writer_payload_event_type_is_published_in_body(): void
    {
        Kafka::fake();

        app(\App\Services\OutboxWriter::class)->write('order.events', 'order.paid', [
            'order_id' => 99,
            'email' => 'a@example.com',
        ]);

        $this->assertSame(1, app(KafkaOutboxWorker::class)->processBatch());

        Kafka::assertPublishedOn('order.events', null, function ($message) {
            $headers = $message->getHeaders();
            $body = $message->getBody();

            return ($headers['event-type'] ?? null) === 'order.paid'
                && ($body['event_type'] ?? null) === 'order.paid'
                && ($body['order_id'] ?? null) === 99;
        });
    }
}
