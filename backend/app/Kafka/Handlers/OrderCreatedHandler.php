<?php

namespace App\Kafka\Handlers;

use App\Models\Stock; 
use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class OrderCreatedHandler
{
    public function __invoke(ConsumerMessage $message): void
    {
        // 1. Извлекаем заголовки и проверяем тип события
        $headers = $message->getHeaders();
        $eventType = $headers['event-type'] ?? null;

        // 2. Если сообщение предназначено не для обработки создания заказа — игнорируем его
        if ($eventType !== 'order.created') {
            return;
        }

        Log::info("🚨 КОНСЬЮМЕР СРАБОТАЛ! Получено сообщение order.created:", ['body' => $message->getBody()]);
        $payload = $message->getBody();

        $orderId = $payload['order_id'] ?? null;
        $items = $payload['items'] ?? [];

        if (!$orderId || empty($items)) {
            Log::warning("Получено пустое или невалидное событие order.created", ['payload' => $payload]);
            return;
        }

        try {
            DB::beginTransaction();

            $allAvailable = true;
            $stockChangedEvents = [];

            foreach ($items as $item) {
                // Пессимистичная блокировка
                $stock = Stock::where('product_id', $item['product_id'])
                              ->where('store_id', $item['store_id'])
                              ->lockForUpdate() 
                              ->first();

                // Проверяем наличие нужного количества
                if (!$stock || $stock->quantity < $item['quantity']) {
                    $allAvailable = false;
                    break;
                }

                // Уменьшение остатка
                $stock->quantity -= $item['quantity'];
                $stock->save();

                // Собираем данные об изменении остатка
                $stockChangedEvents[] = [
                    'product_id' => $stock->product_id,
                    'store_id'   => $stock->store_id,
                    'quantity'   => $stock->quantity,
                ];
            }

            if ($allAvailable) {
                // УСПЕХ: Отправляем ответ в топик stock.events
                OutboxEvent::create([
                    'topic'      => 'stock.events',  // Этот топик для order service
                    'event_type' => 'stock.reserved',
                    'payload'    => [
                        'order_id' => $orderId,
                        'status'   => 'success'
                    ]
                ]);

                Log::info("✅ УСПЕХ: Товар для заказа #{$orderId} зарезервирован. Событие отправлено в Outbox.");

                // События изменений остатков для аналитики и поиска
                foreach ($stockChangedEvents as $changedEvent) {
                    OutboxEvent::create([
                        'topic'      => 'catalog.events', //Этот топик для recomendation service
                        'event_type' => 'stock.changed',
                        'payload'    => $changedEvent
                    ]);
                }
            } else {
                DB::rollBack(); 
                
                // ОТКАЗ: Отправляем ответ в топик stock.events
                DB::beginTransaction();
                OutboxEvent::create([
                    'topic'      => 'stock.events',
                    'event_type' => 'stock.reservation_failed',
                    'payload'    => [
                        'order_id' => $orderId,
                        'reason'   => 'Not enough stock'
                    ]
                ]);
                Log::warning("⚠️ ОТКАЗ: Недостаточно товара на складе для заказа #{$orderId}. Событие reservation_failed отправлено в Outbox.");
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("🔥 ФАТАЛЬНАЯ ОШИБКА внутри консьюмера:", [
                'order_id' => $orderId,
                'message'  => $e->getMessage(),
                'line'     => $e->getLine(),
                'file'     => $e->getFile()
            ]);
        }
    }
}