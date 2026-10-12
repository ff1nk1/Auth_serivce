<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;

class ProductService
{
    public function createProduct(array $data): Product
    {
        $attributes = $this->extractAttributes($data);

        $product = DB::transaction(function () use ($data, $attributes): Product {
            $product = Product::create($data);
            $this->syncAttributes($product, $attributes ?? []);

            return $product->fresh(['store', 'category', 'attributes']) ?? $product;
        });

        $this->clearProductCache();
        $this->publishToTopic('product.created', $product);

        return $product;
    }

    public function updateProduct(Product $product, array $data): Product
    {
        $attributes = $this->extractAttributes($data);

        $product = DB::transaction(function () use ($product, $data, $attributes): Product {
            $product->update($data);
            if ($attributes !== null) {
                $this->syncAttributes($product, $attributes);
            }

            return $product->fresh(['store', 'category', 'attributes']) ?? $product;
        });

        $this->clearProductCache();
        $this->publishToTopic('product.updated', $product);

        return $product;
    }

    public function deleteProduct(Product $product): void
    {
        $product->loadMissing(['store', 'category', 'attributes']);
        $productData = clone $product;
        $productData->setRelation('attributes', $product->attributes);

        $product->delete();

        $this->clearProductCache();
        $this->publishToTopic('product.deleted', $productData);
    }

    public function clearProductCache(): void
    {
        Cache::tags(['products'])->flush();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{name: string, value: string}>|null  null = attributes key absent (do not sync)
     */
    protected function extractAttributes(array &$data): ?array
    {
        if (! array_key_exists('attributes', $data)) {
            return null;
        }

        $raw = Arr::pull($data, 'attributes');
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($name === '' || $value === '') {
                continue;
            }

            $normalized[] = [
                'name' => $name,
                'value' => $value,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{name: string, value: string}>  $attributes
     */
    protected function syncAttributes(Product $product, array $attributes): void
    {
        $product->attributes()->delete();

        if ($attributes === []) {
            return;
        }

        $product->attributes()->createMany($attributes);
    }

    /**
     * Публикация события в Kafka с использованием рабочего конфига.
     */
    protected function publishToTopic(string $topic, Product $product): void
    {
        try {
            $product->loadMissing(['store', 'category', 'attributes']);

            Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                ->onTopic($topic)
                ->withConfigOptions([
                    'socket.timeout.ms' => 60000,
                    'message.timeout.ms' => 60000,
                    'request.timeout.ms' => 30000,
                    'retries' => 10,
                    'retry.backoff.ms' => 1000,
                ])
                ->withKafkaKey((string) $product->id)
                ->withHeaders([
                    'event-type' => $topic,
                    'source' => 'catalog-service',
                ])
                ->withBody([
                    'event' => $topic,
                    'timestamp' => now()->toIso8601String(),
                    'data' => $product->toArray(),
                ])
                ->send();
        } catch (\Throwable $e) {
            Log::error("Kafka publish failed on {$topic}", [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
