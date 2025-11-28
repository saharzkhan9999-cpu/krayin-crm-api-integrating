<?php

return [
    'carriers' => [
        'usps' => [
            'active' => true,
            'title' => 'USPS',
            'description' => 'United States Postal Service',
            'rate_type' => 'per_unit',
            'class' => \Webkul\Shipping\Carriers\Usps::class,
        ],
    ],
];