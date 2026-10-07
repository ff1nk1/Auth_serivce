<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\CatalogService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;
use Tests\TestCase;

class CatalogProductKafkaTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_category_publishes_kafka_event(): void
    {
        Kafka::fake();

        $category = app(CatalogService::class)->createCategory([
            'name' => 'Laptops',
            'slug' => 'laptops',
        ]);

        Kafka::assertPublishedOn('category.created', null, function ($message) use ($category) {
            $headers = $message->getHeaders();
            $body = $message->getBody();

            return ($headers['event-type'] ?? null) === 'category.created'
                && ($headers['source'] ?? null) === 'catalog-service'
                && ($body['event'] ?? null) === 'category.created'
                && ($body['data']['id'] ?? null) === $category->id
                && (string) $message->getKey() === (string) $category->id;
        });

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'laptops']);
    }

    public function test_update_category_publishes_kafka_event(): void
    {
        Kafka::fake();

        $category = Category::factory()->create(['name' => 'Old', 'slug' => 'old']);
        app(CatalogService::class)->updateCategory($category, ['name' => 'New']);

        Kafka::assertPublishedOn('category.updated', null, function ($message) use ($category) {
            $body = $message->getBody();

            return ($message->getHeaders()['event-type'] ?? null) === 'category.updated'
                && ($body['data']['id'] ?? null) === $category->id
                && ($body['data']['name'] ?? null) === 'New';
        });
    }

    public function test_delete_category_with_children_publishes_updated_and_deleted(): void
    {
        Kafka::fake();

        $parent = Category::factory()->create(['name' => 'Parent', 'slug' => 'parent']);
        $child = Category::factory()->create([
            'name' => 'Child',
            'slug' => 'child',
            'parent_id' => $parent->id,
        ]);

        app(CatalogService::class)->deleteCategory($parent);

        Kafka::assertPublishedOn('category.updated');
        Kafka::assertPublishedOn('category.deleted');

        $this->assertDatabaseMissing('categories', ['id' => $parent->id]);
        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_create_product_publishes_kafka_event(): void
    {
        Kafka::fake();

        $store = Store::factory()->create();
        $category = Category::factory()->create();

        $product = app(ProductService::class)->createProduct([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Phone',
            'price' => 199.99,
        ]);

        Kafka::assertPublishedOn('product.created', null, function ($message) use ($product) {
            $headers = $message->getHeaders();
            $body = $message->getBody();

            return ($headers['event-type'] ?? null) === 'product.created'
                && ($headers['source'] ?? null) === 'catalog-service'
                && ($body['event'] ?? null) === 'product.created'
                && isset($body['timestamp'])
                && ($body['data']['id'] ?? null) === $product->id
                && (string) $message->getKey() === (string) $product->id;
        });

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Phone']);
    }

    public function test_update_product_publishes_kafka_event(): void
    {
        Kafka::fake();

        $product = Product::factory()->create(['name' => 'Old phone']);
        app(ProductService::class)->updateProduct($product, ['name' => 'New phone']);

        Kafka::assertPublishedOn('product.updated', null, function ($message) use ($product) {
            $body = $message->getBody();

            return ($message->getHeaders()['event-type'] ?? null) === 'product.updated'
                && ($body['data']['id'] ?? null) === $product->id
                && ($body['data']['name'] ?? null) === 'New phone';
        });
    }

    public function test_delete_product_publishes_kafka_event(): void
    {
        Kafka::fake();

        $product = Product::factory()->create();
        $productId = $product->id;
        app(ProductService::class)->deleteProduct($product);

        Kafka::assertPublishedOn('product.deleted', null, function ($message) use ($productId) {
            return ($message->getBody()['data']['id'] ?? null) === $productId;
        });

        $this->assertDatabaseMissing('products', ['id' => $productId]);
    }

    public function test_create_product_succeeds_when_kafka_fails(): void
    {
        Kafka::shouldReceive('publish')
            ->once()
            ->andThrow(new \Exception('Connection to Kafka broker timed out'));

        $logSpy = Log::spy();

        $store = Store::factory()->create();
        $category = Category::factory()->create();

        $product = app(ProductService::class)->createProduct([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Resilient phone',
            'price' => 99,
        ]);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Resilient phone',
        ]);

        $logSpy->shouldHaveReceived('error')
            ->once()
            ->withArgs(function ($message, $context) use ($product) {
                return $message === 'Kafka publish failed on product.created'
                    && ($context['product_id'] ?? null) === $product->id;
            });
    }
}
