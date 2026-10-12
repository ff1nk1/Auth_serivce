<?php

namespace App\Kafka\Handlers;

use App\Services\SearchIndexService;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class ProductEventsHandler
{
    public function __construct(
        private readonly SearchIndexService $index,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders();
        $body = $message->getBody();
        $eventType = $headers['event-type'] ?? ($body['event'] ?? null);

        if (! in_array($eventType, ['product.created', 'product.updated', 'product.deleted'], true)) {
            Log::debug("Игнорируем неизвестное событие продукта: {$eventType}");

            return;
        }

        $data = $body['data'] ?? null;
        if (! is_array($data)) {
            Log::warning('Kafka: product event без data', ['body' => $body]);

            return;
        }

        $id = $data['id'] ?? null;
        if ($id === null) {
            Log::warning('Kafka: product event без id', ['data' => $data]);

            return;
        }

        if ($eventType === 'product.deleted') {
            $this->index->deleteProduct((int) $id);
            Log::info('Kafka: product removed from OpenSearch', ['product_id' => (int) $id]);

            return;
        }

        if (($data['name'] ?? null) === null || ($data['store_id'] ?? null) === null) {
            Log::warning('Kafka: product event с неполными полями', ['data' => $data]);

            return;
        }

        $this->index->indexProduct($data, preserveStock: $eventType === 'product.updated');

        Log::info('Kafka: product indexed in OpenSearch', [
            'event' => $eventType,
            'product_id' => (int) $id,
        ]);
    }
}
