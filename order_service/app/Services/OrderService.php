<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderService
{
    public function __construct(
        private IdempotencyService $idempotency,
        private OutboxWriter $outbox,
        private SagaStepGuard $saga,
    ) {}

    public function create(int $userId, string $email, string $name, array $items, string $idempotencyKey): array
    {
        $cached = $this->idempotency->get($idempotencyKey);
        if ($cached !== null) {
            return $cached;
        }

        if (! $this->idempotency->acquireLock($idempotencyKey)) {
            usleep(200_000);
            $cached = $this->idempotency->get($idempotencyKey);
            if ($cached !== null) {
                return $cached;
            }

            throw new ConflictHttpException('Order creation already in progress for this idempotency key.');
        }

        try {
            $cached = $this->idempotency->get($idempotencyKey);
            if ($cached !== null) {
                return $cached;
            }

            $order = $this->persistOrder($userId, $email, $name, $items);
            $this->saga->put(
                (string) $order->saga_id,
                'order.created',
                SagaStepGuard::STATUS_DONE,
                ['order_id' => $order->id]
            );
            $body = $this->serializeOrder($order);
            $result = ['status' => 201, 'body' => $body];
            $this->idempotency->put($idempotencyKey, 201, $body);

            return $result;
        } finally {
            $this->idempotency->releaseLock($idempotencyKey);
        }
    }

    public function listForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->with('items')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function listAll(int $perPage = 15): LengthAwarePaginator
    {
        return Order::query()
            ->with('items')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function findForUser(int $orderId, int $userId): Order
    {
        $order = Order::query()
            ->with(['items.productSnapshot', 'payments'])
            ->where('id', $orderId)
            ->where('user_id', $userId)
            ->first();

        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        return $order;
    }

    public function findAny(int $orderId): Order
    {
        $order = Order::query()
            ->with(['items.productSnapshot', 'payments'])
            ->find($orderId);

        if ($order === null) {
            throw new NotFoundHttpException('Order not found.');
        }

        return $order;
    }

    public function cancelForUser(int $orderId, int $userId): Order
    {
        $wroteRelease = false;

        $order = DB::transaction(function () use ($orderId, $userId, &$wroteRelease) {
            $order = Order::query()
                ->with(['items.productSnapshot', 'payments'])
                ->where('id', $orderId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw new NotFoundHttpException('Order not found.');
            }

            if (in_array($order->status, ['cancelled', 'confirmed', 'shipped', 'payment_failed'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Order cannot be cancelled in status: '.$order->status],
                ]);
            }

            $previousStatus = $order->status;
            $order->status = 'cancelled';
            $order->save();

            $this->outbox->write(
                'order.events',
                'order.cancelled',
                $this->notificationPayload($order, ['reason' => 'user_cancelled'])
            );

            if ($previousStatus === 'awaiting_payment') {
                $releasePayload = [
                    'order_id' => $order->id,
                    'items' => $this->itemsPayload($order),
                ];
                if (is_string($order->saga_id) && $order->saga_id !== '') {
                    $releasePayload['saga_id'] = $order->saga_id;
                }

                $this->outbox->write('order.events', 'stock.release_requested', $releasePayload);
                $wroteRelease = true;
            }

            return $order->load(['items.productSnapshot', 'payments']);
        });

        if ($wroteRelease && is_string($order->saga_id) && $order->saga_id !== '') {
            $this->saga->put(
                $order->saga_id,
                'stock.release_requested',
                SagaStepGuard::STATUS_DONE,
                ['order_id' => $order->id]
            );
        }

        return $order;
    }

    public function serializeOrder(Order $order): array
    {
        $order->loadMissing(['items.productSnapshot', 'payments']);

        return $order->toArray();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function notificationPayload(Order $order, array $extra = []): array
    {
        $order->loadMissing('items.productSnapshot');

        $payload = [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'email' => $order->customer_email,
            'name' => $order->customer_name,
            'status' => $order->status,
            'total_amount' => (string) $order->total_amount,
            'items' => $this->notificationItems($order),
        ];

        if (is_string($order->saga_id) && $order->saga_id !== '') {
            $payload['saga_id'] = $order->saga_id;
        }

        return array_merge($payload, $extra);
    }

    /**
     * @return list<array{product_id: int, store_id: int, quantity: int}>
     */
    public function itemsPayload(Order $order): array
    {
        $order->loadMissing('items.productSnapshot');

        $items = [];
        foreach ($order->items as $item) {
            $storeId = $item->productSnapshot?->store_id;
            if ($storeId === null) {
                continue;
            }

            $items[] = [
                'product_id' => (int) $item->product_id,
                'store_id' => (int) $storeId,
                'quantity' => (int) $item->quantity,
            ];
        }

        return $items;
    }

    /**
     * @return list<array{product_id: int, name: string|null, quantity: int, unit_price: string}>
     */
    private function notificationItems(Order $order): array
    {
        $items = [];
        foreach ($order->items as $item) {
            $items[] = [
                'product_id' => (int) $item->product_id,
                'name' => $item->productSnapshot?->name,
                'quantity' => (int) $item->quantity,
                'unit_price' => (string) $item->unit_price,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    private function persistOrder(int $userId, string $email, string $name, array $items): Order
    {
        return DB::transaction(function () use ($userId, $email, $name, $items) {
            $lineItems = [];
            $outboxItems = [];
            $total = '0.00';

            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $quantity = (int) $item['quantity'];

                $snapshot = ProductSnapshot::query()->find($productId);
                if ($snapshot === null) {
                    throw ValidationException::withMessages([
                        'items' => ["Product snapshot not found for product_id {$productId}"],
                    ]);
                }

                $unitPrice = (string) $snapshot->price;
                $lineTotal = $this->moneyMul($unitPrice, (string) $quantity);
                $total = $this->moneyAdd($total, $lineTotal);

                $lineItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];

                $outboxItems[] = [
                    'product_id' => $productId,
                    'store_id' => (int) $snapshot->store_id,
                    'quantity' => $quantity,
                ];
            }

            $sagaId = (string) Str::uuid();

            $order = Order::query()->create([
                'saga_id' => $sagaId,
                'user_id' => $userId,
                'customer_email' => $email,
                'customer_name' => $name,
                'status' => 'pending',
                'total_amount' => $total,
            ]);

            foreach ($lineItems as $line) {
                $order->items()->create($line);
            }

            $this->outbox->write('order.events', 'order.created', [
                'saga_id' => $sagaId,
                'order_id' => $order->id,
                'items' => $outboxItems,
            ]);

            return $order->load(['items.productSnapshot', 'payments']);
        });
    }

    private function moneyMul(string $a, string $b): string
    {
        if (function_exists('bcmul')) {
            return bcmul($a, $b, 2);
        }

        return number_format((float) $a * (float) $b, 2, '.', '');
    }

    private function moneyAdd(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, 2);
        }

        return number_format((float) $a + (float) $b, 2, '.', '');
    }
}
