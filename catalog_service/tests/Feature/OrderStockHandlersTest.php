<?php

namespace Tests\Feature;

use App\Kafka\Handlers\OrderCreatedHandler;
use App\Kafka\Handlers\StockReleaseHandler;
use App\Models\OutboxEvent;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Junges\Kafka\Contracts\ConsumerMessage;
use Mockery;
use Tests\TestCase;

class OrderStockHandlersTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_order_created_reserves_stock_and_writes_outbox(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);
        $stock = Stock::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 10,
        ]);

        $message = $this->consumerMessage('order.created', [
            'order_id' => 1001,
            'items' => [[
                'product_id' => $product->id,
                'store_id' => $store->id,
                'quantity' => 3,
            ]],
        ]);

        (new OrderCreatedHandler)($message);

        $this->assertEquals(7, $stock->fresh()->quantity);
        $this->assertDatabaseHas('outbox_events', [
            'topic' => 'stock.events',
            'event_type' => 'stock.reserved',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'topic' => 'catalog.events',
            'event_type' => 'stock.changed',
        ]);
    }

    public function test_order_created_fails_without_partial_stock_change(): void
    {
        $store = Store::factory()->create();
        $productA = Product::factory()->create(['store_id' => $store->id]);
        $productB = Product::factory()->create(['store_id' => $store->id]);
        $stockA = Stock::factory()->create([
            'product_id' => $productA->id,
            'store_id' => $store->id,
            'quantity' => 5,
        ]);
        $stockB = Stock::factory()->create([
            'product_id' => $productB->id,
            'store_id' => $store->id,
            'quantity' => 1,
        ]);

        $message = $this->consumerMessage('order.created', [
            'order_id' => 1002,
            'items' => [
                ['product_id' => $productA->id, 'store_id' => $store->id, 'quantity' => 2],
                ['product_id' => $productB->id, 'store_id' => $store->id, 'quantity' => 5],
            ],
        ]);

        (new OrderCreatedHandler)($message);

        $this->assertEquals(5, $stockA->fresh()->quantity);
        $this->assertEquals(1, $stockB->fresh()->quantity);
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'stock.reservation_failed',
        ]);
        $this->assertSame(0, OutboxEvent::where('event_type', 'stock.reserved')->count());
    }

    public function test_stock_release_restores_quantity(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);
        $stock = Stock::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 4,
        ]);

        $message = $this->consumerMessage('stock.release_requested', [
            'order_id' => 1003,
            'items' => [[
                'product_id' => $product->id,
                'store_id' => $store->id,
                'quantity' => 2,
            ]],
        ]);

        (new StockReleaseHandler)($message);

        $this->assertEquals(6, $stock->fresh()->quantity);
        $this->assertDatabaseHas('outbox_events', [
            'event_type' => 'stock.changed',
        ]);
    }

    public function test_stock_release_skips_missing_stock_rows(): void
    {
        $message = $this->consumerMessage('stock.release_requested', [
            'order_id' => 1004,
            'items' => [[
                'product_id' => 99999,
                'store_id' => 99999,
                'quantity' => 1,
            ]],
        ]);

        (new StockReleaseHandler)($message);

        $this->assertSame(0, OutboxEvent::count());
    }

    public function test_order_created_ignores_wrong_event_type(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);
        $stock = Stock::factory()->create([
            'product_id' => $product->id,
            'store_id' => $store->id,
            'quantity' => 10,
        ]);

        $message = $this->consumerMessage('other.event', [
            'order_id' => 1,
            'items' => [[
                'product_id' => $product->id,
                'store_id' => $store->id,
                'quantity' => 1,
            ]],
        ]);

        (new OrderCreatedHandler)($message);

        $this->assertEquals(10, $stock->fresh()->quantity);
        $this->assertSame(0, OutboxEvent::count());
    }

    private function consumerMessage(string $eventType, array $body): ConsumerMessage
    {
        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getHeaders')->andReturn(['event-type' => $eventType]);
        $message->shouldReceive('getBody')->andReturn($body);

        return $message;
    }
}
