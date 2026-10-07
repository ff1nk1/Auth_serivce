<?php

namespace App\Kafka\Handlers;

use App\Models\Stock;
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class StockReleaseHandler
{
    public function __invoke(ConsumerMessage $message): void
    {
        $payload = $message->getBody();
        $orderId = $payload['order_id'] ?? null;
        $items = $payload['items'] ?? [];

        if (!$orderId || empty($items)) {
            Log::warning("Получено невалидное событие stock.release_requested", ['payload' => $payload]);
            return;
        }

        try {
            DB::beginTransaction();

            $stockChangedEvents = [];

            foreach ($items as $item) {
                // Пессимистичная блокировка записи
                $stock = Stock::where('product_id', $item['product_id'])
                              ->where('store_id', $item['store_id'])
                              ->lockForUpdate()
                              ->first();

                if ($stock) {
                    // КОМПЕНСАЦИЯ: Возвращаем зарезервированный товар обратно на склад
                    $stock->quantity += $item['quantity'];
                    $stock->save();

                    // Фиксируем изменение остатка для аналитики и поиска
                    $stockChangedEvents[] = [
                        'product_id' => $stock->product_id,
                        'store_id'   => $stock->store_id,
                        'quantity'   => $stock->quantity,
                    ];
                }
            }

            // Отправляем события изменения остатков в Outbox для Search/Analytics
            foreach ($stockChangedEvents as $changedEvent) {
                OutboxEvent::create([
                    'topic'      => 'catalog.events',
                    'event_type' => 'stock.changed',
                    'payload'    => $changedEvent
                ]);
            }

            DB::commit();

            Log::info("🔄 РЕЗЕРВ СНЯТ: Товар для заказа #{$orderId} возвращен на склад.");

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("🔥 Ошибка при освобождении остатка для заказа #{$orderId}:", [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine()
            ]);
        }
    }
}