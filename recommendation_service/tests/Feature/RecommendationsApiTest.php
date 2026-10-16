<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProductCatalog;
use App\Services\Llm\Contracts\LlmClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fakes\FakeLlmClient;
use Tests\TestCase;

class RecommendationsApiTest extends TestCase
{
    use RefreshDatabase;

    private FakeLlmClient $llm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->llm = new FakeLlmClient;
        $this->app->instance(LlmClient::class, $this->llm);
    }

    public function test_health_returns_ok(): void
    {
        $this->getJson('/api/recommendations/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('database', true)
            ->assertJsonPath('pgvector', true);
    }

    public function test_chat_creates_conversation_and_returns_products(): void
    {
        $this->seedProduct(1, 'Blue sneakers');

        $response = $this->postJson('/api/recommendations/chat', [
            'user_id' => '42',
            'message' => 'Find blue sneakers',
            'limit' => 5,
        ]);

        $response->assertOk()
            ->assertJsonPath('answer', 'Here are some products you might like.')
            ->assertJsonPath('products.0.product_id', 1)
            ->assertJsonPath('products.0.path', '/products/1');

        $conversationId = $response->json('conversation_id');
        $this->assertNotEmpty($conversationId);
        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'user_id' => '42',
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'role' => Message::ROLE_USER,
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'role' => Message::ROLE_ASSISTANT,
        ]);
    }

    public function test_chat_continues_same_conversation_context(): void
    {
        $this->seedProduct(1, 'Blue sneakers');

        $first = $this->postJson('/api/recommendations/chat', [
            'user_id' => '7',
            'message' => 'Hello',
        ])->assertOk();

        $conversationId = $first->json('conversation_id');

        $second = $this->postJson('/api/recommendations/chat', [
            'user_id' => '7',
            'conversation_id' => $conversationId,
            'message' => 'Something for running',
        ])->assertOk();

        $this->assertSame($conversationId, $second->json('conversation_id'));
        $this->assertSame(4, Message::query()->where('conversation_id', $conversationId)->count());
    }

    public function test_chat_rejects_foreign_conversation(): void
    {
        $now = now();
        $conversation = Conversation::query()->create([
            'user_id' => '1',
            'last_activity_at' => $now,
            'expires_at' => $now->copy()->addHour(),
        ]);

        $this->postJson('/api/recommendations/chat', [
            'user_id' => '2',
            'conversation_id' => $conversation->id,
            'message' => 'Hi',
        ])->assertNotFound();
    }

    public function test_get_and_delete_conversation_are_scoped_to_owner(): void
    {
        $now = now();
        $conversation = Conversation::query()->create([
            'user_id' => '9',
            'last_activity_at' => $now,
            'expires_at' => $now->copy()->addHour(),
        ]);
        Message::query()->create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_USER,
            'content' => 'Hi',
        ]);

        $this->getJson('/api/recommendations/conversations/'.$conversation->id.'?user_id=9')
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'Hi');

        $this->getJson('/api/recommendations/conversations/'.$conversation->id.'?user_id=8')
            ->assertNotFound();

        $this->deleteJson('/api/recommendations/conversations/'.$conversation->id, [
            'user_id' => '9',
        ])->assertNoContent();

        $this->assertDatabaseMissing('conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('messages', ['conversation_id' => $conversation->id]);
    }

    public function test_reindex_updates_local_embeddings_without_external_http(): void
    {
        $product = ProductCatalog::query()->create([
            'product_id' => 11,
            'store_id' => 1,
            'name' => 'Jacket',
            'description' => 'Warm',
            'category_name' => 'Clothes',
            'price' => 50,
            'attributes' => [],
            'is_published' => true,
            'in_stock' => true,
        ]);

        $this->postJson('/api/recommendations/reindex')
            ->assertOk()
            ->assertJsonPath('indexed', 1);

        $embedding = DB::table('product_catalog')->where('id', $product->id)->value('embedding');
        $this->assertNotNull($embedding);
    }

    private function seedProduct(int $productId, string $name): void
    {
        $product = ProductCatalog::query()->create([
            'product_id' => $productId,
            'store_id' => 1,
            'name' => $name,
            'description' => $name,
            'category_id' => 2,
            'category_name' => 'Shoes',
            'image_url' => '/img/'.$productId.'.jpg',
            'price' => 100,
            'attributes' => [['name' => 'Color', 'value' => 'Blue']],
            'is_published' => true,
            'in_stock' => true,
        ]);

        $vector = '['.implode(',', array_fill(0, 768, '0.01')).']';
        DB::statement(
            'UPDATE product_catalog SET embedding = ?::vector WHERE id = ?',
            [$vector, $product->id]
        );
    }
}
