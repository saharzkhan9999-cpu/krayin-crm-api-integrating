<?php

return [
    /*
    |--------------------------------------------------------------------------
    | USPS Addresses API Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration is used for USPS Address Validation, City/State Lookup,
    | and ZIP Code Lookup APIs.
    |
    */
    
    'base_url' => env('USPS_ADDRESSES_BASE_URL', 'https://apis.usps.com/addresses/v3'),
    
    'client_id' => env('USPS_ADDRESSES_CLIENT_ID'),
    
    'client_secret' => env('USPS_ADDRESSES_CLIENT_SECRET'),
    
    'sandbox' => env('USPS_ADDRESSES_SANDBOX', false),
    
    'sandbox_url' => env('USPS_ADDRESSES_SANDBOX_URL', 'https://apis-tem.usps.com/addresses/v3'),
    
    'timeout' => env('USPS_ADDRESSES_TIMEOUT', 30),
    
    'retry_attempts' => env('USPS_ADDRESSES_RETRY_ATTEMPTS', 3),
    
    'cache_ttl' => env('USPS_ADDRESSES_CACHE_TTL', 3500), // seconds
    
    'endpoints' => [
        'address_standardization' => '/address',
        'city_state' => '/city-state',
        'zipcode' => '/zipcode',
    ],

    'oauth' => [
        'token_url' => '/oauth2/v3/token',
        'scopes' => 'addresses',
    ],
];