<?php

namespace App\Kafka\Handlers;

use App\Models\OutboxEvent;
use App\Models\Stock;
use App\Services\SagaStepGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class StockReleaseHandler
{
    public function __construct(
        private SagaStepGuard $saga,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $payload = $message->getBody();
        if (! is_array($payload)) {
            Log::warning('Получено невалидное событие stock.release_requested', ['payload' => $payload]);

            return;
        }

        $orderId = $payload['order_id'] ?? null;
        $items = $payload['items'] ?? [];
        $sagaId = $this->sagaIdFrom($payload);

        if (! $orderId || empty($items)) {
            Log::warning('Получено невалидное событие stock.release_requested', ['payload' => $payload]);

            return;
        }

        if ($sagaId !== null) {
            $decision = $this->saga->tryBegin($sagaId, 'stock.release');
            if ($decision !== 'run') {
                Log::info('Saga step stock.release skipped', [
                    'saga_id' => $sagaId,
                    'decision' => $decision,
                ]);

                return;
            }
        }

        try {
            DB::beginTransaction();

            $stockChangedEvents = [];

            foreach ($items as $item) {
                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('store_id', $item['store_id'])
                    ->lockForUpdate()
                    ->first();

                if ($stock) {
                    $stock->quantity += $item['quantity'];
                    $stock->save();

                    $stockChangedEvents[] = [
                        'product_id' => $stock->product_id,
                        'store_id' => $stock->store_id,
                        'quantity' => $stock->quantity,
                    ];
                }
            }

            foreach ($stockChangedEvents as $changedEvent) {
                OutboxEvent::create([
                    'topic' => 'catalog.events',
                    'event_type' => 'stock.changed',
                    'payload' => $changedEvent,
                ]);
            }

            DB::commit();

            if ($sagaId !== null) {
                $this->saga->put(
                    $sagaId,
                    'stock.release',
                    SagaStepGuard::STATUS_DONE,
                    ['order_id' => $orderId]
                );
            }

            Log::info("Резерв снят: товар для заказа #{$orderId} возвращен на склад.");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Ошибка при освобождении остатка для заказа #{$orderId}:", [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } finally {
            if ($sagaId !== null) {
                $this->saga->release($sagaId, 'stock.release');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sagaIdFrom(array $payload): ?string
    {
        $sagaId = $payload['saga_id'] ?? null;

        return is_string($sagaId) && $sagaId !== '' ? $sagaId : null;
    }
}
