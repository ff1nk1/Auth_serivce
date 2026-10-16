<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ProductCatalog;
use App\Services\Catalog\ProductSearchService;
use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\DTOs\ChatMessage;
use App\Services\Llm\DTOs\SearchFilters;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

class RecommendationChatService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a shopping recommendation assistant.
Use only the provided product candidates. Mention product names and refer to them by path when helpful.
If nothing fits, say so briefly. Keep answers concise.
PROMPT;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly ProductSearchService $search,
    ) {}

    /**
     * @param  array{
     *     user_id: string|int,
     *     message: string,
     *     conversation_id?: string|null,
     *     store_id?: int|null,
     *     category_id?: int|null,
     *     in_stock?: bool|null,
     *     price_min?: float|null,
     *     price_max?: float|null,
     *     limit?: int|null
     * }  $input
     * @return array{
     *     conversation_id: string,
     *     answer: string,
     *     extracted_filters: array<string, mixed>,
     *     products: list<array<string, mixed>>
     * }
     */
    public function chat(array $input): array
    {
        $userId = (string) $input['user_id'];
        $messageText = trim((string) $input['message']);
        $conversation = $this->resolveConversation(
            $userId,
            isset($input['conversation_id']) ? (string) $input['conversation_id'] : null,
        );

        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get(['role', 'content']);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_USER,
            'content' => $messageText,
        ]);

        $extracted = $this->llm->extractFilters($messageText);
        $merged = $this->mergeFilters($extracted, $input);
        $limit = max(1, min((int) ($input['limit'] ?? 5), 20));

        $products = $this->search->search($merged, $limit);
        $productCards = array_map(
            static fn (ProductCatalog $p): array => $p->toCardArray(),
            $products,
        );

        $chatMessages = [];
        foreach ($history as $row) {
            $chatMessages[] = $row->role === Message::ROLE_ASSISTANT
                ? ChatMessage::assistant((string) $row->content)
                : ChatMessage::user((string) $row->content);
        }
        $chatMessages[] = ChatMessage::user($messageText."\n\nCandidates:\n".$this->formatCandidates($productCards));

        $answer = $this->llm->chat($chatMessages, self::SYSTEM_PROMPT);

        Message::query()->create([
            'conversation_id' => $conversation->id,
            'role' => Message::ROLE_ASSISTANT,
            'content' => $answer,
        ]);

        $conversation->touchActivity();

        return [
            'conversation_id' => (string) $conversation->id,
            'answer' => $answer,
            'extracted_filters' => $merged->toArray(),
            'products' => $productCards,
        ];
    }

    public function getConversation(string $userId, string $conversationId): Conversation
    {
        return $this->findOwnedActive($userId, $conversationId);
    }

    public function deleteConversation(string $userId, string $conversationId): void
    {
        $this->findOwnedActive($userId, $conversationId)->delete();
    }

    private function resolveConversation(string $userId, ?string $conversationId): Conversation
    {
        if ($conversationId === null || $conversationId === '') {
            $now = Carbon::now();

            return Conversation::query()->create([
                'user_id' => $userId,
                'last_activity_at' => $now,
                'expires_at' => Conversation::computeExpiresAt($now, $now),
            ]);
        }

        return $this->findOwnedActive($userId, $conversationId);
    }

    private function findOwnedActive(string $userId, string $conversationId): Conversation
    {
        $conversation = Conversation::query()
            ->where('id', $conversationId)
            ->where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->first();

        if ($conversation === null) {
            throw (new ModelNotFoundException)->setModel(Conversation::class, [$conversationId]);
        }

        return $conversation;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function mergeFilters(SearchFilters $extracted, array $input): SearchFilters
    {
        return new SearchFilters(
            storeId: isset($input['store_id']) ? (int) $input['store_id'] : $extracted->storeId,
            categoryId: isset($input['category_id']) ? (int) $input['category_id'] : $extracted->categoryId,
            inStock: array_key_exists('in_stock', $input) && $input['in_stock'] !== null
                ? (bool) $input['in_stock']
                : $extracted->inStock,
            priceMin: isset($input['price_min']) ? (float) $input['price_min'] : $extracted->priceMin,
            priceMax: isset($input['price_max']) ? (float) $input['price_max'] : $extracted->priceMax,
            query: $extracted->query,
            attributes: $extracted->attributes,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function formatCandidates(array $products): string
    {
        if ($products === []) {
            return '(none)';
        }

        $lines = [];
        foreach ($products as $i => $product) {
            $n = $i + 1;
            $lines[] = sprintf(
                '%d. %s | price=%s | path=%s | store_id=%s',
                $n,
                $product['name'] ?? '',
                $product['price'] ?? '',
                $product['path'] ?? '',
                $product['store_id'] ?? '',
            );
        }

        return implode("\n", $lines);
    }
}
