<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Services\CatalogService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Kafka::assertPublishedOn('category.created');
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'slug' => 'laptops']);
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

        Kafka::assertPublishedOn('product.created');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Phone']);
    }

    public function test_delete_product_publishes_kafka_event(): void
    {
        Kafka::fake();

        $product = Product::factory()->create();
        app(ProductService::class)->deleteProduct($product);

        Kafka::assertPublishedOn('product.deleted');
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
