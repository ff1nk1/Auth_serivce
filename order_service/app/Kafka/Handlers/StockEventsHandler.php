<?php

namespace App\Kafka\Handlers;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\OutboxWriter;
use App\Services\SagaStepGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Contracts\ConsumerMessage;

class StockEventsHandler
{
    public function __construct(
        private OutboxWriter $outbox,
        private SagaStepGuard $saga,
        private OrderService $orders,
    ) {}

    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders();
        $eventType = $headers['event-type'] ?? null;
        $payload = $message->getBody();

        if (! is_array($payload)) {
            Log::warning('Kafka: stock event with non-array body');

            return;
        }

        match ($eventType) {
            'stock.reserved' => $this->onReserved($payload),
            'stock.reservation_failed' => $this->onReservationFailed($payload),
            default => Log::debug("Игнорируем неизвестное событие stock.events: {$eventType}"),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onReserved(array $payload): void
    {
        $orderId = $payload['order_id'] ?? null;
        if (! is_numeric($orderId)) {
            return;
        }

        $sagaId = $this->sagaIdFrom($payload);
        if ($sagaId !== null) {
            $decision = $this->saga->tryBegin($sagaId, 'stock.reserved');
            if ($decision !== 'run') {
                Log::info('Saga step stock.reserved skipped', [
                    'saga_id' => $sagaId,
                    'decision' => $decision,
                ]);

                return;
            }
        }

        try {
            DB::transaction(function () use ($orderId) {
                $order = Order::query()->lockForUpdate()->find((int) $orderId);
                if ($order === null || $order->status !== 'pending') {
                    return;
                }

                $order->status = 'awaiting_payment';
                $order->save();
            });

            if ($sagaId !== null) {
                $this->saga->put(
                    $sagaId,
                    'stock.reserved',
                    SagaStepGuard::STATUS_DONE,
                    ['order_id' => (int) $orderId]
                );
            }
        } finally {
            if ($sagaId !== null) {
                $this->saga->release($sagaId, 'stock.reserved');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function onReservationFailed(array $payload): void
    {
        $orderId = $payload['order_id'] ?? null;
        if (! is_numeric($orderId)) {
            return;
        }

        $sagaId = $this->sagaIdFrom($payload);
        if ($sagaId !== null) {
            $decision = $this->saga->tryBegin($sagaId, 'stock.reservation_failed');
            if ($decision !== 'run') {
                Log::info('Saga step stock.reservation_failed skipped', [
                    'saga_id' => $sagaId,
                    'decision' => $decision,
                ]);

                return;
            }
        }

        try {
            DB::transaction(function () use ($orderId, $sagaId) {
                $order = Order::query()->lockForUpdate()->find((int) $orderId);
                if ($order === null || $order->status !== 'pending') {
                    return;
                }

                $order->status = 'cancelled';
                $order->save();

                $this->outbox->write(
                    'order.events',
                    'order.cancelled',
                    $this->orders->notificationPayload($order, ['reason' => 'insufficient_stock'])
                );
            });

            if ($sagaId !== null) {
                $this->saga->put(
                    $sagaId,
                    'stock.reservation_failed',
                    SagaStepGuard::STATUS_FAILED,
                    ['order_id' => (int) $orderId]
                );
            }
        } finally {
            if ($sagaId !== null) {
                $this->saga->release($sagaId, 'stock.reservation_failed');
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
