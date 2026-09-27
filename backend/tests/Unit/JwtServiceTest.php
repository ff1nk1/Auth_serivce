<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use App\Models\Role;

class JwtServiceTest extends TestCase
{
    use RefreshDatabase; // Используем для работы с БД (создание пользователя)

    protected JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        // Устанавливаем конфиги для инициализации JwtService
        Config::set('jwt.secret', 'test-secret-key-which-should-be-long');
        Config::set('jwt.algorithm', 'HS256');
        Config::set('jwt.ttl', 15);

        $this->jwtService = new JwtService();
    }

    /**
     * Тест 1: Возвращает null, если токен не передан (нет ни cookie, ни заголовка Bearer)
     */
    public function test_user_from_request_returns_null_when_no_token_provided(): void
    {
        // Создаем пустой запрос
        $request = Request::create('/api/test', 'GET');

        $result = $this->jwtService->userFromRequest($request);

        $this->assertNull($result);
    }

    /**
     * Тест 2: Успешно возвращает пользователя при передаче валидного токена
     */
    public function test_user_from_request_returns_user_with_valid_token(): void
    {
        // Создаем тестового пользователя
        Role::factory()->create(['id' => 1]);
        $user = User::factory()->create();

        // Генерируем для него реальный токен через сервис
        $token = $this->jwtService->createAccessToken($user);

        // Имитируем запрос с заголовком Authorization
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', "Bearer {$token}");

        // Мокаем фасад Redis, чтобы он сказал, что токена НЕТ в блэклисте
        Redis::shouldReceive('exists')
            ->once()
            ->withArgs(function (string $key) {
                return str_starts_with($key, 'jwt:blacklist:');
            })
            ->andReturn(false);

        // Вызываем тестируемый метод
        $result = $this->jwtService->userFromRequest($request);

        // Проверяем, что вернулся объект пользователя и его ID совпадает
        $this->assertNotNull($result);
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
    }

    /**
     * Тест 3: Возвращает null, если токен есть в блэклисте Redis
     */
    public function test_user_from_request_returns_null_when_token_is_blacklisted(): void
    {
        Role::factory()->create(['id' => 1]);
        $user = User::factory()->create();
        $token = $this->jwtService->createAccessToken($user);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', "Bearer {$token}");

        // Мокаем фасад Redis, чтобы он сказал, что токен ЕСТЬ в блэклисте
        Redis::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        $result = $this->jwtService->userFromRequest($request);

        $this->assertNull($result);
    }
}