<?php

namespace App\Kafka\Handlers;

use App\Events\StockChanged;
use App\Events\StockReservationFailed;
use App\Events\StockReserved;
use App\Models\Stock;
use App\Services\SagaStepGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class OrderCreatedHandler
{
    public function __construct(
        private SagaStepGuard $saga,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders();
        $eventType = $headers['event-type'] ?? null;

        if ($eventType !== 'order.created') {
            return;
        }

        Log::info('Получено сообщение order.created:', ['body' => $message->getBody()]);
        $payload = $message->getBody();

        if (! is_array($payload)) {
            Log::warning('Получено невалидное событие order.created', ['payload' => $payload]);

            return;
        }

        $orderId = $payload['order_id'] ?? null;
        $items = $payload['items'] ?? [];
        $sagaId = $this->sagaIdFrom($payload);

        if (! $orderId || empty($items)) {
            Log::warning('Получено пустое или невалидное событие order.created', ['payload' => $payload]);

            return;
        }

        if ($sagaId !== null) {
            $decision = $this->saga->tryBegin($sagaId, 'stock.reserve');
            if ($decision !== 'run') {
                Log::info('Saga step stock.reserve skipped', [
                    'saga_id' => $sagaId,
                    'decision' => $decision,
                ]);

                return;
            }
        }

        $stepStatus = SagaStepGuard::STATUS_DONE;
        $stepResult = ['order_id' => $orderId, 'outcome' => 'reserved'];

        try {
            DB::beginTransaction();

            $allAvailable = true;
            $stockChangedEvents = [];

            foreach ($items as $item) {
                $stock = Stock::where('product_id', $item['product_id'])
                    ->where('store_id', $item['store_id'])
                    ->lockForUpdate()
                    ->first();

                if (! $stock || $stock->quantity < $item['quantity']) {
                    $allAvailable = false;
                    break;
                }

                $stock->quantity -= $item['quantity'];
                $stock->save();

                $stockChangedEvents[] = [
                    'product_id' => $stock->product_id,
                    'store_id' => $stock->store_id,
                    'quantity' => $stock->quantity,
                ];
            }

            if ($allAvailable) {
                $reservedPayload = [
                    'order_id' => $orderId,
                    'status' => 'success',
                ];
                if ($sagaId !== null) {
                    $reservedPayload['saga_id'] = $sagaId;
                }

                event(new StockReserved($reservedPayload));

                Log::info("Товар для заказа #{$orderId} зарезервирован. Событие отправлено в Outbox.");

                foreach ($stockChangedEvents as $changedEvent) {
                    event(new StockChanged($changedEvent));
                }
            } else {
                DB::rollBack();

                DB::beginTransaction();
                $failedPayload = [
                    'order_id' => $orderId,
                    'reason' => 'Not enough stock',
                ];
                if ($sagaId !== null) {
                    $failedPayload['saga_id'] = $sagaId;
                }

                event(new StockReservationFailed($failedPayload));
                Log::warning("Недостаточно товара на складе для заказа #{$orderId}. Событие reservation_failed отправлено в Outbox.");

                $stepStatus = SagaStepGuard::STATUS_FAILED;
                $stepResult = ['order_id' => $orderId, 'outcome' => 'reservation_failed'];
            }

            DB::commit();

            if ($sagaId !== null) {
                $this->saga->put($sagaId, 'stock.reserve', $stepStatus, $stepResult);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Фатальная ошибка внутри консьюмера order.created:', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
            ]);
            // Rethrow so the consumer does not auto-commit a failed message
            // (otherwise stock.* is never written and the order stays pending).
            throw $e;
        } finally {
            if ($sagaId !== null) {
                $this->saga->release($sagaId, 'stock.reserve');
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
