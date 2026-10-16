<?php

namespace App\Kafka\Handlers;

use App\Services\Catalog\ProductIndexer;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class StockEventsHandler
{
    public function __construct(
        private readonly ProductIndexer $indexer,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders();
        $body = $message->getBody();
        if (! is_array($body)) {
            $body = [];
        }

        $eventType = $headers['event-type'] ?? ($body['event'] ?? null);
        if ($eventType !== 'stock.changed') {
            Log::debug("Recommendation: ignore catalog.events {$eventType}");

            return;
        }

        $productId = $body['product_id'] ?? null;
        $quantity = $body['quantity'] ?? null;
        if ($productId === null || $quantity === null) {
            Log::warning('Recommendation: stock.changed incomplete', ['body' => $body]);

            return;
        }

        $inStock = ((int) $quantity) > 0;
        $this->indexer->setInStock((int) $productId, $inStock);

        Log::info('Recommendation: stock updated', [
            'product_id' => (int) $productId,
            'quantity' => (int) $quantity,
            'in_stock' => $inStock,
        ]);
    }
}
