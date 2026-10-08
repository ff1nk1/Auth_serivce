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
            ]
        );

        Log::info('Kafka: product snapshot upserted', [
            'event' => $eventType,
            'product_id' => $snapshot->id,
            'name' => $snapshot->name,
            'price' => (string) $snapshot->price,
        ]);
    }
}
