<?php

return [
    /*
    |--------------------------------------------------------------------------
    | JWT secret
    |--------------------------------------------------------------------------
    */
    'secret' => env('JWT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Access token lifetime in minutes
    |--------------------------------------------------------------------------
    */
    'ttl' => (int) env('JWT_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Refresh token lifetime in seconds
    |--------------------------------------------------------------------------
    */
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 60 * 24 * 30),

    /*
    |--------------------------------------------------------------------------
    | JWT algorithm
    |--------------------------------------------------------------------------
    */
    'algorithm' => env('JWT_ALGO'),

    /*
    |--------------------------------------------------------------------------
    | Cookie domain
    |--------------------------------------------------------------------------
    | Leave empty for host-only cookies (required for app.localhost — browsers
    | reject Domain=.localhost). Set only if using a real shared parent domain.
    */
    'cookie_domain' => env('JWT_COOKIE_DOMAIN') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Cookie Secure flag
    |--------------------------------------------------------------------------
    | When true (or when APP_ENV=production), JWT cookies are marked Secure.
    | Enable for local HTTPS (e.g. https://app.localhost via Traefik).
    */
    'cookie_secure' => filter_var(
        env('JWT_COOKIE_SECURE', env('APP_ENV') === 'production' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN
    ),
];



