<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class IdempotencyService
{
    private const TTL_SECONDS = 86400;

    private const LOCK_TTL_SECONDS = 30;

    public function get(string $key): ?array
    {
        $raw = Redis::get($this->responseKey($key));

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    public function put(string $key, int $status, array $body): void
    {
        Redis::setex(
            $this->responseKey($key),
            self::TTL_SECONDS,
            json_encode([
                'status' => $status,
                'body' => $body,
            ], JSON_THROW_ON_ERROR)
        );
    }

    public function acquireLock(string $key): bool
    {
        return (bool) Redis::set(
            $this->lockKey($key),
            '1',
            'EX',
            self::LOCK_TTL_SECONDS,
            'NX'
        );
    }

    public function releaseLock(string $key): void
    {
        Redis::del($this->lockKey($key));
    }

    private function responseKey(string $key): string
    {
        return 'order:idempotency:'.$key;
    }

    private function lockKey(string $key): string
    {
        return 'order:idempotency:lock:'.$key;
    }
}
