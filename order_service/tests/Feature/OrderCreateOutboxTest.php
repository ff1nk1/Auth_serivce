<?php

namespace Tests\Feature;

use App\Models\OutboxEvent;
use App\Models\ProductSnapshot;
use App\Services\IdempotencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\BindsInMemorySagaStepGuard;
use Tests\TestCase;

class OrderCreateOutboxTest extends TestCase
{
    use BindsInMemorySagaStepGuard;
    use RefreshDatabase;

    /** @var array<string, array{status: int, body: array}> */
    private array $idempotencyStore = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bindInMemoryIdempotency();
        $this->bindInMemorySagaStepGuard();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_create_writes_order_created_outbox_with_store_id(): void
    {
        ProductSnapshot::query()->create([
            'id' => 42,
            'name' => 'Phone',
            'price' => '199.99',
            'store_id' => 7,
        ]);

        $response = $this->postJson('/api/orders', [
            'user_id' => 3,
            'email' => 'buyer@example.com',
            'name' => 'Buyer',
            'items' => [
                ['product_id' => 42, 'quantity' => 2],
            ],
        ], [
            'Idempotency-Key' => 'key-create-1',
        ]);

        $response->assertCreated();
        $orderId = $response->json('id');

        $event = OutboxEvent::query()->where('event_type', 'order.created')->first();
        $this->assertNotNull($event);
        $this->assertSame('order.events', $event->topic);
        $this->assertSame($orderId, $event->payload['order_id']);
        $this->assertIsString($event->payload['saga_id'] ?? null);
        $this->assertNotSame('', $event->payload['saga_id']);
        $this->assertDatabaseHas('orders', [
            'id' => $orderId,
            'saga_id' => $event->payload['saga_id'],
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
        ]);
        $this->assertSame([
            [
                'product_id' => 42,
                'store_id' => 7,
                'quantity' => 2,
            ],
        ], $event->payload['items']);
        $this->assertArrayHasKey($event->payload['saga_id'].':order.created', $this->sagaStepStore);
    }

    public function test_idempotent_create_does_not_write_second_outbox(): void
    {
        ProductSnapshot::query()->create([
            'id' => 42,
            'name' => 'Phone',
            'price' => '10.00',
            'store_id' => 1,
        ]);

        $headers = ['Idempotency-Key' => 'key-dup-1'];
        $payload = [
            'user_id' => 3,
            'email' => 'buyer@example.com',
            'name' => 'Buyer',
            'items' => [
                ['product_id' => 42, 'quantity' => 1],
            ],
        ];

        $first = $this->postJson('/api/orders', $payload, $headers);
        $first->assertCreated();

        $second = $this->postJson('/api/orders', $payload, $headers);
        $second->assertCreated();
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'order.created')->count());
    }

    private function bindInMemoryIdempotency(): void
    {
        $store = &$this->idempotencyStore;

        $idem = Mockery::mock(IdempotencyService::class);
        $idem->shouldReceive('get')->andReturnUsing(function (string $key) use (&$store) {
            return $store[$key] ?? null;
        });
        $idem->shouldReceive('acquireLock')->andReturn(true);
        $idem->shouldReceive('releaseLock')->andReturnNull();
        $idem->shouldReceive('put')->andReturnUsing(function (string $key, int $status, array $body) use (&$store) {
            $store[$key] = ['status' => $status, 'body' => $body];
        });

        $this->app->instance(IdempotencyService::class, $idem);
    }
}
