<?php
namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka; 

class ProductService
{
    public function createProduct(array $data): Product
    {
        $product = Product::create($data);

        $this->clearProductCache();
        $this->publishToTopic('product.created', $product);

        return $product;
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $product->update($data);

        $this->clearProductCache();
        $this->publishToTopic('product.updated', $product);

        return $product;
    }

    public function deleteProduct(Product $product): void
    {
        $productData = clone $product;
        
        $product->delete();

        $this->clearProductCache();
        $this->publishToTopic('product.deleted', $productData);
    }

    public function clearProductCache(): void
    {
        Cache::tags(['products'])->flush();
    }

    /**
     * Публикация события в Kafka с использованием рабочего конфига.
     */
    protected function publishToTopic(string $topic, Product $product): void
    {
        try {
            // Подгружаем связи перед отправкой
            $product->loadMissing(['store', 'category']);

            Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                ->onTopic($topic) 
                ->withConfigOptions([
                    'socket.timeout.ms'  => 60000,
                    'message.timeout.ms' => 60000,
                    'request.timeout.ms' => 30000,
                    'retries'            => 10,
                    'retry.backoff.ms'   => 1000,
                ])
                // Ключ гарантирует, что события одного товара попадут в одну партицию и будут обработаны строго по порядку
                ->withKafkaKey((string) $product->id)
                ->withHeaders([
                    'event-type' => $topic,
                    'source'     => 'catalog-service', // Укажите имя вашего сервиса
                ])
                ->withBody([
                    'event'     => $topic,
                    'timestamp' => now()->toIso8601String(),
                    'data'      => $product->toArray(),
                ])
                ->send();

        } catch (\Throwable $e) {
            Log::error("Kafka publish failed on {$topic}", [
                'product_id' => $product->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}