<?php

namespace App\Providers;

use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\Gemini\GeminiLlmClient;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LlmClient::class, function (): LlmClient {
            $driver = (string) config('llm.driver', 'gemini');

            return match ($driver) {
                'gemini' => new GeminiLlmClient(
                    apiKey: (string) config('llm.gemini.api_key', ''),
                    baseUrl: (string) config('llm.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'),
                    chatModel: (string) config('llm.gemini.chat_model', 'gemini-1.5-pro'),
                    embeddingModel: (string) config('llm.gemini.embedding_model', 'text-embedding-004'),
                    temperature: (float) config('llm.gemini.temperature', 0.3),
                    embeddingDimensions: (int) config('llm.gemini.embedding_dimensions', 768),
                ),
                default => throw new InvalidArgumentException("Unsupported LLM_DRIVER [{$driver}]"),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
