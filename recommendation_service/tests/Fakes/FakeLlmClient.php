<?php

namespace Tests\Fakes;

use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\DTOs\SearchFilters;

class FakeLlmClient implements LlmClient
{
    public string $lastChatAnswer = 'Here are some products you might like.';

    public SearchFilters $filters;

    public function __construct()
    {
        $this->filters = new SearchFilters(query: 'sneakers');
    }

    public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        return array_fill(0, 768, 0.01);
    }

    public function chat(array $messages, ?string $systemPrompt = null): string
    {
        return $this->lastChatAnswer;
    }

    public function extractFilters(string $userMessage): SearchFilters
    {
        return $this->filters;
    }
}
