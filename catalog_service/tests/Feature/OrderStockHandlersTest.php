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

    public function test_order_created_invalid_payload_writes_no_outbox(): void
    {
        $message = $this->consumerMessage('order.created', [
            'order_id' => null,
            'items' => [],
        ]);

        (new OrderCreatedHandler)($message);

        $this->assertSame(0, OutboxEvent::count());
    }

    public function test_order_created_multi_item_success_writes_stock_changed_per_item(): void
    {
        $store = Store::factory()->create();
        $productA = Product::factory()->create(['store_id' => $store->id]);
        $productB = Product::factory()->create(['store_id' => $store->id]);
        $stockA = Stock::factory()->create([
            'product_id' => $productA->id,
            'store_id' => $store->id,
            'quantity' => 10,
        ]);
        $stockB = Stock::factory()->create([
            'product_id' => $productB->id,
            'store_id' => $store->id,
            'quantity' => 8,
        ]);

        $message = $this->consumerMessage('order.created', [
            'order_id' => 2001,
            'items' => [
                ['product_id' => $productA->id, 'store_id' => $store->id, 'quantity' => 2],
                ['product_id' => $productB->id, 'store_id' => $store->id, 'quantity' => 3],
            ],
        ]);

        (new OrderCreatedHandler)($message);

        $this->assertEquals(8, $stockA->fresh()->quantity);
        $this->assertEquals(5, $stockB->fresh()->quantity);

        $reserved = OutboxEvent::where('event_type', 'stock.reserved')->first();
        $this->assertNotNull($reserved);
        $this->assertSame([
            'order_id' => 2001,
            'status' => 'success',
        ], $reserved->payload);

        $changed = OutboxEvent::where('event_type', 'stock.changed')->orderBy('id')->get();
        $this->assertCount(2, $changed);
        $this->assertEqualsCanonicalizing(
            [
                ['product_id' => $productA->id, 'store_id' => $store->id, 'quantity' => 8],
                ['product_id' => $productB->id, 'store_id' => $store->id, 'quantity' => 5],
            ],
            $changed->pluck('payload')->all()
        );
    }

    public function test_order_created_missing_stock_row_fails_reservation(): void
    {
        $store = Store::factory()->create();
        $product = Product::factory()->create(['store_id' => $store->id]);

        $message = $this->consumerMessage('order.created', [
            'order_id' => 2002,
            'items' => [[
                'product_id' => $product->id,
                'store_id' => $store->id,
                'quantity' => 1,
            ]],
        ]);

        (new OrderCreatedHandler)($message);

        $failed = OutboxEvent::where('event_type', 'stock.reservation_failed')->first();
        $this->assertNotNull($failed);
        $this->assertSame([
            'order_id' => 2002,
            'reason' => 'Not enough stock',
        ], $failed->payload);
        $this->assertSame(0, OutboxEvent::where('event_type', 'stock.reserved')->count());
    }

    public function test_stock_release_invalid_payload_writes_no_outbox(): void
    {
        $message = $this->consumerMessage('stock.release_requested', [
            'order_id' => 2003,
            'items' => [],
        ]);

        (new StockReleaseHandler)($message);

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
