<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // ✅ USPS API Configuration - UPDATED COMPLETE VERSION
    'usps' => [
        // Authentication
        'client_id'     => env('USPS_CLIENT_ID'),
        'client_secret' => env('USPS_CLIENT_SECRET'),
        
        // Environment
        'environment'   => env('USPS_ENVIRONMENT', 'testing'),
        'timeout'       => env('USPS_TIMEOUT', 30),
        
        // OAuth
       'oauth_token_url' => env('USPS_OAUTH_TOKEN_URL', 'https://apis-tem.usps.com/oauth2/v3/token'),
        
        // Address API URLs
        'address_base_url' => env('USPS_BASE_URL', 'https://apis.usps.com/addresses/v3'),
        'address_test_url' => env('USPS_TEST_URL', 'https://apis-tem.usps.com/addresses/v3'),
        
        // Prices API URLs
        'prices_base_url' => env('USPS_PRICES_BASE_URL', 'https://apis.usps.com/prices/v3'),
        'prices_test_url' => env('USPS_PRICES_TEST_URL', 'https://apis-tem.usps.com/prices/v3'),
        
        // Labels API URLs
        'labels_base_url' => env('USPS_LABELS_BASE_URL', 'https://apis.usps.com/labels/v3'),
        'labels_test_url' => env('USPS_LABELS_TEST_URL', 'https://apis-tem.usps.com/labels/v3'),
        
        // Account Information (Optional but recommended for commercial rates)
        'account_number' => env('USPS_ACCOUNT_NUMBER'),
        'account_type'   => env('USPS_ACCOUNT_TYPE', 'COMMERCIAL'),
        
        // Letter Rates Configuration - NEW
        'default_letter_origin' => env('USPS_DEFAULT_LETTER_ORIGIN', '10001'),
        'default_letter_dest' => env('USPS_DEFAULT_LETTER_DEST', '10002'),
    ],

];