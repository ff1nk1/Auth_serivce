<?php

namespace App\Services;

use App\Models\OutboxEvent;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function createStock(array $data): Stock
    {
        return DB::transaction(function () use ($data) {
            $stock = Stock::create([
                'product_id' => $data['product_id'],
                'store_id' => $data['store_id'],
                'quantity' => (int) $data['quantity'],
                'reserved' => (int) ($data['reserved'] ?? 0),
            ]);

            $this->queueStockChanged($stock);

            return $stock->load(['product', 'store']);
        });
    }

    public function updateQuantity(Stock $stock, int $quantity): Stock
    {
        return DB::transaction(function () use ($stock, $quantity) {
            $stock->quantity = $quantity;
            $stock->save();

            $this->queueStockChanged($stock);

            return $stock->fresh(['product', 'store']);
        });
    }

    private function queueStockChanged(Stock $stock): void
    {
        OutboxEvent::create([
            'topic' => 'catalog.events',
            'event_type' => 'stock.changed',
            'payload' => [
                'product_id' => $stock->product_id,
                'store_id' => $stock->store_id,
                'quantity' => $stock->quantity,
            ],
        ]);
    }
}
