<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductSnapshot;
use App\Services\Stripe\StripeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_pay_creates_payment_and_returns_checkout_url(): void
    {
        $order = $this->createAwaitingPaymentOrder();

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
                'payment_intent' => 'pi_test_123',
                'status' => 'open',
            ]);
        $this->app->instance(StripeClient::class, $stripe);

        $this->postJson('/api/orders/'.$order->id.'/pay', ['user_id' => 7])
            ->assertOk()
            ->assertJson(['checkout_url' => 'https://checkout.stripe.com/c/pay/cs_test_123']);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);
    }

    public function test_pay_rejects_pending_before_stock_reserved(): void
    {
        $order = $this->createAwaitingPaymentOrder();
        $order->status = 'pending';
        $order->save();

        $stripe = Mockery::mock(StripeClient::class);
        $stripe->shouldNotReceive('createCheckoutSession');
        $this->app->instance(StripeClient::class, $stripe);

        $this->postJson('/api/orders/'.$order->id.'/pay', ['user_id' => 7])->assertStatus(422);
    }

    private function createAwaitingPaymentOrder(): Order
    {
        ProductSnapshot::query()->create([
            'id' => 42,
            'name' => 'Phone',
            'price' => '199.99',
            'store_id' => 1,
        ]);

        $order = Order::query()->create([
            'user_id' => 7,
            'status' => 'awaiting_payment',
            'total_amount' => '199.99',
        ]);

        $order->items()->create([
            'product_id' => 42,
            'quantity' => 1,
            'unit_price' => '199.99',
        ]);

        return $order->fresh('items');
    }
}
