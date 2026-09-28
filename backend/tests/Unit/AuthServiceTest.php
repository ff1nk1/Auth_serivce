<?php

namespace Tests\Unit\Services\Auth;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['jwt.refresh_ttl' => 20160]);
    }

    /**
     * Тест логина со строгим мокированием JwtService
     */
    public function test_login_returns_tokens_with_mocked_jwt_service(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('password123'),
        ]);

        $credentials = [
            'email' => $user->email,
            'password' => 'password123',
        ];

        $fakeJwt = 'fake.access.token';

        $this->mock(JwtService::class, function (MockInterface $mock) use ($user, $fakeJwt) {
            $mock->shouldReceive('createAccessToken')
                ->once()
                ->withArgs(function ($arg) use ($user) {
                    return $arg->id === $user->id;
                })
                ->andReturn($fakeJwt);
        });

        Redis::shouldReceive('setex')->once();

        $authService = app(AuthService::class);
        $tokens = $authService->login($credentials);

        $this->assertEquals($fakeJwt, $tokens['access_token']);
        $this->assertArrayHasKey('refresh_token', $tokens);
    }

    /**
     * Тест неверных данных (здесь JwtService вообще не должен вызываться)
     */
    public function test_login_throws_exception_on_invalid_credentials(): void
    {
        // Создаем роль и пользователя
        $role = Role::factory()->create();
        $user = User::factory()->create([
            'role_id' => $role->id, // Указываем реальный ID созданной роли
            'password' => Hash::make('correct_password'),
        ]);

        $credentials = [
            'email' => $user->email,
            'password' => 'wrong_password',
        ];

        $this->mock(JwtService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createAccessToken')->never();
        });

        Redis::shouldReceive('setex')->never();

        $authService = app(AuthService::class);

        // Ожидаем ошибку валидации
        $this->expectException(ValidationException::class);

        // Пытаемся залогиниться
        $authService->login($credentials);
    }

    /**
     * Тест логаута со строгим мокированием
     */
    public function test_logout_calls_revoke_on_jwt_service(): void
    {
        $accessToken = 'some.valid.token';

        $this->mock(JwtService::class, function (MockInterface $mock) use ($accessToken) {
            $mock->shouldReceive('revokeToken')
                ->once()
                ->with($accessToken);
        });

        // Мокаем Redis для refresh_token (в логауте он удаляется, но в этом тесте мы передаем null для рефреша)
        Redis::shouldReceive('del')->never();

        $authService = app(AuthService::class);

        $authService->logout($accessToken, null);
    }
}
