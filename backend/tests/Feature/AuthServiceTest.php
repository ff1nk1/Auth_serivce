<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AuthService $authService;
    protected JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        
        // Устанавливаем реальные конфиги для теста
        Config::set('jwt.secret', 'real-secret-key-for-integration-tests-12345');
        Config::set('jwt.algorithm', 'HS256');
        Config::set('jwt.ttl', 15);
        Config::set('jwt.refresh_ttl', 100); 

        $this->jwtService = new JwtService();
        $this->authService = new AuthService($this->jwtService);
        
        Redis::flushdb(); 
    }

    /**
     * Тест 1: Полный цикл обновления токенов (Refresh Flow)
     */
    public function test_full_refresh_token_flow_with_redis(): void
    {
        // 1. Создаем юзера и логинимся
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id, 'password' => Hash::make('password123')]);
        
        $tokens = $this->authService->login(['email' => $user->email, 'password' => 'password123']);
        $oldRefreshToken = $tokens['refresh_token'];
        
        // Убеждаемся, что хэш старого токена реально лежит в Redis
        $oldHash = hash('sha256', $oldRefreshToken);
        $this->assertTrue(Redis::exists("refresh_token:{$oldHash}") > 0, 'Refresh токен должен быть в Redis после логина');

        // 2. Делаем Refresh
        $newTokens = $this->authService->refreshTokens($oldRefreshToken);

        $this->assertNotNull($newTokens);
        $this->assertArrayHasKey('access_token', $newTokens);
        $this->assertArrayHasKey('refresh_token', $newTokens);

        // 3. Проверяем состояние Redis после Refresh
        $this->assertFalse(
            Redis::exists("refresh_token:{$oldHash}") > 0, 
            'Старый токен должен быть удален из активных'
        );
        $this->assertTrue(
            Redis::exists("refresh_token:blacklist:{$oldHash}") > 0, 
            'Старый токен должен быть помещен в блэклист'
        );

        $newHash = hash('sha256', $newTokens['refresh_token']);
        $this->assertTrue(
            Redis::exists("refresh_token:{$newHash}") > 0, 
            'Новый токен должен быть сохранен в Redis'
        );
    }

    /**
     * Тест 2: Защита от повторного использования Refresh-токена (Replay Attack)
     */
    public function test_cannot_use_blacklisted_refresh_token(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id, 'password' => Hash::make('password')]);
        
        // Получаем токены
        $tokens = $this->authService->login(['email' => $user->email, 'password' => 'password']);
        $refreshToken = $tokens['refresh_token'];
        
        // 1-й рефреш (должен пройти успешно)
        $firstRefresh = $this->authService->refreshTokens($refreshToken);
        $this->assertNotNull($firstRefresh);

        // Должен вернуть null, так как токен уже в блэклисте
        $secondRefresh = $this->authService->refreshTokens($refreshToken);
        
        $this->assertNull($secondRefresh, 'Использование блэклистнутого токена должно возвращать null');
    }

    /**
     * Тест 3: Логаут отзывает JWT Access токен, и система его больше не принимает
     */
    public function test_logout_invalidates_jwt_access_token_via_redis_blacklist(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create(['role_id' => $role->id, 'password' => Hash::make('password')]);
        
        $tokens = $this->authService->login(['email' => $user->email, 'password' => 'password']);
        $accessToken = $tokens['access_token'];

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', "Bearer {$accessToken}");

        // До логаута: пользователь успешно определяется
        $identifiedUser = $this->jwtService->userFromRequest($request);
        $this->assertNotNull($identifiedUser);
        $this->assertEquals($user->id, $identifiedUser->id);

        // Выполняем логаут
        $this->authService->logout($accessToken, null);

        $identifiedUserAfterLogout = $this->jwtService->userFromRequest($request);
        $this->assertNull($identifiedUserAfterLogout, 'После логаута токен не должен авторизовывать пользователя');
        
        // Убедимся физически, что JTI токена лежит в Redis
        $decoded = $this->jwtService->decode($accessToken);
        $this->assertTrue(
            Redis::exists("jwt:blacklist:{$decoded->jti}") > 0,
            'JTI токена должен находиться в блэклисте Redis'
        );
    }
}