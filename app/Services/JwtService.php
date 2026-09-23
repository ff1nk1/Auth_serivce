<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Throwable;

class JwtService
{
    private string $secret;
    private string $algorithm;
    private int $ttl;

    public function __construct()
    {
        $this->secret = (string) config('jwt.secret');
        $this->algorithm = (string) config('jwt.algorithm', 'HS256');
        $this->ttl = (int) config('jwt.ttl', 15) * 60;

        if ($this->secret === '') {
            throw new \RuntimeException('JWT_SECRET is not configured.');
        }
    }

    /**
     * Создание access JWT.
     */
    public function createAccessToken(User $user): string
    {
        $now = time();

        $payload = [
            'sub' => $user->getAuthIdentifier(),
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];

        return JWT::encode(
            $payload,
            $this->secret,
            $this->algorithm
        );
    }

    /**
     * Проверка и декодирование JWT.
     */
    public function decode(string $token): \stdClass
    {
        return JWT::decode(
            $token,
            new Key($this->secret, $this->algorithm)
        );
    }

    /**
     * Получить User из Authorization: Bearer ...
     */
public function userFromRequest(Request $request): ?User
{
    // Сначала пробуем взять JWT из HttpOnly cookie.
    // Если cookie нет — поддерживаем старый Bearer header.
    $token = $request->cookie('access_token')
        ?? $request->bearerToken();

    if (!$token) {
        return null;
    }

    try {
        $payload = $this->decode($token);

        if (
            !isset($payload->sub) ||
            !isset($payload->jti)
        ) {
            return null;
        }

        $blacklistKey = "jwt:blacklist:{$payload->jti}";

        if (Redis::exists($blacklistKey)) {
            return null;
        }

        return User::find($payload->sub);

    } catch (Throwable) {
        return null;
    }
}
}