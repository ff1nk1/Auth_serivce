<?php
namespace Tests\Unit;

use Tests\TestCase;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeMail;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\PendingMail;
use Mockery;
// use Illuminate\Foundation\Testing\RefreshDatabase; // Если у вас MongoDB, очистку базы нужно настроить отдельно, так как стандартный RefreshDatabase работает с SQL

class NotificationTest extends TestCase
{
    // ИСПОЛЬЗУЙТЕ ТРЕЙТ ОЧИСТКИ БАЗЫ, ЕСЛИ НУЖНО
    // use RefreshDatabase;

    public function test_send_email_success()
    {
        Mail::fake();

        $notification = EmailLog::factory()->create([
            'email' => 'test@example.com',
            'name'  => 'John Doe',
            'status' => 'pending',
        ]);

        $service = new NotificationService();
        $result = $service->send_email($notification);

        $this->assertTrue($result);

        Mail::assertSent(WelcomeMail::class, function ($mail) use ($notification) {
            // Проверяем адресата
            $hasCorrectTo = $mail->hasTo($notification->email);
            $hasCorrectPayload = 
                isset($mail->payload['email']) && $mail->payload['email'] === $notification->email &&
                isset($mail->payload['name']) && $mail->payload['name'] === $notification->name;
            return $hasCorrectTo && $hasCorrectPayload; 
        });

        $notification->refresh();
        $this->assertEquals('sent', $notification->status);
        $this->assertNull($notification->error_message);
        $this->assertNotNull($notification->time);
    }

    public function test_send_email_failure_handles_exception()
    {
        $pendingMailMock = Mockery::mock(PendingMail::class);
        
        $pendingMailMock->shouldReceive('send')
            ->once()
            ->andThrow(new \Exception('SMTP connection timeout'));

        //Мокаем сам фасад Mail, чтобы метод to() вернул наш $pendingMailMock
        Mail::shouldReceive('to')
            ->once()
            ->with('test@example.com')
            ->andReturn($pendingMailMock);

        // 1. ИСПОЛЬЗУЕМ ВАШУ НОВУЮ ФАБРИКУ ВМЕСТО MOCKERY
        $notification = EmailLog::factory()->create([
            'email' => 'test@example.com',
            'name'  => 'John Doe',
            'status' => 'pending',
        ]);

        $service = new NotificationService();
        $result = $service->send_email($notification);

        $this->assertFalse($result);

        // 2. ПРОВЕРЯЕМ, ЧТО В БАЗЕ СТАТУС ИЗМЕНИЛСЯ НА FAILED
        $notification->refresh();
        $this->assertEquals('failed', $notification->status);
        $this->assertEquals('Ошибка отправки из админки: SMTP connection timeout', $notification->error_message);
    }

    public function test_welcome_mail_has_correct_data_and_subject()
    {
        $payload = [
            'email' => 'test@example.com',
            'name'  => 'Иван Иванов',
        ];

        $mail = new WelcomeMail($payload);

        // Проверяем тему письма (Envelope)
        $this->assertEquals('Добро пожаловать на наш сайт!', $mail->envelope()->subject);

        // Проверяем, что письмо успешно рендерится и содержит имя пользователя.
        $mail->assertSeeInHtml('Иван Иванов');
        $mail->assertSeeInHtml('test@example.com');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Сценарий 1: Успешная отправка с первой попытки.
     */
    public function test_it_sends_email_successfully_on_first_attempt()
    {
        Mail::fake();
        $logSpy = Log::spy();

        $service = new NotificationService();
        $payload = ['email' => 'test@example.com', 'name' => 'John'];

        $result = $service->sendWelcomeMailWithRetry($payload);

        $this->assertTrue($result['isSent']);
        $this->assertEmpty($result['lastError']);
        
        $this->assertEquals('sent', $result['emailLog']->status);
        $this->assertEquals(1, $result['emailLog']->retries);
        
        Mail::assertSent(WelcomeMail::class, function ($mail) use ($payload) {
            return $mail->hasTo($payload['email']);
        });

        // Проверяем запись в Log
        $logSpy->shouldHaveReceived('info')
            ->once()
            ->withArgs(function ($msg) {
                return str_contains($msg, '[KAFKA УСПЕХ]');
            });
    }

    /**
     * Сценарий 2: Успешная отправка со второй попытки (отработка Retry-механики).
     */
    public function test_it_retries_and_succeeds_on_second_attempt()
    {
        $pendingMailMock = Mockery::mock(PendingMail::class);
        
        $callCount = 0;
        $pendingMailMock->shouldReceive('send')
            ->twice()
            ->andReturnUsing(function () use (&$callCount) {
                $callCount++;
                if ($callCount === 1) {
                    throw new \Exception('SMTP timeout');
                }
                return true;
            });

        Mail::shouldReceive('to')
            ->twice()
            ->with('test@example.com')
            ->andReturn($pendingMailMock);

        $logSpy = Log::spy();

        /** @var NotificationService&\Mockery\MockInterface $service */
        $service = Mockery::mock(NotificationService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('sleepInTest')
            ->once()
            ->with(Mockery::type('numeric')); 

        $payload = ['email' => 'test@example.com', 'name' => 'John'];
        
        $result = $service->sendWelcomeMailWithRetry($payload);

        $this->assertTrue($result['isSent']);
        $this->assertEquals(2, $result['emailLog']->retries); 
        $this->assertEquals('sent', $result['emailLog']->status);

        $logSpy->shouldHaveReceived('warning')->once();
        $logSpy->shouldHaveReceived('info')->once();
    }

    /**
     * Сценарий 3: Полный сбой (исчерпаны все попытки).
     */
    public function test_it_fails_after_max_retries()
    {
        $maxRetries = NotificationService::MAX_RETRIES; // Берем константу из вашего класса
        
        // Мокаем почту так, чтобы она падала ВСЕ разы
        $pendingMailMock = Mockery::mock(PendingMail::class);
        $pendingMailMock->shouldReceive('send')
            ->times($maxRetries)
            ->andThrow(new \Exception('Critical SMTP Error'));

        Mail::shouldReceive('to')
            ->times($maxRetries)
            ->with('test@example.com')
            ->andReturn($pendingMailMock);

        $logSpy = Log::spy();

        /** @var NotificationService&\Mockery\MockInterface $service */
        $service = Mockery::mock(NotificationService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('sleepInTest')
            ->times($maxRetries - 1); 

        $payload = ['email' => 'test@example.com'];

        $result = $service->sendWelcomeMailWithRetry($payload);

        $this->assertFalse($result['isSent']);
        $this->assertEquals('Critical SMTP Error', $result['lastError']);
        $this->assertEquals($maxRetries, $result['emailLog']->retries);
        
        $this->assertEquals('pending', $result['emailLog']->status);

        $logSpy->shouldHaveReceived('warning')->times($maxRetries - 1);
        
        $logSpy->shouldNotHaveReceived('info');
    }

}