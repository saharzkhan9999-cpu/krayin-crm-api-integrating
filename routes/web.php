<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Http\Controllers\UspsAddressController;
use App\Http\Controllers\UspsLabelsController;
use App\Http\Controllers\UspsPricesController;
use Webkul\Shipping\Facades\USPSPayment;
use Webkul\Shipping\Facades\USPSLabels;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application.
| These routes are loaded by the RouteServiceProvider within a group
| which contains the "web" middleware group.
|
*/

Route::get('/', function () {
    return view('welcome');
});

/////////////////////////////////////////////////////////////////
// USPS Service Groups
/////////////////////////////////////////////////////////////////

Route::prefix('usps')->group(function () {
    
    //////////////////////////////////////////////////////////////////
    // USPS Debug & Test Routes
    //////////////////////////////////////////////////////////////////
    Route::prefix('debug')->group(function () {
        Route::get('/json-test', function () {
            // Your existing JSON test logic
        })->name('usps.debug.json_test');
        
        Route::get('/manual-json-test', function () {
            // Your existing manual test logic
        })->name('usps.debug.manual_test');
        
        Route::get('/token-test', function () {
            // Your token test logic
        })->name('usps.debug.token_test');
        
        Route::get('/url-check', function () {
            // Your URL check logic
        })->name('usps.debug.url_check');
        
        Route::get('/service-check', function () {
            // Your service check logic
        })->name('usps.debug.service_check');
    });

    //////////////////////////////////////////////////////////////////
    // USPS Payment Routes
    //////////////////////////////////////////////////////////////////
    Route::prefix('payment')->group(function () {
        Route::get('/config', function () {
            // Payment config logic
        })->name('usps.payment.config');
        
        Route::get('/token-test', function () {
            // Payment token test logic
        })->name('usps.payment.token_test');
        
        Route::get('/auth-test', function () {
            // Payment auth test logic
        })->name('usps.payment.auth_test');
        
        Route::get('/connection-test', function () {
            // Payment connection test logic
        })->name('usps.payment.connection_test');
    });

    //////////////////////////////////////////////////////////////////
    // USPS Address Validation Routes
    //////////////////////////////////////////////////////////////////
    Route::prefix('address')->group(function () {
        Route::post('/validate', [UspsAddressController::class, 'validateCustomerAddress'])
             ->name('usps.address.validate');
        Route::post('/city-state', [UspsAddressController::class, 'getCityStateFromZip'])
             ->name('usps.address.city_state');
        Route::post('/zipcode', [UspsAddressController::class, 'getZipFromAddress'])
             ->name('usps.address.zipcode');
        Route::get('/test-all', [UspsAddressController::class, 'testAllFeatures'])
             ->name('usps.address.test_all');
        Route::post('/clear-cache', [UspsAddressController::class, 'clearCache'])
             ->name('usps.address.clear_cache');
    });

    //////////////////////////////////////////////////////////////////
    // USPS Labels API Routes
    //////////////////////////////////////////////////////////////////
    Route::prefix('labels')->group(function () {
        // Label Operations
        Route::post('/create', [UspsLabelsController::class, 'createDomesticLabel'])
             ->name('usps.labels.create');
        Route::post('/create-return', [UspsLabelsController::class, 'createReturnLabel'])
             ->name('usps.labels.create_return');
        Route::post('/cancel', [UspsLabelsController::class, 'cancelLabel'])
             ->name('usps.labels.cancel');
        Route::patch('/edit/{trackingNumber}', [UspsLabelsController::class, 'editLabel'])
             ->name('usps.labels.edit');
        Route::post('/reprint/{trackingNumber}', [UspsLabelsController::class, 'reprintLabel'])
             ->name('usps.labels.reprint');
        
        // Label Testing & Debug
        Route::get('/test', [UspsLabelsController::class, 'testLabelsApi'])
             ->name('usps.labels.test');
        Route::post('/create-test', function (Request $request) {
            // Your label creation test logic
        })->name('usps.labels.create_test');
        Route::get('/create-test-get', function () {
            // Your GET label test logic
        })->name('usps.labels.create_test_get');
        Route::get('/production-test', function () {
            // Your production label test logic
        })->name('usps.labels.production_test');
        Route::get('/force-bypass-test', function () {
            // Your force bypass test logic
        })->name('usps.labels.force_bypass_test');
        
        // Label Service Management
        Route::get('/service-test', function () {
            // Your labels service test logic
        })->name('usps.labels.service_test');
        Route::post('/clear-cache', [UspsLabelsController::class, 'clearCache'])
             ->name('usps.labels.clear_cache');
        Route::get('/payment-token-debug', function () {
            // Your payment token debug logic
        })->name('usps.labels.payment_token_debug');
    });

    //////////////////////////////////////////////////////////////////
    // USPS Prices API Routes
    //////////////////////////////////////////////////////////////////
    Route::prefix('prices')->group(function () {
        // Price Calculations
        Route::post('/base-rates', [UspsPricesController::class, 'searchBaseRates'])
             ->name('usps.prices.base_rates');
        Route::post('/extra-service-rates', [UspsPricesController::class, 'searchExtraServiceRates'])
             ->name('usps.prices.extra_service_rates');
        Route::post('/total-rates', [UspsPricesController::class, 'searchTotalRates'])
             ->name('usps.prices.total_rates');
        Route::post('/base-rates-list', [UspsPricesController::class, 'searchBaseRatesList'])
             ->name('usps.prices.base_rates_list');
        Route::post('/letter-rates', [UspsPricesController::class, 'searchLetterRates'])
             ->name('usps.prices.letter_rates');

        // Testing & Debug
        Route::get('/test', [UspsPricesController::class, 'testPricesApi'])
             ->name('usps.prices.test');
        Route::get('/test-connection', [UspsPricesController::class, 'testConnection'])
             ->name('usps.prices.test_connection');
        Route::get('/debug-oauth', [UspsPricesController::class, 'debugOAuth'])
             ->name('usps.prices.debug_oauth');
        Route::get('/clear-cache', [UspsPricesController::class, 'clearCache'])
             ->name('usps.prices.clear_cache');
        Route::post('/clear-cache', [UspsPricesController::class, 'clearCachePost'])
             ->name('usps.prices.clear_cache_post');
    });

    //////////////////////////////////////////////////////////////////
    // USPS File Management Routes
    //////////////////////////////////////////////////////////////////
    Route::prefix('files')->group(function () {
        // File Downloads
        Route::get('/download-label/{filename}', function ($filename) {
            // Your label download logic
        })->name('usps.files.download_label');
        
        Route::get('/download-receipt/{filename}', function ($filename) {
            // Your receipt download logic
        })->name('usps.files.download_receipt');
        
        Route::get('/download/{type}/{filename}', function ($type, $filename) {
            // Your enhanced download logic
        })->name('usps.files.download');

        // File Management
        Route::get('/list', function () {
            // Your file listing logic
        })->name('usps.files.list');
        
        Route::delete('/cleanup', function () {
            // Your cleanup logic
        })->name('usps.files.cleanup');
    });

    //////////////////////////////////////////////////////////////////
    // USPS Cache Management
    //////////////////////////////////////////////////////////////////
    Route::prefix('cache')->group(function () {
        Route::get('/clear', function () {
            // Your cache clear logic
        })->name('usps.cache.clear');
        
        Route::get('/labels-clear', function () {
            // Your labels cache clear logic
        })->name('usps.cache.labels_clear');
    });
});

/////////////////////////////////////////////////////////////////
// Shipping Module Routes (Clean Group)
/////////////////////////////////////////////////////////////////
Route::group(['prefix' => 'shipping'], function () {
    // Label creation endpoints
    Route::post('/label', [ShippingController::class, 'createShippingLabel'])
         ->name('shipping.label.create');
    Route::post('/label/simple', [ShippingController::class, 'createSimpleLabel'])
         ->name('shipping.label.simple');
    Route::post('/label/return', [ShippingController::class, 'createReturnLabel'])
         ->name('shipping.label.return');
    Route::delete('/label/{trackingNumber}', [ShippingController::class, 'cancelLabel'])
         ->name('shipping.label.cancel');
    
    // Utility endpoints
    Route::get('/test', [ShippingController::class, 'testConnection'])
         ->name('shipping.test');
    Route::post('/address/validate', [ShippingController::class, 'validateAddress'])
         ->name('shipping.address.validate');
    
    // Web interface routes (optional - for admin panel)
    Route::get('/dashboard', function () {
        return view('shipping::dashboard');
    })->name('shipping.dashboard');
    
    Route::get('/labels', function () {
        return view('shipping::labels.index');
    })->name('shipping.labels.index');
    
    Route::get('/labels/create', function () {
        return view('shipping::labels.create');
    })->name('shipping.labels.create');
});

/////////////////////////////////////////////////////////////////
// Service Test Route (Keep at root level for easy access)
/////////////////////////////////////////////////////////////////
Route::get('/test-usps-services', function () {
    try {
        // Test service registration
        $services = [
            'payment' => app('usps.payment') instanceof \Webkul\Shipping\Services\USPSPaymentService,
            'label' => app('usps.labels') instanceof \Webkul\Shipping\Services\USPSLabelService,
            'address' => app('usps.addresses') instanceof \Webkul\Shipping\Services\USPSAddressService,
        ];

        // Test configuration
        $config = config('usps');

        return response()->json([
            'success' => true,
            'services_registered' => $services,
            'config_loaded' => !empty($config),
            'environment' => config('usps.environment'),
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

/////////////////////////////////////////////////////////////////
// Working Label Creation Route (Keep for quick testing)
/////////////////////////////////////////////////////////////////
Route::get('/usps-create-label', function () {
    try {
        $labelsService = app('usps.labels');
        
        $labelData = [
            'to_address' => [
                'firstName' => 'Test',
                'lastName' => 'User',
                'streetAddress' => '123 Main St',
                'secondaryAddress' => 'Apt 1',
                'city' => 'NEW YORK',
                'state' => 'NY',
                'ZIPCode' => '10001',
                'phone' => '+12125551212',
                'email' => 'test@example.com'
            ],
            'from_address' => [
                'firstName' => 'Test',
                'lastName' => 'Sender',
                'streetAddress' => '456 Oak St',
                'secondaryAddress' => 'Suite 100',
                'city' => 'LOS ANGELES', 
                'state' => 'CA',
                'ZIPCode' => '90001',
                'phone' => '+13105551212',
                'email' => 'sender@example.com'
            ],
            'package_description' => [
                'mailClass' => 'USPS_GROUND_ADVANTAGE',
                'rateIndicator' => 'SP',
                'weight' => 1.0,
                'length' => 10.0,
                'height' => 8.0,
                'width' => 4.0,
                'processingCategory' => 'MACHINABLE',
                'mailingDate' => now()->addDay()->format('Y-m-d'),
                'destinationEntryFacilityType' => 'NONE'
            ],
            'ignore_bad_address' => true
        ];

        $result = $labelsService->createDomesticLabel($labelData);
        
        return response()->json([
            'success' => true,
            'label' => $result
        ]);
        
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});