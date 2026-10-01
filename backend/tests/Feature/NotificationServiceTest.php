<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Junges\Kafka\Facades\Kafka;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Auth\AuthService;
use Junges\Kafka\Message\Message;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase; 
    protected function setUp(): void
    {
        parent::setUp();
        
        Role::factory()->create([
            'name' => 'Customer',
            'slug' => 'customer'
        ]);
    }

    /**
     * Сценарий 1: Успешная регистрация и успешная отправка события в Kafka.
     */
    public function test_it_registers_user_and_publishes_to_kafka()
    {
        //перехват отправки Kafka-сообщений (чтобы не стучаться в реальный брокер)
        Kafka::fake();

        $userData = [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'secret123',
        ];

        $service = app(AuthService::class);
        $user = $service->register($userData);

        $this->assertDatabaseHas('users', [
            'id'    => $user->id,
            'email' => 'john@example.com',
            'name'  => 'John Doe',
        ]);

        $this->assertTrue(Hash::check('secret123', $user->password));

        $expectedMessage = new Message(
            headers: [
                'event-type' => 'user.registered',
                'source'     => 'auth-service',
            ],
            body: [
                'user_id' => $user->id,
                'email'   => 'john@example.com',
                'name'    => 'John Doe',
            ],
            key: (string) $user->id
        );

        Kafka::assertPublishedOn('user.events', $expectedMessage);
        
    }

    /**
     * Сценарий 2: Ошибка Kafka логируется, но регистрация пользователя НЕ прерывается.
     */
    public function test_it_registers_user_even_if_kafka_fails()
    {
        Kafka::shouldReceive('publish')
            ->once()
            ->andThrow(new \Exception('Connection to Kafka broker timed out'));

        $logSpy = Log::spy();

        $userData = [
            'name'     => 'Jane Doe',
            'email'    => 'jane@example.com',
            'password' => 'secret123',
        ];

        $service = app(AuthService::class);
        $user = $service->register($userData);

        $this->assertDatabaseHas('users', [
            'email' => 'jane@example.com',
            'name'  => 'Jane Doe',
        ]);

        $logSpy->shouldHaveReceived('error')
            ->once()
            ->withArgs(function ($message, $context) use ($user) {
                return $message === 'Kafka publish failed on user.registered' 
                    && isset($context['user_id']) && $context['user_id'] === $user->id
                    && isset($context['error']) && $context['error'] === 'Connection to Kafka broker timed out';
            });
    }
}