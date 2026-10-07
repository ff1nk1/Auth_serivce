<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

trait UsesRedisCache
{
    protected function enableRedisCache(): void
    {
        config([
            'cache.default' => 'redis',
            'database.redis.default.host' => env('REDIS_HOST', '127.0.0.1'),
            'database.redis.default.port' => env('REDIS_PORT', 6381),
            'database.redis.default.database' => env('REDIS_DB', 15),
            'database.redis.cache.host' => env('REDIS_HOST', '127.0.0.1'),
            'database.redis.cache.port' => env('REDIS_PORT', 6381),
            'database.redis.cache.database' => env('REDIS_DB', 15),
        ]);

        Cache::forgetDriver('redis');
        Redis::connection()->select((int) env('REDIS_DB', 15));
        Redis::flushdb();
        Cache::flush();
    }
}
