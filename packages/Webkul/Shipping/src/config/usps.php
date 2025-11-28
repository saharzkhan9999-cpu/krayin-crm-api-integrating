<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Environment: testing or production
    |--------------------------------------------------------------------------
    */
    'environment' => env('USPS_ENVIRONMENT', 'testing'),

    /*
    |--------------------------------------------------------------------------
    | USPS API Credentials
    |--------------------------------------------------------------------------
    */
    'credentials' => [
        'client_id' => env('USPS_CLIENT_ID'),
        'client_secret' => env('USPS_CLIENT_SECRET'),
        'consumer_key' => env('USPS_CONSUMER_KEY'),
        'consumer_secret' => env('USPS_CONSUMER_SECRET'),
         'auth_token' => env('USPS_AUTH_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    */
    'api' => [
        'timeout' => env('USPS_TIMEOUT', 30),
        'retry_attempts' => env('USPS_RETRY_ATTEMPTS', 3),
        'retry_delay' => 1000,
        'batch_size' => 100, // For batch address validation
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'token_ttl' => 3500,
        'rate_limit_ttl' => 3600,
        'address_ttl' => 86400, // Increased for better performance
        'label_ttl' => 86400,
    ],

    /*
    |--------------------------------------------------------------------------
    | Service Endpoints
    |--------------------------------------------------------------------------
    */
    'services' => [
        'addresses' => [
            'base_url' => [
                'production' => 'https://apis.usps.com/addresses/v3',
                'testing' => 'https://apis-tem.usps.com/addresses/v3',
            ],
            'endpoints' => [
                'standardization' => '/address',
                'city_state' => '/city-state',
                'zipcode' => '/zipcode',
            ],
        ],
        
        'labels' => [
            'base_url' => [
                'production' => 'https://apis.usps.com/labels/v3',
                'testing' => 'https://apis-tem.usps.com/labels/v3',
            ],
            'endpoints' => [
                'create_label' => '/label',
                'create_return_label' => '/return-label',
                'cancel_label' => '/label/{trackingNumber}',
                'edit_label' => '/label/{trackingNumber}',
                'reprint_label' => '/label-reprint/{trackingNumber}',
                'create_indicia' => '/indicia',
                'create_indicia_imb' => '/indicia/imb',
                'cancel_indicia_imb' => '/indicia/imb/{imb}',
                'branding_upload' => '/branding',
                'branding_list' => '/branding',
                'branding_single' => '/branding/{imageUUID}',
                'branding_delete' => '/branding/{imageUUID}',
                'branding_patch' => '/branding/{imageUUID}',
            ],
        ],
        
        'payments' => [
            'base_url' => [
                'production' => 'https://apis.usps.com/payments/v3',
                'testing' => 'https://apis-tem.usps.com/payments/v3',
            ],
        ],

        'prices' => [
            'base_url' => [
                'production' => 'https://apis.usps.com/prices/v3',
                'testing' => 'https://apis-tem.usps.com/prices/v3',
            ],
        ],

        'international_labels' => [
                'base_url' => [
                    'production' => 'https://apis.usps.com/international-labels/v3',
                    'testing' => 'https://apis-tem.usps.com/international-labels/v3',
                ],
            ],
        
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Endpoints
    |--------------------------------------------------------------------------
    */
    'oauth' => [
        'production' => 'https://apis.usps.com/oauth2/v3/token',
        'testing' => 'https://apis-tem.usps.com/oauth2/v3/token',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values for Labels & Shipping
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'image_type' => 'PDF',
        'label_type' => '4X6LABEL', 
        'receipt_option' => 'SAME_PAGE',
        'mail_class' => 'USPS_GROUND_ADVANTAGE',
        'processing_category' => 'MACHINABLE',
        'weight_uom' => 'lb',
        'dimensions_uom' => 'in',
        'rate_indicator' => 'SP',
        'destination_entry_facility_type' => 'NONE',
        'suppress_postage' => false,
        'suppress_mail_date' => true,
        'return_label' => false,
        'branding_image_format' => 'NONE',
        'shipper_visibility_method' => 'SENDER_INFORMATION',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_weight' => 70,
        'max_length' => 108,
        'max_girth' => 130,
        // Address validation specific rules
        'states' => [
            'AA', 'AE', 'AL', 'AK', 'AP', 'AS', 'AZ', 'AR', 'CA', 'CO', 
            'CT', 'DE', 'DC', 'FM', 'FL', 'GA', 'GU', 'HI', 'ID', 'IL', 
            'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MH', 'MD', 'MA', 'MI', 
            'MN', 'MS', 'MO', 'MP', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 
            'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PW', 'PA', 'PR', 'RI', 
            'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VI', 'VA', 'WA', 'WV', 
            'WI', 'WY'
        ],
        'max_street_length' => 100,
        'max_city_length' => 50,
        'max_firm_length' => 50,
        'max_first_name_length' => 50,
        'max_last_name_length' => 50,
        'max_secondary_address_length' => 50,
        'max_customer_reference_length' => 30,
        'max_customer_references' => 4,
        'max_extra_services' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limiting' => [
        'calls_per_hour' => 60,
        'retry_after' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Information
    |--------------------------------------------------------------------------
    */
    'account' => [
        'payer_crid' => env('USPS_PAYER_CRID', '39947637'),
        'payer_mid' => env('USPS_PAYER_MID', '903248668'),
        'label_owner_crid' => env('USPS_LABEL_OWNER_CRID', '39947637'),
        'label_owner_mid' => env('USPS_LABEL_OWNER_MID', '903248668'),
        'account_number' => env('USPS_ACCOUNT_NUMBER', '1000344153'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Defaults
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'default_letter_origin' => env('USPS_DEFAULT_LETTER_ORIGIN', '10001'),
        'default_letter_dest' => env('USPS_DEFAULT_LETTER_DEST', '10002'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Labels API Specific Configuration
    |--------------------------------------------------------------------------
    */
    'labels' => [
        /*
        |--------------------------------------------------------------------------
        | Supported Mail Classes
        |--------------------------------------------------------------------------
        */
        'mail_classes' => [
            'USPS_GROUND_ADVANTAGE',
            'PRIORITY_MAIL',
            'PRIORITY_MAIL_EXPRESS',
            'FIRST_CLASS',
            'MEDIA_MAIL',
            'LIBRARY_MAIL',
            'PARCEL_SELECT',
            'BOUND_PRINTED_MATERIAL',
            'USPS_CONNECT_LOCAL',
            'USPS_CONNECT_REGIONAL',
            'USPS_CONNECT_MAIL',
        ],

        /*
        |--------------------------------------------------------------------------
        | Supported Processing Categories
        |--------------------------------------------------------------------------
        */
        'processing_categories' => [
            'LETTERS',
            'FLATS',
            'MACHINABLE',
            'NONSTANDARD',
            'IRREGULAR',
            'NON_MACHINABLE',
        ],

        /*
        |--------------------------------------------------------------------------
        | Supported Image Types
        |--------------------------------------------------------------------------
        */
        'image_types' => [
            'PDF',
            'TIFF',
            'JPG',
            'PNG',
            'GIF',
            'SVG',
            'ZPL203DPI',
            'ZPL300DPI',
            'LABEL_BROKER',
            'NONE',
        ],

        /*
        |--------------------------------------------------------------------------
        | Supported Label Types
        |--------------------------------------------------------------------------
        */
        'label_types' => [
            '4X4LABEL',
            '4X5LABEL',
            '4X6LABEL',
            '6X4LABEL',
            '2X7LABEL',
        ],

        /*
        |--------------------------------------------------------------------------
        | Supported Receipt Options
        |--------------------------------------------------------------------------
        */
        'receipt_options' => [
            'SAME_PAGE',
            'SEPARATE_PAGE',
            'NONE',
        ],

        /*
        |--------------------------------------------------------------------------
        | Supported Destination Entry Facility Types
        |--------------------------------------------------------------------------
        */
        'destination_entry_facility_types' => [
            'NONE',
            'DESTINATION_NETWORK_DISTRIBUTION_CENTER',
            'DESTINATION_SECTIONAL_CENTER_FACILITY',
            'DESTINATION_DELIVERY_UNIT',
            'DESTINATION_SERVICE_HUB',
        ],

        /*
        |--------------------------------------------------------------------------
        | Supported Rate Indicators
        |--------------------------------------------------------------------------
        */
        'rate_indicators' => [
            '3D', '3N', '3R', '5D', 'BA', 'BB', 'CP', 'CM', 'DC', 'DE',
            'DF', 'DN', 'DR', 'E4', 'E6', 'FA', 'FB', 'FE', 'FP', 'FS',
            'LC', 'LF', 'LL', 'LO', 'LS', 'NP', 'OS', 'P5', 'P6', 'P7',
            'P8', 'P9', 'Q6', 'Q7', 'Q8', 'Q9', 'Q0', 'PA', 'PL', 'PM',
            'PR', 'SN', 'SP', 'SR',
        ],

        /*
        |--------------------------------------------------------------------------
        | Extra Services Configuration
        |--------------------------------------------------------------------------
        */
        'extra_services' => [
            365 => 'Global Direct Entry',
            415 => 'USPS Label Delivery Service',
            480 => 'Tracking Plus 6 Months',
            481 => 'Tracking Plus 1 Year',
            482 => 'Tracking Plus 3 Years',
            483 => 'Tracking Plus 5 Years',
            484 => 'Tracking Plus 7 Years',
            485 => 'Tracking Plus 10 Years',
            486 => 'Tracking Plus Signature 3 Years',
            487 => 'Tracking Plus Signature 5 Years',
            488 => 'Tracking Plus Signature 7 Years',
            489 => 'Tracking Plus Signature 10 Years',
            810 => 'Hazardous Materials - Air Eligible Ethanol',
            811 => 'Hazardous Materials - Class 1 – Toy Propellant/Safety Fuse Package',
            812 => 'Hazardous Materials - Class 3 - Flammable and Combustible Liquids',
            813 => 'Hazardous Materials - Class 7 – Radioactive Materials',
            814 => 'Hazardous Materials - Class 8 – Air Eligible Corrosive Materials',
            815 => 'Hazardous Materials - Class 8 – Nonspillable Wet Batteries',
            816 => 'Hazardous Materials - Class 9 - Lithium Battery Marked Ground Only',
            817 => 'Hazardous Materials - Class 9 - Lithium Battery Returns',
            818 => 'Hazardous Materials - Class 9 - Marked Lithium Batteries',
            819 => 'Hazardous Materials - Class 9 – Dry Ice',
            820 => 'Hazardous Materials - Class 9 – Unmarked Lithium Batteries',
            821 => 'Hazardous Materials - Class 9 – Magnetized Materials',
            822 => 'Hazardous Materials - Division 4.1 – Mailable Flammable Solids and Safety Matches',
            823 => 'Hazardous Materials - Division 5.1 – Oxidizers',
            824 => 'Hazardous Materials - Division 5.2 – Organic Peroxides',
            825 => 'Hazardous Materials - Division 6.1 – Toxic Materials',
            826 => 'Hazardous Materials - Division 6.2 Biological Materials',
            827 => 'Hazardous Materials - Excepted Quantity Provision',
            828 => 'Hazardous Materials - Ground Only Hazardous Materials',
            829 => 'Hazardous Materials - Air Eligible ID8000 Consumer Commodity',
            830 => 'Hazardous Materials - Lighters',
            831 => 'Hazardous Materials - Limited Quantity Ground',
            832 => 'Hazardous Materials - Small Quantity Provision (Markings Required)',
            857 => 'Hazardous Materials',
            910 => 'Certified Mail',
            911 => 'Certified Mail Restricted Delivery',
            912 => 'Certified Mail Adult Signature Required',
            913 => 'Certified Mail Adult Signature Restricted Delivery',
            920 => 'USPS Tracking Electronic',
            921 => 'Signature Confirmation',
            922 => 'Adult Signature Required',
            923 => 'Adult Signature Restricted Delivery',
            924 => 'Signature Confirmation Restricted Delivery',
            925 => 'Priority Mail Express Insurance',
            930 => 'Insurance <= $500',
            931 => 'Insurance > $500',
            934 => 'Insurance Restricted Delivery',
            955 => 'Return Receipt',
            957 => 'Return Receipt Electronic',
            981 => 'Signature Requested (PRIORITY_MAIL_EXPRESS only)',
            986 => 'PO to Addressee (PRIORITY_MAIL_EXPRESS only)',
            991 => 'Sunday Delivery',
        ],

        /*
        |--------------------------------------------------------------------------
        | Package Content Types
        |--------------------------------------------------------------------------
        */
        'content_types' => [
            'HAZMAT',
            'CREMATED_REMAINS',
            'BEES',
            'DAY_OLD_POULTRY',
            'ADULT_BIRDS',
            'OTHER_LIVES',
            'PERISHABLE',
            'PHARMACEUTICALS',
            'MEDICAL_SUPPLIES',
            'FRUITS',
            'VEGETABLES',
            'LIVE_PLANTS',
        ],

        /*
        |--------------------------------------------------------------------------
        | Branding Image Formats
        |--------------------------------------------------------------------------
        */
        'branding_image_formats' => [
            'ONE_SQUARE',
            'TWO_SQUARES',
            'RECTANGLE',
            'NONE',
        ],

        /*
        |--------------------------------------------------------------------------
        | Shipping Visibility Methods
        |--------------------------------------------------------------------------
        */
        'shipper_visibility_methods' => [
            'SENDER_INFORMATION',
            'MID_INFORMATION',
        ],
      

         'international' => [
            'mail_classes' => [
                'FIRST-CLASS_PACKAGE_INTERNATIONAL_SERVICE',
                'PRIORITY_MAIL_INTERNATIONAL',
                'PRIORITY_MAIL_EXPRESS_INTERNATIONAL',
            ],
            'customs_content_types' => [
                'MERCHANDISE',
                'GIFT',
                'DOCUMENT',
                'COMMERCIAL_SAMPLE',
                'RETURNED_GOODS',
                'OTHER',
                'HUMANITARIAN_DONATIONS',
                'DANGEROUS_GOODS',
                'CREMATED_REMAINS',
                'NON_NEGOTIABLE_DOCUMENT',
                'MEDICAL_SUPPLIES',
                'PHARMACEUTICALS',
            ],
            'extra_services' => [
                480 => 'Tracking Plus 6 Months',
                481 => 'Tracking Plus 1 Year',
                482 => 'Tracking Plus 3 Years',
                483 => 'Tracking Plus 5 Years',
                484 => 'Tracking Plus 7 Years',
                486 => 'Tracking Plus Signature 3 Years',
                487 => 'Tracking Plus Signature 5 Years',
                488 => 'Tracking Plus Signature 7 Years',
                813 => 'Hazardous Materials - Class 7 – Radioactive Materials',
                820 => 'Hazardous Materials - Class 9 – Unmarked Lithium Batteries',
                826 => 'Hazardous Materials - Division 6.2 Biological Materials',
                857 => 'Hazardous Materials',
                930 => 'Insurance <= $500',
                931 => 'Insurance > $500',
                955 => 'Return Receipt',
            ],
        ],

    ],
];