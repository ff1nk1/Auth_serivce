<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Database\Seeder;

/**
 * Публикует product.created/updated в Kafka для всех товаров,
 * чтобы order_service consumer обновил product_snapshots.
 */
class RepublishProductsSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()->with(['store', 'category'])->orderBy('id')->get();

        if ($products->isEmpty()) {
            $this->command?->warn('Нет товаров — сначала ShopSeeder.');

            return;
        }

        /** @var ProductService $productsService */
        $productsService = app(ProductService::class);

        foreach ($products as $product) {
            // updateProduct публикует product.updated с актуальным payload.
            $productsService->updateProduct($product, [
                'name' => $product->name,
            ]);
        }

        $this->command?->info(sprintf(
            'Republished %d products to Kafka (product.updated).',
            $products->count()
        ));
    }
}
