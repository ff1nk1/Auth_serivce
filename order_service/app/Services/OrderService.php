<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderService
{
    public function __construct(
        private IdempotencyService $idempotency
    ) {}

    public function create(int $userId, array $items, string $idempotencyKey): array
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

            $order = $this->persistOrder($userId, $items);
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
        $order = $this->findForUser($orderId, $userId);

        if (in_array($order->status, ['cancelled', 'paid'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Order cannot be cancelled in status: '.$order->status],
            ]);
        }

        $order->status = 'cancelled';
        $order->save();

        return $order->load(['items.productSnapshot', 'payments']);
    }

    public function serializeOrder(Order $order): array
    {
        $order->loadMissing(['items.productSnapshot', 'payments']);

        return $order->toArray();
    }

    /**
     * @param  list<array{product_id: int, quantity: int}>  $items
     */
    private function persistOrder(int $userId, array $items): Order
    {
        return DB::transaction(function () use ($userId, $items) {
            $lineItems = [];
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
                $lineTotal = bcmul($unitPrice, (string) $quantity, 2);
                $total = bcadd($total, $lineTotal, 2);

                $lineItems[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
            }

            $order = Order::query()->create([
                'user_id' => $userId,
                'status' => 'pending',
                'total_amount' => $total,
            ]);

            foreach ($lineItems as $line) {
                $order->items()->create($line);
            }

            return $order->load(['items.productSnapshot', 'payments']);
        });
    }
}
