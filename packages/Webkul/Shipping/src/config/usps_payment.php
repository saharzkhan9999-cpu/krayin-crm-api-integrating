<?php

return [
    'environment' => env('USPS_ENVIRONMENT', 'testing'),
    'urls' => [
        'production' => [
            'payments' => 'https://apis.usps.com/payments/v3',
            'oauth' => 'https://apis.usps.com/oauth2/v3/token',
        ],
        'testing' => [
            'payments' => 'https://apis-tem.usps.com/payments/v3', 
            'oauth' => 'https://apis-tem.usps.com/oauth2/v3/token',
        ],
    ],
    'timeout' => env('USPS_TIMEOUT', 30),
    'account' => [
        'payer_crid' => env('USPS_PAYER_CRID', '39947637'),
        'payer_mid' => env('USPS_PAYER_MID', '903248668'),
        'label_owner_crid' => env('USPS_LABEL_OWNER_CRID', '39947637'),
        'label_owner_mid' => env('USPS_LABEL_OWNER_MID', '903248668'),
        'account_number' => env('USPS_ACCOUNT_NUMBER', '1000344153'),
    ],
];
