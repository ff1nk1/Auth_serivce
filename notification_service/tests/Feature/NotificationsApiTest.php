<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmedMail;
use App\Mail\OrderPaidMail;
use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class NotificationsApiTest extends TestCase
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

    public function test_show_returns_email_log_from_mongo(): void
    {
        $log = EmailLog::factory()->failed('SMTP down')->create([
            'email' => 'a@example.com',
            'name' => 'A',
            'event_type' => 'order.paid',
            'payload' => ['order_id' => 1, 'email' => 'a@example.com', 'name' => 'A'],
        ]);

        $response = $this->getJson('/api/notifications/'.$log->id);

        $response->assertOk()
            ->assertJsonPath('email', $log->email)
            ->assertJsonPath('status', 'failed')
            ->assertJsonPath('event_type', 'order.paid')
            ->assertJsonPath('error_message', 'SMTP down');
    }

    public function test_resend_failed_order_paid_sends_correct_mailable(): void
    {
        Mail::fake();

        $log = EmailLog::factory()->failed()->create([
            'event_type' => 'order.paid',
            'email' => 'buyer@example.com',
            'payload' => [
                'order_id' => 99,
                'email' => 'buyer@example.com',
                'name' => 'Buyer',
                'items' => [],
            ],
        ]);

        $response = $this->postJson('/api/notifications/'.$log->id.'/resend');

        $response->assertOk()
            ->assertJsonPath('data.status', 'sent');

        Mail::assertSent(OrderPaidMail::class, function (OrderPaidMail $mail) {
            return ($mail->payload['order_id'] ?? null) === 99;
        });
    }

    public function test_resend_rejects_non_failed_status(): void
    {
        Mail::fake();

        $log = EmailLog::factory()->sent()->create();

        $response = $this->postJson('/api/notifications/'.$log->id.'/resend');

        $response->assertStatus(422);
        Mail::assertNothingSent();
    }

    public function test_resend_paid_and_confirmed_send_distinct_mailables(): void
    {
        Mail::fake();

        $sharedPayload = [
            'order_id' => 17,
            'email' => 'buyer@example.com',
            'name' => 'Buyer',
            'items' => [],
        ];

        $paid = EmailLog::factory()->failed()->create([
            'event_type' => 'order.paid',
            'email' => 'buyer@example.com',
            'payload' => array_merge($sharedPayload, ['event_type' => 'order.paid']),
        ]);

        $confirmed = EmailLog::factory()->failed()->create([
            'event_type' => 'order.confirmed',
            'email' => 'buyer@example.com',
            'payload' => array_merge($sharedPayload, ['event_type' => 'order.confirmed']),
        ]);

        $this->postJson('/api/notifications/'.$paid->id.'/resend')->assertOk();
        $this->postJson('/api/notifications/'.$confirmed->id.'/resend')->assertOk();

        Mail::assertSent(OrderPaidMail::class, 1);
        Mail::assertSent(OrderConfirmedMail::class, 1);
    }

    public function test_resend_rejects_order_log_without_event_type(): void
    {
        Mail::fake();

        $log = EmailLog::factory()->failed()->create([
            'event_type' => null,
            'email' => 'buyer@example.com',
            'payload' => [
                'order_id' => 17,
                'email' => 'buyer@example.com',
                'name' => 'Buyer',
            ],
        ]);

        $response = $this->postJson('/api/notifications/'.$log->id.'/resend');

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'У записи нет event_type, переотправка невозможна.',
            ]);
        Mail::assertNothingSent();
    }

    public function test_resend_uses_event_type_from_payload_when_top_level_missing(): void
    {
        Mail::fake();

        $log = EmailLog::factory()->failed()->create([
            'event_type' => null,
            'email' => 'buyer@example.com',
            'payload' => [
                'order_id' => 42,
                'email' => 'buyer@example.com',
                'name' => 'Buyer',
                'event_type' => 'order.paid',
            ],
        ]);

        $this->postJson('/api/notifications/'.$log->id.'/resend')->assertOk();

        Mail::assertSent(OrderPaidMail::class);
    }

    public function test_resend_returns_400_when_smtp_fails(): void
    {
        $maxRetries = NotificationService::MAX_RETRIES;

        $pendingMailMock = Mockery::mock(PendingMail::class);
        $pendingMailMock->shouldReceive('send')
            ->times($maxRetries)
            ->andThrow(new \Exception('SMTP Connection Rejected'));

        Mail::shouldReceive('to')
            ->times($maxRetries)
            ->andReturn($pendingMailMock);

        $log = EmailLog::factory()->failed()->create([
            'email' => 'fail@example.com',
            'event_type' => 'user.registered',
            'payload' => [
                'email' => 'fail@example.com',
                'name' => 'Fail',
                'event_type' => 'user.registered',
            ],
        ]);

        $response = $this->postJson('/api/notifications/'.$log->id.'/resend');

        $response->assertStatus(400)
            ->assertJsonFragment([
                'message' => 'Ошибка отправки из админки: SMTP Connection Rejected',
            ]);

        $this->assertEquals($maxRetries, $log->fresh()->retries);
    }
}
