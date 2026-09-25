<?php

namespace App\Services\Auth;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private JwtService $jwtService
    ) {}

    /**
     * Регистрация пользователя
     */
    public function register(array $userData): User
    {
        $userData['password'] = Hash::make($userData['password']);

        $userData['role_id'] = Role::where('slug', 'customer')->valueOrFail('id');

        $user = User::create($userData);

        Profile::create([
            'user_id'    => $user->id,
            'first_name' => $user->name,
        ]);

        return $user;
    }

    /**
     * Проверка данных и генерация пары токенов
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Неверный логин или пароль.'],
            ]);
        }

        return $this->generateTokens($user);
    }

    /**
     * Обновление токенов
     */
    public function refreshTokens(?string $oldRefreshToken): ?array
    {
        if (! $oldRefreshToken) {
            return null;
        }

        $oldHash = hash('sha256', $oldRefreshToken);
        $oldKey = "refresh_token:{$oldHash}";
        $blacklistKey = "refresh_token:blacklist:{$oldHash}";

        // Токен уже был использован или отозван
        if (Redis::exists($blacklistKey)) {
            return null;
        }

        $userId = Redis::get($oldKey);
        if (! $userId) {
            return null;
        }

        $user = User::find($userId);
        if (! $user) {
            Redis::del($oldKey);
            return null;
        }

        // Отправляем старый токен в blacklist
        $oldTtl = Redis::ttl($oldKey);
        if ($oldTtl > 0) {
            Redis::setex($blacklistKey, $oldTtl, '1');
        }
        Redis::del($oldKey);

        return $this->generateTokens($user);
    }

    /**
     * Инвалидация токенов
     */
    public function logout(?string $accessToken, ?string $refreshToken): void
    {
        if ($accessToken) {
            $this->jwtService->revokeToken($accessToken);
        }

        if ($refreshToken) {
            $hash = hash('sha256', $refreshToken);
            $key = "refresh_token:{$hash}";
            $blacklistKey = "refresh_token:blacklist:{$hash}";

            $ttl = Redis::ttl($key);
            if ($ttl > 0) {
                Redis::setex($blacklistKey, $ttl, '1');
            }

            Redis::del($key);
        }
    }

    /**
     * Приватный метод для переиспользуемой логики создания токенов
     */
    private function generateTokens(User $user): array
    {
        $accessToken = $this->jwtService->createAccessToken($user);
        
        $refreshToken = bin2hex(random_bytes(64));
        $refreshHash = hash('sha256', $refreshToken);
        $refreshTtl = (int) config('jwt.refresh_ttl');

        Redis::setex("refresh_token:{$refreshHash}", $refreshTtl, $user->id);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }
}