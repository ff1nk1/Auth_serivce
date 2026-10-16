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
        if ($ttl <= 0) {
            return;
        }

        Redis::setex("refresh_token:blacklist:{$hash}", $ttl, '1');
    }
}
