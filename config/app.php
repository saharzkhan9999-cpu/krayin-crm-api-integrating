<?php

use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    */
    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    */
    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    */
    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Admin URL
    |--------------------------------------------------------------------------
    */
    'admin_path' => env('APP_ADMIN_PATH', 'admin'),

    'asset_url' => env('ASSET_URL', null),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    */
    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    */
    'locale' => env('APP_LOCALE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Available Locales
    |--------------------------------------------------------------------------
    */
    'available_locales' => [
        'ar'    => 'Arabic',
        'en'    => 'English',
        'es'    => 'Español',
        'fa'    => 'Persian',
        'pt_BR' => 'Portuguese',
        'tr'    => 'Türkçe',
        'vi'    => 'Vietnamese',
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    */
    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    */
    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Base Currency Code
    |--------------------------------------------------------------------------
    */
    'currency' => env('APP_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    */
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    */
    'providers' => ServiceProvider::defaultProviders()->merge([
        /*
         * Package Service Providers...
         */
        Barryvdh\DomPDF\ServiceProvider::class,
        Konekt\Concord\ConcordServiceProvider::class,
        Prettus\Repository\Providers\RepositoryServiceProvider::class,

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

        /*
         * Webkul Service Providers...
         */
        Webkul\Activity\Providers\ActivityServiceProvider::class,
        Webkul\Admin\Providers\AdminServiceProvider::class,
        Webkul\Attribute\Providers\AttributeServiceProvider::class,
        Webkul\Automation\Providers\WorkflowServiceProvider::class,
        Webkul\Contact\Providers\ContactServiceProvider::class,
        Webkul\Core\Providers\CoreServiceProvider::class,
        Webkul\DataGrid\Providers\DataGridServiceProvider::class,
        Webkul\DataTransfer\Providers\DataTransferServiceProvider::class,
        Webkul\EmailTemplate\Providers\EmailTemplateServiceProvider::class,
        Webkul\Email\Providers\EmailServiceProvider::class,
        Webkul\Marketing\Providers\MarketingServiceProvider::class,
        Webkul\Installer\Providers\InstallerServiceProvider::class,
        Webkul\Lead\Providers\LeadServiceProvider::class,
        Webkul\Product\Providers\ProductServiceProvider::class,
        Webkul\Quote\Providers\QuoteServiceProvider::class,
        Webkul\Tag\Providers\TagServiceProvider::class,
        Webkul\User\Providers\UserServiceProvider::class,
        Webkul\Warehouse\Providers\WarehouseServiceProvider::class,
        Webkul\WebForm\Providers\WebFormServiceProvider::class,
        
        /*
         * Shipping Service Providers
         * Register all USPS service providers
         */
        Webkul\Shipping\Providers\ShippingServiceProvider::class,
              
    ])->toArray(),

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    */
    'aliases' => Facade::defaultAliases()->merge([
        // All your USPS facade aliases
        'USPSPayment' => Webkul\Shipping\Facades\USPSPayment::class,
        'USPSLabels' => Webkul\Shipping\Facades\USPSLabels::class,
        'USPSAddresses' => Webkul\Shipping\Facades\USPSAddresses::class,
    ])->toArray(),

];