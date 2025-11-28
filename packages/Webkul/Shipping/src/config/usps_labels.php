<?php

return [
    'environment' => env('USPS_ENVIRONMENT', 'testing'),
    'urls' => [
        'production' => [
            'labels' => 'https://apis.usps.com/labels/v3',
            'oauth' => 'https://apis.usps.com/oauth2/v3/token',
        ],
        'testing' => [
            'labels' => 'https://apis-tem.usps.com/labels/v3',
            'oauth' => 'https://apis-tem.usps.com/oauth2/v3/token',
        ],
    ],
    'timeout' => env('USPS_TIMEOUT', 30),
    'defaults' => [
        'image_type' => 'PDF',
        'label_type' => '4X6LABEL',
        'receipt_option' => 'SAME_PAGE',
        'mail_class' => 'USPS_GROUND_ADVANTAGE',
        'processing_category' => 'MACHINABLE',
        'weight_uom' => 'lb',
        'dimensions_uom' => 'in',
        'rate_indicator' => 'SP',
    ],
    'cache' => [
        'token_ttl' => 3500,
        'label_ttl' => 86400,
    ],
];
