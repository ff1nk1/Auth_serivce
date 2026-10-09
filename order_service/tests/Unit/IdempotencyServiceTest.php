<?php

namespace Tests\Unit;

use App\Services\IdempotencyService;
use Illuminate\Support\Str;
use Tests\Concerns\UsesRedis;
use Tests\TestCase;

class IdempotencyServiceTest extends TestCase
{
    use UsesRedis;

    private IdempotencyService $idempotency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableRedis();
        $this->idempotency = new IdempotencyService;
    }

    public function test_put_and_get_round_trip(): void
    {
        $key = 'idem-'.Str::uuid();
        $body = ['id' => 9, 'status' => 'pending'];

        $this->assertNull($this->idempotency->get($key));

        $this->idempotency->put($key, 201, $body);

        $cached = $this->idempotency->get($key);
        $this->assertSame(201, $cached['status'] ?? null);
        $this->assertSame($body, $cached['body'] ?? null);
    }

    public function test_acquire_lock_is_exclusive(): void
    {
        $key = 'lock-'.Str::uuid();

        $this->assertTrue($this->idempotency->acquireLock($key));
        $this->assertFalse($this->idempotency->acquireLock($key));

        $this->idempotency->releaseLock($key);
        $this->assertTrue($this->idempotency->acquireLock($key));
        $this->idempotency->releaseLock($key);
    }
}
