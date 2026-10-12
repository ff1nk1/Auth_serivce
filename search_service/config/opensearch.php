<?php

return [
    'hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('OPENSEARCH_HOSTS', 'http://search_opensearch:9200'))
    ))),

    'indices' => [
        'products' => env('OPENSEARCH_PRODUCTS_INDEX', 'products'),
        'categories' => env('OPENSEARCH_CATEGORIES_INDEX', 'categories'),
    ],
];
