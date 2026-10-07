<?php

namespace App\Kafka\MainHandlers;

use Junges\Kafka\Contracts\ConsumerMessage;
use Illuminate\Support\Facades\Log;
use App\Kafka\Handlers\OrderCreatedHandler;
use App\Kafka\Handlers\StockReleaseHandler;

class OrderEventsHandler
{
    public function __invoke(ConsumerMessage $message): void
    {
        $headers = $message->getHeaders();
        $eventType = $headers['event-type'] ?? null;

        match ($eventType) {
            // Создание заказа -> Резервируем остаток
            'order.created' => app(OrderCreatedHandler::class)($message),

            // Отмена заказа / ошибка оплаты -> Возвращаем остаток
            'stock.release_requested' => app(StockReleaseHandler::class)($message),

            default => Log::debug("Игнорируем неизвестное событие в order.events: {$eventType}")
        };
    }
}