<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Redis;

class TokenBlacklistService
{
    public function isJwtBlacklisted(string $jti): bool
    {
        return Redis::exists("jwt:blacklist:{$jti}") > 0;
    }

    public function blacklistJwt(string $jti, int $ttl): void
    {
        $ttl = $this->normalizeTtl($ttl, (int) config('jwt.ttl', 15) * 60);
        if ($ttl <= 0) {
            return;
        }

        Redis::setex("jwt:blacklist:{$jti}", $ttl, '1');
    }

    public function isRefreshTokenBlacklisted(string $hash): bool
    {
        return Redis::exists("refresh_token:blacklist:{$hash}") > 0;
    }

    public function blacklistRefreshToken(string $hash, int $ttl): void
    {
        // Redis TTL -1 (no expiry) / 0 / missing → use configured refresh lifetime
        $ttl = $this->normalizeTtl($ttl, (int) config('jwt.refresh_ttl'));
        if ($ttl <= 0) {
            return;
        }

        Redis::setex("refresh_token:blacklist:{$hash}", $ttl, '1');
    }

    private function normalizeTtl(int $ttl, int $fallback): int
    {
        if ($ttl > 0) {
            return $ttl;
        }

        return max(0, $fallback);
    }
}
