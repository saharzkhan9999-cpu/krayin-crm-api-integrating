<?php

return [
    /*
    |--------------------------------------------------------------------------
    | USPS Addresses API Configuration
    |--------------------------------------------------------------------------
    */
    'addresses' => [
        'api' => [
            'base_url' => env('USPS_BASE_URL', 'https://apis.usps.com/addresses/v3'),
            'test_url' => env('USPS_TEST_URL', 'https://apis-tem.usps.com/addresses/v3'),
            'oauth_token_url' => env('USPS_OAUTH_TOKEN_URL', 'https://apis.usps.com/oauth2/v3/token'),
            'client_id' => env('USPS_CLIENT_ID'),
            'client_secret' => env('USPS_CLIENT_SECRET'),
            'timeout' => env('USPS_TIMEOUT', 30),
            'environment' => env('USPS_ENVIRONMENT', 'production'),
        ],
        'scopes' => [
            'addresses' => 'read-only access to all addresses endpoints',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | USPS Prices API Configuration
    |--------------------------------------------------------------------------
    */
    'prices' => [
        'api' => [
            'base_url' => env('USPS_PRICES_BASE_URL', 'https://apis.usps.com/prices/v3'),
            'test_url' => env('USPS_PRICES_TEST_URL', 'https://apis-tem.usps.com/prices/v3'),
            'oauth_token_url' => env('USPS_OAUTH_TOKEN_URL', 'https://apis.usps.com/oauth2/v3/token'),
            'client_id' => env('USPS_CLIENT_ID'), // Using same credentials as addresses
            'client_secret' => env('USPS_CLIENT_SECRET'), // Using same credentials as addresses
            'timeout' => env('USPS_TIMEOUT', 30),
            'environment' => env('USPS_ENVIRONMENT', 'production'),
        ],
        'scopes' => [
            'prices' => 'get prices based on ingredients',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | USPS Labels API Configuration
    |--------------------------------------------------------------------------
    */
    'labels' => [
        'api' => [
            'base_url' => env('USPS_LABELS_BASE_URL', 'https://apis.usps.com/labels/v3'),
            'test_url' => env('USPS_LABELS_TEST_URL', 'https://apis-tem.usps.com/labels/v3'),
            'oauth_token_url' => env('USPS_OAUTH_TOKEN_URL', 'https://apis.usps.com/oauth2/v3/token'),
            'client_id' => env('USPS_CLIENT_ID'), // Using same credentials
            'client_secret' => env('USPS_CLIENT_SECRET'), // Using same credentials
            'timeout' => env('USPS_TIMEOUT', 30),
            'environment' => env('USPS_ENVIRONMENT', 'production'),
        ],
        'scopes' => [
            'labels' => 'create and manage shipping labels',
        ],
    ],
];