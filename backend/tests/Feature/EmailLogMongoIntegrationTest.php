<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\EmailLog;
use App\Services\NotificationService;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\PendingMail;
use Mockery;

class EmailLogMongoIntegrationTest extends TestCase
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

    /**
     * 1. Проверяем, что создается начальная запись в MongoDB со статусом 'pending'
     * и последующий переход в статус 'sent'.
     */
    public function test_it_persists_initial_pending_state_and_updates_to_sent_in_mongodb()
    {
        Mail::fake();

        $service = new NotificationService();
        $payload = [
            'email' => 'mongo_user@example.com',
            'name'  => 'Mongo Test User',
        ];

        $result = $service->sendWelcomeMailWithRetry($payload);

        /** @var EmailLog $emailLog */
        $emailLog = $result['emailLog'];

        $this->assertNotNull($emailLog->_id); // У моделей MongoDB первичный ключ - _id
        $this->assertEquals('mongo_user@example.com', $emailLog->email);

        $this->assertDatabaseHas('email_logs', [
            '_id'          => $emailLog->_id,
            'email'        => 'mongo_user@example.com',
            'status'       => 'sent',
            'retries'      => 1,
            'moved_to_dlq' => false,
            'error_message' => null,
        ], 'mongodb'); 

        $emailLog->refresh();
        $this->assertNotNull($emailLog->time);
    }

    /**
     * 2. Проверяем изменение статуса на 'failed' и сохранение сообщения об ошибке в MongoDB (метод send_email).
     */
    public function test_it_updates_status_to_failed_and_stores_error_message_in_mongodb()
    {
        // Симулируем сбой отправки
        $pendingMailMock = Mockery::mock(PendingMail::class);
        $pendingMailMock->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('SMTP Connection Rejected'));

        Mail::shouldReceive('to')
            ->once()
            ->with('fail_user@example.com')
            ->andReturn($pendingMailMock);

        $logInMongo = EmailLog::factory()->create([
            'email'  => 'fail_user@example.com',
            'status' => 'pending',
        ]);

        $service = new NotificationService();
        $result = $service->send_email($logInMongo);

        $this->assertFalse($result);

        $logInMongo->refresh();

        $this->assertEquals('failed', $logInMongo->status);
        $this->assertEquals('Ошибка отправки из админки: SMTP Connection Rejected', $logInMongo->error_message);

        $this->assertDatabaseHas('email_logs', [
            '_id'           => $logInMongo->_id,
            'status'        => 'failed',
            'error_message' => 'Ошибка отправки из админки: SMTP Connection Rejected',
        ], 'mongodb');
    }

    /**
     * 3. Проверяем инкремент счётчика 'retries' в MongoDB при промежуточных сбоях.
     */
    public function test_it_increments_retries_count_in_mongodb_on_failures()
    {
        $maxRetries = NotificationService::MAX_RETRIES;

        // Мокаем постоянный сбой почты
        $pendingMailMock = Mockery::mock(PendingMail::class);
        $pendingMailMock->shouldReceive('send')
            ->times($maxRetries)
            ->andThrow(new \Exception('Mongo Retry Test Failure'));

        Mail::shouldReceive('to')
            ->times($maxRetries)
            ->with('retry_mongo@example.com')
            ->andReturn($pendingMailMock);

        /** @var NotificationService&\Mockery\MockInterface $service */
        $service = Mockery::mock(NotificationService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('sleepInTest')->times($maxRetries - 1);

        $payload = ['email' => 'retry_mongo@example.com', 'name' => 'Retry User'];

        $result = $service->sendWelcomeMailWithRetry($payload);

        /** @var EmailLog $emailLog */
        $emailLog = $result['emailLog'];

        // Запрашиваем свежие данные из MongoDB
        $emailLog->refresh();

        // Проверяем, что в Mongo зафиксировались все попытки
        $this->assertEquals($maxRetries, $emailLog->retries);
        $this->assertEquals('pending', $emailLog->status);

        // Проверяем актуальное состояние в Mongo-коллекции
        $this->assertDatabaseHas('email_logs', [
            '_id'     => $emailLog->_id,
            'retries' => $maxRetries,
            'status'  => 'pending',
        ], 'mongodb');
    }
}