<?php

namespace Tests\Integration;

use App\Models\OutboxEvent;
use App\Services\IdempotencyService;
use Illuminate\Support\Str;

class IdempotencyRedisIntegrationTest extends IntegrationTestCase
{
    public function test_duplicate_create_with_same_idempotency_key_writes_single_order(): void
    {
        $this->seedSnapshot();

        $headers = ['Idempotency-Key' => 'it-create-'.Str::uuid()];
        $payload = $this->orderCreatePayload();

        $first = $this->postJson('/api/orders', $payload, $headers);
        $first->assertCreated();
        $orderId = $first->json('id');

        $second = $this->postJson('/api/orders', $payload, $headers);
        $second->assertCreated();
        $this->assertSame($orderId, $second->json('id'));

        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'order.created')->count());
        $this->assertDatabaseCount('orders', 1);

        $cached = app(IdempotencyService::class)->get($headers['Idempotency-Key']);
        $this->assertSame(201, $cached['status'] ?? null);
        $this->assertSame($orderId, $cached['body']['id'] ?? null);
    }

    public function test_lock_contention_returns_conflict_when_response_not_cached_yet(): void
    {
        $key = 'it-lock-'.Str::uuid();
        $idem = app(IdempotencyService::class);

        $this->assertTrue($idem->acquireLock($key));

        $this->seedSnapshot();
        $response = $this->postJson('/api/orders', $this->orderCreatePayload(), [
            'Idempotency-Key' => $key,
        ]);

        // Lock held, no cached body → 409 after short wait in OrderService.
        $response->assertStatus(409);

        $idem->releaseLock($key);
    }
}
