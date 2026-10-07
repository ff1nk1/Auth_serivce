<?php

namespace Tests\Feature;

use App\Kafka\Handlers\UserEventsHandler;
use App\Models\EmailLog;
use App\Services\NotificationService;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Junges\Kafka\Contracts\ConsumerMessage;
use Junges\Kafka\Facades\Kafka;
use Mockery;
use Tests\TestCase;

class UserEventsHandlerTest extends TestCase
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
            ->with('dlq@example.com')
            ->andReturn($pendingMailMock);

        /** @var NotificationService&\Mockery\MockInterface $service */
        $service = Mockery::mock(NotificationService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('sleepInTest')->times($maxRetries - 1);

        $this->app->instance(NotificationService::class, $service);

        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getBody')->andReturn([
            'email' => 'dlq@example.com',
            'name' => 'DLQ User',
            'user_id' => 42,
        ]);
        $message->shouldReceive('getKey')->andReturn('42');
        $message->shouldReceive('getHeaders')->andReturn([
            'event-type' => 'user.registered',
        ]);

        $handler = app(UserEventsHandler::class);
        $handler($message);

        $log = EmailLog::where('email', 'dlq@example.com')->first();
        $this->assertNotNull($log);
        $this->assertEquals('failed', $log->status);
        $this->assertTrue((bool) $log->moved_to_dlq);
        $this->assertEquals($maxRetries, $log->retries);
        $this->assertStringContainsString('SMTP permanently down', (string) $log->error_message);

        Kafka::assertPublishedOn('user.events.dlq');
    }

    public function test_handler_skips_messages_without_email(): void
    {
        Kafka::fake();
        Mail::fake();

        $message = Mockery::mock(ConsumerMessage::class);
        $message->shouldReceive('getBody')->andReturn(['name' => 'No Email']);

        app(UserEventsHandler::class)($message);

        $this->assertSame(0, EmailLog::count());
        Kafka::assertNothingPublished();
    }
}
