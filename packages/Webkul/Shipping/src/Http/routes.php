<?php

use Illuminate\Support\Facades\Route;
use Webkul\Shipping\Http\Controllers\USPSAddressesController;
use Webkul\Shipping\Http\Controllers\USPSLabelController;

/*
|--------------------------------------------------------------------------
| USPS ADDRESSES API (Public API routes)
|--------------------------------------------------------------------------
*/
Route::prefix('api/usps/addresses')->group(function () {

    // Validation endpoints
    Route::post('/validate', [USPSAddressesController::class, 'validateAddress']);
    Route::post('/quick-validate', [USPSAddressesController::class, 'quickValidate']);

    // Lookup endpoints
    Route::get('/city-state', [USPSAddressesController::class, 'getCityState']);
    Route::post('/zipcode', [USPSAddressesController::class, 'getZipCode']);

    // Service endpoints
    Route::get('/test', [USPSAddressesController::class, 'testConnection']);
    Route::get('/info', [USPSAddressesController::class, 'getServiceInfo']);
});


/*
|--------------------------------------------------------------------------
| USPS LABEL MANAGEMENT (Admin Panel)
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['web', 'admin']], function () {

    Route::prefix('usps/labels')->group(function () {

        // Create labels
        Route::post('/orders/{orderId}/create', [USPSLabelController::class, 'createLabel']);
        Route::post('/orders/{orderId}/return', [USPSLabelController::class, 'createReturnLabel']);

        // Label operations
        Route::get('/{labelId}', [USPSLabelController::class, 'getLabel']);
        Route::get('/{labelId}/download', [USPSLabelController::class, 'downloadLabel']);
        Route::post('/{labelId}/cancel', [USPSLabelController::class, 'cancelLabel']);
        Route::post('/{labelId}/reprint', [USPSLabelController::class, 'reprintLabel']);

        // Order-level operations
        Route::get('/orders/{orderId}/list', [USPSLabelController::class, 'getOrderLabels']);

        // Token management
        Route::post('/refresh-token', [USPSLabelController::class, 'refreshToken']);
    });

});
