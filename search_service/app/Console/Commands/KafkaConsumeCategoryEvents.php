<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\CategoryEventsHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class KafkaConsumeCategoryEvents extends Command
{
    protected $signature = 'kafka:consume:category-events';

    protected $description = 'Консьюмер Search Service для category.created / category.updated / category.deleted';

    public function handle(): int
    {
        $this->info('Запуск консьюмера для топиков category.created, category.updated, category.deleted...');

        $consumer = Kafka::consumer(['category.created', 'category.updated', 'category.deleted'])
            ->withBrokers(config('kafka.brokers', 'kafka:29092'))
            ->withConsumerGroupId('search-service-categories-v1')
            ->withHandler(app(CategoryEventsHandler::class))
            ->withAutoCommit()
            ->withOptions([
                'auto.offset.reset' => 'earliest',
            ])
            ->build();

        try {
            $consumer->consume();
        } catch (\Throwable $e) {
            $this->error('ОШИБКА KAFKA: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
