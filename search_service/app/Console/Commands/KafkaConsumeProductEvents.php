<?php

namespace App\Console\Commands;

use App\Kafka\Handlers\ProductEventsHandler;
use Illuminate\Console\Command;
use Junges\Kafka\Facades\Kafka;

class KafkaConsumeProductEvents extends Command
{
    protected $signature = 'kafka:consume:product-events';

    protected $description = 'Консьюмер Search Service для product.created / product.updated / product.deleted';

    public function handle(): int
    {
        $this->info('Запуск консьюмера для топиков product.created, product.updated, product.deleted...');

        $consumer = Kafka::consumer(['product.created', 'product.updated', 'product.deleted'])
            ->withBrokers(config('kafka.brokers', 'kafka:29092'))
            ->withConsumerGroupId('search-service-products-v1')
            ->withHandler(app(ProductEventsHandler::class))
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
