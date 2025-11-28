<?php

namespace Webkul\Shipping\Providers;

use Illuminate\Support\ServiceProvider;
use Webkul\Shipping\Services\USPSLabelService;
use Webkul\Shipping\Services\USPSPaymentService;
use Webkul\Shipping\Services\USPSAddressService;
use Webkul\Shipping\Services\UspsInternationalService;
use Webkul\Shipping\Contracts\USPSLabelServiceInterface;
use Webkul\Shipping\Contracts\UspsInternationalInterface;

class ShippingServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register USPS Payment Service
        $this->app->singleton('usps.payment', function ($app) {
            return new USPSPaymentService();
        });

        // Register USPS Address Service
        $this->app->singleton('usps.address', function ($app) {
            return new USPSAddressService();
        });

        // Register USPS Label Service
        $this->app->singleton('usps.label', function ($app) {
            $paymentService = $app->make('usps.payment');
            return new USPSLabelService($paymentService);
        });

        // Register USPS International Service
        $this->app->singleton('usps.international', function ($app) {
            $paymentService = $app->make('usps.payment');
            return new UspsInternationalService($paymentService);
        });

        // Bind interfaces
        $this->app->bind(USPSLabelServiceInterface::class, 'usps.label');
        $this->app->bind(UspsInternationalInterface::class, 'usps.international');

        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/usps.php',
            'shipping.usps'
        );
    }

    public function boot()
    {
        config(['usps' => config('shipping.usps')]);
        
        $this->publishes([
            __DIR__ . '/../config/usps.php' => config_path('shipping/usps.php'),
        ], 'usps-shipping-config');
    }
}