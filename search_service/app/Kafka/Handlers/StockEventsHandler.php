<?php

namespace App\Kafka\Handlers;

use App\Services\SearchIndexService;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class StockEventsHandler
{
    public function __construct(
        private readonly SearchIndexService $index,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders();
        $body = $message->getBody();
        $eventType = $headers['event-type'] ?? ($body['event'] ?? null);

        if ($eventType !== 'stock.changed') {
            Log::debug("Игнорируем событие catalog.events: {$eventType}");

            return;
        }

        $productId = $body['product_id'] ?? null;
        $quantity = $body['quantity'] ?? null;

        if ($productId === null || $quantity === null) {
            Log::warning('Kafka: stock.changed с неполными полями', ['body' => $body]);

            return;
        }

        $this->index->updateProductStock((int) $productId, (int) $quantity);

        Log::info('Kafka: product stock updated in OpenSearch', [
            'product_id' => (int) $productId,
            'quantity' => (int) $quantity,
            'in_stock' => ((int) $quantity) > 0,
        ]);
    }
}
