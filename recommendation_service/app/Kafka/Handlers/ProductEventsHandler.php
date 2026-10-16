<?php

namespace App\Kafka\Handlers;

use App\Models\ProductCatalog;
use App\Services\Catalog\ProductIndexer;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class ProductEventsHandler
{
    public const ALLOWED_TYPES = [
        'product.created',
        'product.updated',
        'product.deleted',
        'product.published',
        'product.unpublished',
    ];

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
        if (! in_array($eventType, self::ALLOWED_TYPES, true)) {
            Log::debug("Recommendation: ignore product event {$eventType}");

            return;
        }

        $data = $body['data'] ?? null;
        if (! is_array($data)) {
            Log::warning('Recommendation: product event without data', ['body' => $body]);

            return;
        }

        $id = $data['id'] ?? $data['product_id'] ?? null;
        if ($id === null) {
            Log::warning('Recommendation: product event without id', ['data' => $data]);

            return;
        }

        $productId = (int) $id;

        match ($eventType) {
            'product.deleted' => $this->indexer->remove($productId),
            'product.published' => $this->handlePublished($data, $productId),
            'product.unpublished' => $this->indexer->setPublished($productId, false),
            default => $this->indexer->upsertFromEvent($data, reembed: true),
        };

        Log::info('Recommendation: product event handled', [
            'event' => $eventType,
            'product_id' => $productId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handlePublished(array $data, int $productId): void
    {
        $data['is_published'] = true;
        $existing = ProductCatalog::query()->where('product_id', $productId)->exists();
        if ($existing && ($data['name'] ?? null) === null) {
            $this->indexer->setPublished($productId, true);

            return;
        }

        $this->indexer->upsertFromEvent($data, reembed: true);
    }
}
