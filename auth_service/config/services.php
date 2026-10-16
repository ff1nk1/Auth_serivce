<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'catalog' => [
        'url' => env('CATALOG_SERVICE_URL', 'http://catalog_service_app:8002'),
        'paths' => [
            'storefront' => '/api/catalog',
            'admin_api' => '/api/admin',
            'admin_panel' => '/admin-catalog',
            'upload_url' => '/api/get-upload-url',
        ],
    ],

    'notification' => [
        'url' => env('NOTIFICATION_SERVICE_URL', 'http://notification_service_app:8003'),
        'paths' => [
            'index' => '/api/notifications',
        ],
    ],

    'order' => [
        'url' => env('ORDER_SERVICE_URL', 'http://order_service_app:8004'),
        'paths' => [
            'customer' => '/api/orders',
            'admin' => '/api/admin/orders',
            'stripe_webhook' => '/api/webhooks/stripe',
        ],
    ],

    'search' => [
        'url' => env('SEARCH_SERVICE_URL', 'http://search_service_app:8005'),
        'paths' => [
            'search' => '/api/search',
            'autocomplete' => '/api/search/autocomplete',
        ],
    ],

    'analytic' => [
        'url' => env('ANALYTIC_SERVICE_URL', 'http://analytic_service_api:8006'),
        'paths' => [
            'index' => '/analytics',
        ],
    ],

    'recommendation' => [
        'url' => env('RECOMMENDATION_SERVICE_URL', 'http://recommendation_service_app:8007'),
        'paths' => [
            'api' => '/api/recommendations',
            'health' => '/api/recommendations/health',
            'reindex' => '/api/recommendations/reindex',
        ],
    ],

];
