<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Junges\Kafka\Facades\Kafka;

class CatalogService
{
    /**
     * Получение всех категорий из кэша.
     */
    public function getAllCategories(): array
    {
        return Cache::tags(['categories'])->remember('all_categories', 3600, function () {
            return Category::all()->toArray();
        });
    }

    /**
     * Очистка кэша при любых изменениях категорий.
     */
    public function clearCategoryCache(): void
    {
        Cache::tags(['categories'])->flush();
    }

    public function createCategory(array $data): Category
    {
        $category = Category::create($data);
        
        $this->clearCategoryCache();
        $this->publishToTopic('category.created', $category);

        return $category;
    }

    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);
        
        $this->clearCategoryCache();
        $this->publishToTopic('category.updated', $category);

        return $category;
    }

    public function deleteCategory(Category $category): bool
    {
    // 1. Явно делаем детей корневыми и отправляем события в Kafka
        foreach ($category->children as $child) {
            $this->updateCategory($child, ['parent_id' => null]); 
        // updateCategory сам отправит 'category.updated' в Kafka
    }

    // 2. Удаляем самого родителя
        $categoryData = clone $category;
        $result = $category->delete();
    
        $this->clearCategoryCache();
        $this->publishToTopic('category.deleted', $categoryData);

        return $result;
    }

    /**
     * Публикация события в Kafka для категорий.
     */
    protected function publishToTopic(string $topic, Category $category): void
    {
        try {
            // Подгружаем родителя, если нужно передать полную структуру в событии
            $category->loadMissing(['parent']);

            Kafka::publish(config('kafka.brokers', 'kafka:29092'))
                ->onTopic($topic) 
                ->withConfigOptions([
                    'socket.timeout.ms'  => 60000,
                    'message.timeout.ms' => 60000,
                    'request.timeout.ms' => 30000,
                    'retries'            => 10,
                    'retry.backoff.ms'   => 1000,
                ])
                // Ключ гарантирует сохранение порядка обработки событий для одной категории
                ->withKafkaKey((string) $category->id)
                ->withHeaders([
                    'event-type' => $topic,
                    'source'     => 'catalog-service', // Источник события
                ])
                ->withBody([
                    'event'     => $topic,
                    'timestamp' => now()->toIso8601String(),
                    'data'      => $category->toArray(),
                ])
                ->send();

        } catch (\Throwable $e) {
            Log::error("Kafka publish failed on {$topic}", [
                'category_id' => $category->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}