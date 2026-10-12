<?php

namespace App\Kafka\Handlers;

use App\Services\SearchIndexService;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class CategoryEventsHandler
{
    public function __construct(
        private readonly SearchIndexService $index,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders();
        $body = $message->getBody();
        $eventType = $headers['event-type'] ?? ($body['event'] ?? null);

        if (! in_array($eventType, ['category.created', 'category.updated', 'category.deleted'], true)) {
            Log::debug("Игнорируем неизвестное событие категории: {$eventType}");

            return;
        }

        $data = $body['data'] ?? null;
        if (! is_array($data)) {
            Log::warning('Kafka: category event без data', ['body' => $body]);

            return;
        }

        $id = $data['id'] ?? null;
        if ($id === null) {
            Log::warning('Kafka: category event без id', ['data' => $data]);

            return;
        }

        if ($eventType === 'category.deleted') {
            $this->index->deleteCategory((int) $id);
            Log::info('Kafka: category removed from OpenSearch', ['category_id' => (int) $id]);

            return;
        }

        if (($data['name'] ?? null) === null) {
            Log::warning('Kafka: category event с неполными полями', ['data' => $data]);

            return;
        }

        $this->index->indexCategory($data);

        if ($eventType === 'category.updated') {
            $this->index->syncCategoryNameOnProducts((int) $id, (string) $data['name']);
        }

        Log::info('Kafka: category indexed in OpenSearch', [
            'event' => $eventType,
            'category_id' => (int) $id,
        ]);
    }
}
