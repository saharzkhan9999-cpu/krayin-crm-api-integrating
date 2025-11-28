<?php

return [
    'environment' => env('USPS_ENVIRONMENT', 'testing'),
    'urls' => [
        'production' => [
            'addresses' => 'https://apis.usps.com/addresses/v3',
            'oauth' => 'https://apis.usps.com/oauth2/v3/token',
        ],
        'testing' => [
            'addresses' => 'https://apis-tem.usps.com/addresses/v3',
            'oauth' => 'https://apis-tem.usps.com/oauth2/v3/token',
        ],
    ],
    'timeout' => env('USPS_TIMEOUT', 30),
    'cache' => [
        'token_ttl' => 3500,
        'address_ttl' => 86400, // 24 hours for address caching
    ],
    'validation' => [
        'states' => [
            'AA', 'AE', 'AL', 'AK', 'AP', 'AS', 'AZ', 'AR', 'CA', 'CO', 
            'CT', 'DE', 'DC', 'FM', 'FL', 'GA', 'GU', 'HI', 'ID', 'IL', 
            'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MH', 'MD', 'MA', 'MI', 
            'MN', 'MS', 'MO', 'MP', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 
            'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PW', 'PA', 'PR', 'RI', 
            'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VI', 'VA', 'WA', 'WV', 
            'WI', 'WY'
        ],
        'zip_code_pattern' => '^\d{5}$',
        'zip_plus4_pattern' => '^\d{4}$',
    ],
];