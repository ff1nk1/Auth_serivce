<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Facades\Schema;

class ShopSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Очищаем таблицы перед сидированием, чтобы избежать дублей
        // Отключаем проверку внешних ключей на время очистки
        Schema::disableForeignKeyConstraints();
        Product::truncate();
        Category::truncate();
        Store::truncate();
        Schema::enableForeignKeyConstraints();

        // 2. Создаем магазины
        $storesData = ['Главный склад', 'Магазин на Ленина', 'ТЦ Галерея', 'Точка выдачи'];
        $stores = collect();
        
        foreach ($storesData as $storeName) {
            // Предполагается, что в модели Store есть поле name. 
            // Если там другие поля - добавьте их сюда.
            $stores->push(Store::create([
                'name' => $storeName,
            ]));
        }

        // 3. Создаем дерево категорий (вложенная структура)
        $categoriesTree = [
            [
                'name' => 'Электроника',
                'children' => [
                    [
                        'name' => 'Компьютеры',
                        'children' => [
                            ['name' => 'Ноутбуки'],
                            ['name' => 'Мониторы'],
                            ['name' => 'Комплектующие'],
                        ]
                    ],
                    [
                        'name' => 'Смартфоны и гаджеты',
                        'children' => [
                            ['name' => 'Смартфоны'],
                            ['name' => 'Умные часы'],
                        ]
                    ]
                ]
            ],
            [
                'name' => 'Одежда и обувь',
                'children' => [
                    [
                        'name' => 'Мужская',
                        'children' => [
                            ['name' => 'Куртки'],
                            ['name' => 'Футболки'],
                        ]
                    ],
                    [
                        'name' => 'Женская',
                        'children' => [
                            ['name' => 'Платья'],
                            ['name' => 'Джинсы'],
                        ]
                    ]
                ]
            ]
        ];

        // Рекурсивная функция для создания категорий и их детей
        $createCategories = function ($categories, $parentId = null) use (&$createCategories) {
            foreach ($categories as $catData) {
                $category = Category::create([
                    'name' => $catData['name'],
                    // Генерируем slug и добавляем random на случай совпадения имен
                    'slug' => Str::slug($catData['name']) . '-' . rand(10, 99),
                    'parent_id' => $parentId,
                ]);

                // Если есть дочерние элементы - вызываем функцию снова
                if (isset($catData['children'])) {
                    $createCategories($catData['children'], $category->id);
                }
            }
        };

        $createCategories($categoriesTree);

        // 4. Генерируем товары для конечных категорий (у которых нет подкатегорий)
        // Товары логичнее привязывать к самым нижним веткам дерева (например, к "Ноутбуки", а не к "Электроника")
        $leafCategories = Category::doesntHave('children')->get();

        foreach ($leafCategories as $category) {
            // Создаем по 15 товаров для каждой конечной категории
            for ($i = 1; $i <= 15; $i++) {
                Product::create([
                    // Привязываем к случайному магазину из созданных ранее
                    'store_id' => $stores->random()->id,
                    'category_id' => $category->id,
                    'name' => "{$category->name} " . Str::random(5) . " - Модель {$i}",
                    'description' => "Это подробное описание для потрясающего товара из категории {$category->name}. Отличное качество и гарантия.",
                    'price' => rand(1000, 150000) / 10, // Случайная цена от 100.0 до 15000.0
                    'image_url' => "https://placehold.co/600x400/png?text=" . urlencode($category->name),
                ]);
            }
        }
    }
}