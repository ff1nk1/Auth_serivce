<?php

namespace Tests\Feature;

use App\Kafka\Handlers\ProductEventsHandler;
use App\Services\IdempotencyService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mockery;
use Tests\TestCase;

class ProductSnapshotOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_order_create_fails_without_snapshot(): void
    {
        $idempotency = Mockery::mock(IdempotencyService::class);
        $idempotency->shouldReceive('get')->andReturn(null);
        $idempotency->shouldReceive('acquireLock')->andReturn(true);
        $idempotency->shouldReceive('releaseLock')->once();
        $idempotency->shouldReceive('put')->never();

        $this->app->instance(IdempotencyService::class, $idempotency);

        $this->expectException(ValidationException::class);

        app(OrderService::class)->create(1, [
            ['product_id' => 136, 'quantity' => 1],
        ], 'idem-missing-snapshot');
    }

    public function test_handler_snapshot_allows_order_create(): void
    {
        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getHeaders')->andReturn(['event-type' => 'product.created']);
        $message->shouldReceive('getBody')->andReturn([
            'event' => 'product.created',
            'timestamp' => now()->toIso8601String(),
            'data' => [
                'id' => 136,
                'name' => 'че то новое',
                'price' => '6767.00',
                'store_id' => 1,
            ],
        ]);

        (new ProductEventsHandler)($message);

        $idempotency = Mockery::mock(IdempotencyService::class);
        $idempotency->shouldReceive('get')->andReturn(null);
        $idempotency->shouldReceive('acquireLock')->once()->andReturn(true);
        $idempotency->shouldReceive('put')->once();
        $idempotency->shouldReceive('releaseLock')->once();

        $this->app->instance(IdempotencyService::class, $idempotency);

        $result = app(OrderService::class)->create(7, [
            ['product_id' => 136, 'quantity' => 2],
        ], 'idem-after-snapshot');

        $this->assertSame(201, $result['status']);
        $this->assertSame('6767.00', $result['body']['items'][0]['unit_price'] ?? null);
        $this->assertSame('13534.00', $result['body']['total_amount'] ?? null);
        $this->assertDatabaseHas('orders', [
            'user_id' => 7,
            'status' => 'pending',
            'total_amount' => '13534.00',
        ]);
        $this->assertDatabaseHas('order_items', [
            'product_id' => 136,
            'quantity' => 2,
            'unit_price' => '6767.00',
        ]);
    }

    public function test_http_order_create_uses_snapshot_price(): void
    {
        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getHeaders')->andReturn(['event-type' => 'product.updated']);
        $message->shouldReceive('getBody')->andReturn([
            'event' => 'product.updated',
            'data' => [
                'id' => 137,
                'name' => 'f',
                'price' => '111.00',
                'store_id' => 1,
            ],
        ]);

        (new ProductEventsHandler)($message);

        $idempotency = Mockery::mock(IdempotencyService::class);
        $idempotency->shouldReceive('get')->andReturn(null);
        $idempotency->shouldReceive('acquireLock')->once()->andReturn(true);
        $idempotency->shouldReceive('put')->once();
        $idempotency->shouldReceive('releaseLock')->once();
        $this->app->instance(IdempotencyService::class, $idempotency);

        $response = $this->postJson('/api/orders', [
            'user_id' => 3,
            'items' => [
                ['product_id' => 137, 'quantity' => 1],
            ],
        ], [
            'Idempotency-Key' => 'http-idem-137',
        ]);

        $response->assertCreated()
            ->assertJsonPath('total_amount', '111.00')
            ->assertJsonPath('items.0.product_id', 137)
            ->assertJsonPath('items.0.unit_price', '111.00');
    }
}
