<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stock;

class StockSeeder extends Seeder
{
    /**
     * Запуск сидера для заполнения остатков товаров на складах.
     */
    public function run(): void
    {
        $stocks = [
            [
                'product_id' => 1,
                'store_id'   => 1,
                'quantity'   => 100,
                'reserved'   => 0,
            ],
            [
                'product_id' => 1,
                'store_id'   => 2,
                'quantity'   => 50,
                'reserved'   => 5,
            ],
            [
                'product_id' => 2,
                'store_id'   => 1,
                'quantity'   => 200,
                'reserved'   => 10,
            ],
            [
                'product_id' => 3,
                'store_id'   => 1,
                'quantity'   => 0, // Нарочно 0 для тестирования кейсов с нехваткой товара
                'reserved'   => 0,
            ],
        ];

        foreach ($stocks as $stock) {
            Stock::updateOrCreate(
                [
                    'product_id' => $stock['product_id'],
                    'store_id'   => $stock['store_id'],
                ],
                [
                    'quantity' => $stock['quantity'],
                    'reserved' => $stock['reserved'],
                ]
            );
        }
    }
}