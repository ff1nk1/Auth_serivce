<?php

namespace App\Services;

use App\Events\OrderConfirmed;
use App\Events\OrderPaid;
use App\Events\StockReleaseRequested;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Stripe\StripeClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentService
{
    public function __construct(
        private OrderService $orders,
        private StripeClient $stripe,
        private SagaStepGuard $saga,
    ) {}

    /**
     * @return array{checkout_url: string, payment_id: int}
     */
    public function createCheckoutForUser(int $orderId, int $userId): array
    {
        $order = $this->orders->findForUser($orderId, $userId);
        $order->loadMissing(['items.productSnapshot']);

        if ($order->status !== 'awaiting_payment') {
            throw ValidationException::withMessages([
                'status' => ['Order cannot be paid in status: '.$order->status],
            ]);
        }

        $existing = Payment::query()
            ->where('order_id', $order->id)
            ->where('status', 'pending')
            ->whereNotNull('stripe_checkout_session_id')
            ->latest('id')
            ->first();

        if ($existing !== null) {
            try {
                $session = $this->stripe->retrieveCheckoutSession($existing->stripe_checkout_session_id);
                if (($session['status'] ?? null) === 'open' && ! empty($session['url'])) {
                    return [
                        'checkout_url' => (string) $session['url'],
                        'payment_id' => (int) $existing->id,
                    ];
                }
            } catch (RuntimeException) {
                // Session unusable — create a new one below.
            }

            $existing->status = 'failed';
            $existing->save();
        }

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'amount' => $order->total_amount,
            'status' => 'pending',
        ]);

        $lineItems = [];
        foreach ($order->items as $item) {
            $name = $item->productSnapshot?->name ?? ('Product #'.$item->product_id);
            $unitAmount = $this->toMinorUnits((string) $item->unit_price);

            $lineItems[] = [
                'quantity' => (int) $item->quantity,
                'price_data' => [
                    'currency' => 'rub',
                    'unit_amount' => $unitAmount,
                    'product_data' => [
                        'name' => $name,
                    ],
                ],
            ];
        }

        if ($lineItems === []) {
            throw ValidationException::withMessages([
                'items' => ['Order has no items to pay for.'],
            ]);
        }

        $successUrl = str_replace(
            '{ORDER_ID}',
            (string) $order->id,
            (string) config('services.stripe.success_url')
        );
        $cancelUrl = str_replace(
            '{ORDER_ID}',
            (string) $order->id,
            (string) config('services.stripe.cancel_url')
        );

        $session = $this->stripe->createCheckoutSession([
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $order->id,
            'metadata' => [
                'order_id' => (string) $order->id,
                'payment_id' => (string) $payment->id,
            ],
            'expires_at' => time() + 30 * 60,
            'line_items' => $lineItems,
        ]);

        $payment->stripe_checkout_session_id = $session['id'] ?? null;
        $payment->stripe_payment_intent_id = is_string($session['payment_intent'] ?? null)
            ? $session['payment_intent']
            : null;
        $payment->save();

        $url = $session['url'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Stripe Checkout Session did not return a URL.');
        }

        return [
            'checkout_url' => $url,
            'payment_id' => (int) $payment->id,
        ];
    }

    public function handleWebhookEvent(array $event): void
    {
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? null;

        if (! is_array($object)) {
            return;
        }

        match ($type) {
            'checkout.session.completed' => $this->markConfirmedFromSession($object),
            'checkout.session.expired' => $this->markPaymentFailedFromSession($object),
            default => null,
        };
    }

    private function markConfirmedFromSession(array $session): void
    {
        $payment = $this->findPaymentForSession($session);
        if ($payment === null) {
            return;
        }

        DB::transaction(function () use ($payment, $session) {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);
            if ($payment === null || $payment->status === 'succeeded') {
                return;
            }

            $payment->status = 'succeeded';
            if (is_string($session['payment_intent'] ?? null)) {
                $payment->stripe_payment_intent_id = $session['payment_intent'];
            }
            $payment->save();

            $order = Order::query()->lockForUpdate()->find($payment->order_id);
            if ($order === null || $order->status === 'confirmed') {
                return;
            }

            if (in_array($order->status, ['awaiting_payment', 'pending'], true)) {
                $order->status = 'confirmed';
                $order->save();

                $payload = $this->orders->notificationPayload($order);
                event(new OrderPaid($payload));
                event(new OrderConfirmed($payload));
            }
        });
    }

    private function markPaymentFailedFromSession(array $session): void
    {
        $payment = $this->findPaymentForSession($session);
        if ($payment === null || $payment->status !== 'pending') {
            return;
        }

        $releaseSagaId = null;
        $releaseOrderId = null;

        DB::transaction(function () use ($payment, &$releaseSagaId, &$releaseOrderId) {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);
            if ($payment === null || $payment->status !== 'pending') {
                return;
            }

            $payment->status = 'failed';
            $payment->save();

            $order = Order::query()->lockForUpdate()->find($payment->order_id);
            if ($order === null || $order->status !== 'awaiting_payment') {
                return;
            }

            $order->status = 'payment_failed';
            $order->save();

            $releasePayload = [
                'order_id' => $order->id,
                'items' => $this->orders->itemsPayload($order),
            ];
            if (is_string($order->saga_id) && $order->saga_id !== '') {
                $releasePayload['saga_id'] = $order->saga_id;
                $releaseSagaId = $order->saga_id;
                $releaseOrderId = $order->id;
            }

            event(new StockReleaseRequested($releasePayload));
        });

        if ($releaseSagaId !== null && $releaseOrderId !== null) {
            $this->saga->put(
                $releaseSagaId,
                'stock.release_requested',
                SagaStepGuard::STATUS_DONE,
                ['order_id' => $releaseOrderId]
            );
        }
    }

    private function findPaymentForSession(array $session): ?Payment
    {
        $sessionId = $session['id'] ?? null;
        $paymentId = $session['metadata']['payment_id'] ?? null;

        if (is_numeric($paymentId)) {
            $payment = Payment::query()->find((int) $paymentId);
            if ($payment !== null) {
                return $payment;
            }
        }

        if (is_string($sessionId) && $sessionId !== '') {
            return Payment::query()
                ->where('stripe_checkout_session_id', $sessionId)
                ->first();
        }

        return null;
    }

    //
    private function toMinorUnits(string $amount): int
    {
        if (function_exists('bcmul')) {
            return (int) bcmul($amount, '100', 0);
        }

        $normalized = number_format((float) $amount, 2, '.', '');

        return (int) str_replace('.', '', $normalized);
    }
}
