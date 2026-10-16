<?php

namespace Tests\Feature;

use App\Kafka\Handlers\OrderEventsHandler;
use App\Mail\OrderCancelledMail;
use App\Mail\OrderConfirmedMail;
use App\Mail\OrderPaidMail;
use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderEventsHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EmailLog::truncate();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_handler_sends_order_paid_mail(): void
    {
        Mail::fake();

        $body = $this->orderBody();
        app(OrderEventsHandler::class)($this->message('order.paid', $body));

        Mail::assertSent(OrderPaidMail::class, function (OrderPaidMail $mail) use ($body) {
            return $mail->hasTo($body['email'])
                && ($mail->payload['order_id'] ?? null) === $body['order_id'];
        });

        $log = EmailLog::where('email', $body['email'])->first();
        $this->assertNotNull($log);
        $this->assertSame('sent', $log->status);
        $this->assertSame('order.paid', $log->event_type);
        $this->assertSame('order.paid', $log->payload['event_type'] ?? null);
    }

    public function test_handler_sends_order_confirmed_mail(): void
    {
        Mail::fake();

        $body = $this->orderBody();
        app(OrderEventsHandler::class)($this->message('order.confirmed', $body));

        Mail::assertSent(OrderConfirmedMail::class);
        $log = EmailLog::where('status', 'sent')->first();
        $this->assertNotNull($log);
        $this->assertSame('order.confirmed', $log->event_type);
        $this->assertSame('order.confirmed', $log->payload['event_type'] ?? null);
    }

    public function test_handler_sends_order_cancelled_mail(): void
    {
        Mail::fake();

        $body = $this->orderBody(['reason' => 'insufficient_stock']);
        app(OrderEventsHandler::class)($this->message('order.cancelled', $body));

        Mail::assertSent(OrderCancelledMail::class, function (OrderCancelledMail $mail) {
            return ($mail->payload['reason'] ?? null) === 'insufficient_stock';
        });
    }

    public function test_handler_uses_event_type_from_body_when_header_missing(): void
    {
        Mail::fake();

        $body = $this->orderBody(['event_type' => 'order.paid']);
        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getHeaders')->andReturn([]);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('getKey')->andReturn('42');

        app(OrderEventsHandler::class)($message);

        Mail::assertSent(OrderPaidMail::class);
        $log = EmailLog::where('email', $body['email'])->first();
        $this->assertNotNull($log);
        $this->assertSame('order.paid', $log->event_type);
    }

    public function test_handler_ignores_unknown_event_types(): void
    {
        Mail::fake();
        Kafka::fake();

        app(OrderEventsHandler::class)($this->message('order.created', $this->orderBody()));

        Mail::assertNothingSent();
        $this->assertSame(0, EmailLog::count());
        Kafka::assertNothingPublished();
    }

    public function test_handler_skips_messages_without_email(): void
    {
        Mail::fake();
        Kafka::fake();

        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getHeaders')->andReturn(['event-type' => 'order.paid']);
        $message->shouldReceive('getBody')->andReturn([
            'order_id' => 1,
            'name' => 'No Email',
        ]);

        app(OrderEventsHandler::class)($message);

        Mail::assertNothingSent();
        $this->assertSame(0, EmailLog::count());
        Kafka::assertNothingPublished();
    }

    public function test_handler_marks_failed_and_publishes_to_dlq_after_retries_exhausted(): void
    {
        Kafka::fake();

        $maxRetries = NotificationService::MAX_RETRIES;
        $pendingMailMock = Mockery::mock(PendingMail::class);
        $pendingMailMock->shouldReceive('send')
            ->times($maxRetries)
            ->andThrow(new \Exception('SMTP permanently down'));

        Mail::shouldReceive('to')
            ->times($maxRetries)
            ->with('dlq-order@example.com')
            ->andReturn($pendingMailMock);

        /** @var NotificationService&MockInterface $service */
        $service = Mockery::mock(NotificationService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('sleepInTest')->times($maxRetries - 1);
        $this->app->instance(NotificationService::class, $service);

        $body = $this->orderBody(['email' => 'dlq-order@example.com']);
        $message = $this->message('order.paid', $body, '99');

        app(OrderEventsHandler::class)($message);

        $log = EmailLog::where('email', 'dlq-order@example.com')->first();
        $this->assertNotNull($log);
        $this->assertSame('failed', $log->status);
        $this->assertTrue((bool) $log->moved_to_dlq);
        $this->assertSame($maxRetries, $log->retries);
        $this->assertSame('order.paid', $log->event_type);
        $this->assertSame('order.paid', $log->payload['event_type'] ?? null);
        $this->assertStringContainsString('SMTP permanently down', (string) $log->error_message);

        Kafka::assertPublishedOn('order.events.dlq');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function orderBody(array $overrides = []): array
    {
        return array_merge([
            'order_id' => 42,
            'user_id' => 7,
            'email' => 'buyer@example.com',
            'name' => 'Buyer',
            'status' => 'confirmed',
            'total_amount' => '50.00',
            'items' => [],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function message(string $eventType, array $body, string $key = '42'): ConsumerMessage
    {
        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getHeaders')->andReturn(['event-type' => $eventType]);
        $message->shouldReceive('getBody')->andReturn($body);
        $message->shouldReceive('getKey')->andReturn($key);

        return $message;
    }
}
