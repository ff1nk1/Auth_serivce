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

    /*
    | Synonym lines for product search analyzer (OpenSearch synonym_graph).
    | Format: "term1, term2, term3" — one group per array element.
    */
    'synonyms' => array_values(array_filter(array_map(
        'trim',
        explode("\n", (string) env('OPENSEARCH_PRODUCT_SYNONYMS', implode("\n", [
            'ноутбук, ноутбуки, laptop, notebook',
            'телефон, смартфон, phone, smartphone',
            'наушники, headphones, headset',
        ])))
    ))),
];
