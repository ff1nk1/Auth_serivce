<?php

namespace Tests\Feature;

use App\Kafka\Handlers\ProductEventsHandler;
use App\Kafka\Handlers\StockEventsHandler;
use App\Models\ProductCatalog;
use App\Services\Llm\Contracts\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeConsumerMessage;
use Tests\Fakes\FakeLlmClient;
use Tests\TestCase;

class ProductKafkaHandlersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(LlmClient::class, new FakeLlmClient);
    }

    public function test_product_created_upserts_and_embeds(): void
    {
        $message = new FakeConsumerMessage(
            body: [
                'event' => 'product.created',
                'data' => [
                    'id' => 55,
                    'store_id' => 3,
                    'name' => 'Phone',
                    'description' => 'Smart',
                    'price' => 199,
                    'category' => ['id' => 9, 'name' => 'Gadgets'],
                    'attributes' => [['name' => 'Color', 'value' => 'Black']],
                    'is_published' => true,
                ],
            ],
            headers: ['event-type' => 'product.created'],
            key: '55',
            topicName: 'product.created',
        );

        app(ProductEventsHandler::class)($message);

        $this->assertDatabaseHas('product_catalog', [
            'product_id' => 55,
            'store_id' => 3,
            'name' => 'Phone',
            'category_name' => 'Gadgets',
            'is_published' => true,
        ]);

        $embedding = DB::table('product_catalog')->where('product_id', 55)->value('embedding');
        $this->assertNotNull($embedding);
    }

    public function test_product_deleted_removes_row(): void
    {
        ProductCatalog::query()->create([
            'product_id' => 70,
            'store_id' => 1,
            'name' => 'Gone',
            'price' => 1,
            'is_published' => true,
            'in_stock' => true,
            'attributes' => [],
        ]);

        app(ProductEventsHandler::class)(new FakeConsumerMessage(
            body: ['event' => 'product.deleted', 'data' => ['id' => 70]],
            headers: ['event-type' => 'product.deleted'],
        ));

        $this->assertDatabaseMissing('product_catalog', ['product_id' => 70]);
    }

    public function test_product_unpublished_sets_flag(): void
    {
        ProductCatalog::query()->create([
            'product_id' => 71,
            'store_id' => 1,
            'name' => 'Hidden',
            'price' => 1,
            'is_published' => true,
            'in_stock' => true,
            'attributes' => [],
        ]);

        app(ProductEventsHandler::class)(new FakeConsumerMessage(
            body: ['event' => 'product.unpublished', 'data' => ['id' => 71, 'is_published' => false]],
            headers: ['event-type' => 'product.unpublished'],
        ));

        $this->assertDatabaseHas('product_catalog', [
            'product_id' => 71,
            'is_published' => false,
        ]);
    }

    public function test_stock_changed_updates_in_stock(): void
    {
        ProductCatalog::query()->create([
            'product_id' => 80,
            'store_id' => 1,
            'name' => 'Stocked',
            'price' => 10,
            'is_published' => true,
            'in_stock' => true,
            'attributes' => [],
        ]);

        app(StockEventsHandler::class)(new FakeConsumerMessage(
            body: ['product_id' => 80, 'store_id' => 1, 'quantity' => 0],
            headers: ['event-type' => 'stock.changed'],
            topicName: 'catalog.events',
        ));

        $this->assertDatabaseHas('product_catalog', [
            'product_id' => 80,
            'in_stock' => false,
        ]);
    }
}
