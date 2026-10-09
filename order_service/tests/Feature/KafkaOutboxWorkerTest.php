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

            return ($headers['event-type'] ?? null) === 'order.created'
                && ($headers['source'] ?? null) === 'order-service';
        });
    }
}
