<?php

namespace App\Kafka\Handlers;

use App\Models\ProductSnapshot;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class ProductEventsHandler
{
    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders();
        $body = $message->getBody();
        $eventType = $headers['event-type'] ?? ($body['event'] ?? null);

        if (! in_array($eventType, ['product.created', 'product.updated'], true)) {
            Log::debug("Игнорируем неизвестное событие продукта: {$eventType}");

            return;
        }

        $data = $body['data'] ?? null;
        if (! is_array($data)) {
            Log::warning('Kafka: product event без data', ['body' => $body]);

            return;
        }

        $id = $data['id'] ?? null;
        $name = $data['name'] ?? null;
        $price = $data['price'] ?? null;
        $storeId = $data['store_id'] ?? null;
        $categoryId = $data['category_id'] ?? null;
        if ($categoryId === null && isset($data['category']) && is_array($data['category'])) {
            $categoryId = $data['category']['id'] ?? null;
        }

        $storeName = null;
        if (isset($data['store']) && is_array($data['store'])) {
            $storeName = $data['store']['name'] ?? null;
        }

        $categoryName = null;
        $categorySlug = null;
        if (isset($data['category']) && is_array($data['category'])) {
            $categoryName = $data['category']['name'] ?? null;
            $categorySlug = $data['category']['slug'] ?? null;
        }

        if ($id === null || $name === null || $price === null || $storeId === null) {
            Log::warning('Kafka: product event с неполными полями снапшота', ['data' => $data]);

            return;
        }

        $snapshot = ProductSnapshot::query()->updateOrCreate(
            ['id' => (int) $id],
            [
                'name' => (string) $name,
                'price' => $price,
                'store_id' => (int) $storeId,
                'store_name' => $storeName !== null ? (string) $storeName : null,
                'category_id' => $categoryId !== null ? (int) $categoryId : null,
                'category_name' => $categoryName !== null ? (string) $categoryName : null,
                'category_slug' => $categorySlug !== null ? (string) $categorySlug : null,
            ]
        );

        Log::info('Kafka: product snapshot upserted', [
            'event' => $eventType,
            'product_id' => $snapshot->id,
            'name' => $snapshot->name,
            'price' => (string) $snapshot->price,
            'category_id' => $snapshot->category_id,
            'store_name' => $snapshot->store_name,
            'category_name' => $snapshot->category_name,
        ]);
    }
}
