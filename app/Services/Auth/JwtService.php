<?php

namespace App\Services\Auth;

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
        $token = $request->cookie('access_token') ?? $request->bearerToken();

        if (! $token) {
            return null;
        }

        try {
            $payload = $this->decode($token);

            if (! isset($payload->sub) || ! isset($payload->jti)) {
                return null;
            }

            $blacklistKey = "jwt:blacklist:{$payload->jti}";
            $isBlacklisted = Redis::exists($blacklistKey);

            if ($isBlacklisted) {
                return null;
            }

            $user = User::find($payload->sub);

            return $user;
        } catch (Throwable $e) {

            return null;
        }
    }

    /**
     * Помещает access-токен в blacklist по его jti.
     *
     * TTL blacklist-записи = оставшееся время жизни токена.
     * Как только токен сам истечёт — запись исчезнет из Redis.
     */
    public function revokeToken(string $token): void
    {
        try {
            $payload = $this->decode($token);

            if (! isset($payload->jti) || ! isset($payload->exp)) {
                return;
            }

            $now = time();
            $remainingTtl = $payload->exp - $now;

            /*
             * Токен уже истёк — блэклистить нечего,
             * он и так никого не пустит.
             */
            if ($remainingTtl <= 0) {
                return;
            }

            Redis::setex(
                "jwt:blacklist:{$payload->jti}",
                $remainingTtl,
                '1'
            );
        } catch (Throwable) {
            /*
             * Битый токен — блэклистить нечего.
             * Просто молча выходим.
             */
        }
    }
}
