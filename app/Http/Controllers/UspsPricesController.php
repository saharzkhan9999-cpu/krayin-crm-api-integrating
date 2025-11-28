<?php

namespace App\Http\Controllers;

use App\Services\UspsPricesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class UspsPricesController extends Controller
{
    protected $uspsPricesService;

    public function __construct(UspsPricesService $uspsPricesService)
    {
        $this->uspsPricesService = $uspsPricesService;
    }

    /**
     * Search for Base Rates
     */
    public function searchBaseRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_zip_code' => 'required|string|size:5',
            'destination_zip_code' => 'required|string|size:5',
            'weight' => 'required|numeric|min:0.1',
            'length' => 'required|numeric|min:0.1',
            'width' => 'required|numeric|min:0.1',
            'height' => 'required|numeric|min:0.1',
            'mail_class' => 'required|string',
            'processing_category' => 'required|string',
            'rate_indicator' => 'required|string',
            'destination_entry_facility_type' => 'required|string',
            'price_type' => 'required|string',
            'mailing_date' => 'sometimes|date_format:Y-m-d',
            'account_type' => 'sometimes|string',
            'account_number' => 'sometimes|string',
            'has_nonstandard_characteristics' => 'sometimes|boolean'
        ]);

        try {
            $rateData = [
                'originZIPCode' => $validated['origin_zip_code'],
                'destinationZIPCode' => $validated['destination_zip_code'],
                'weight' => $validated['weight'],
                'length' => $validated['length'],
                'width' => $validated['width'],
                'height' => $validated['height'],
                'mailClass' => $validated['mail_class'],
                'processingCategory' => $validated['processing_category'],
                'rateIndicator' => $validated['rate_indicator'],
                'destinationEntryFacilityType' => $validated['destination_entry_facility_type'],
                'priceType' => $validated['price_type'],
                'mailingDate' => $validated['mailing_date'] ?? now()->format('Y-m-d')
            ];

            // Add optional fields
            $optionalFields = ['account_type', 'account_number', 'has_nonstandard_characteristics'];
            foreach ($optionalFields as $field) {
                if (isset($validated[$field])) {
                    $camelCaseField = lcfirst(str_replace('_', '', ucwords($field, '_')));
                    $rateData[$camelCaseField] = $validated[$field];
                }
            }

            $result = $this->uspsPricesService->searchBaseRates($rateData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'status_code' => $result['status_code'] ?? 400
                ], $result['status_code'] ?? 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Base rates retrieved successfully',
                'total_base_price' => $result['total_base_price'],
                'rates' => $result['rates'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Base rates search failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Search for Extra Service Rates
     */
    public function searchExtraServiceRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mail_class' => 'required|string',
            'price_type' => 'required|string',
            'extra_services' => 'required|array',
            'item_value' => 'sometimes|numeric|min:0',
            'weight' => 'sometimes|numeric|min:0.1',
            'origin_zip_code' => 'sometimes|string|size:5',
            'destination_zip_code' => 'sometimes|string|size:5',
            'mailing_date' => 'sometimes|date_format:Y-m-d',
            'account_type' => 'sometimes|string',
            'account_number' => 'sometimes|string'
        ]);

        try {
            $extraServiceData = [
                'mailClass' => $validated['mail_class'],
                'priceType' => $validated['price_type'],
                'extraServices' => $validated['extra_services']
            ];

            // Add optional fields
            $optionalFields = [
                'item_value', 'weight', 'origin_zip_code', 'destination_zip_code', 
                'mailing_date', 'account_type', 'account_number'
            ];
            
            foreach ($optionalFields as $field) {
                if (isset($validated[$field])) {
                    $camelCaseField = lcfirst(str_replace('_', '', ucwords($field, '_')));
                    $extraServiceData[$camelCaseField] = $validated[$field];
                }
            }

            $result = $this->uspsPricesService->searchExtraServiceRates($extraServiceData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'status_code' => $result['status_code'] ?? 400
                ], $result['status_code'] ?? 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Extra service rates retrieved successfully',
                'extra_services' => $result['extra_services'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Extra service rates search failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Search for Total Rates (Base + Extra Services)
     */
    public function searchTotalRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_zip_code' => 'required|string|size:5',
            'destination_zip_code' => 'required|string|size:5',
            'weight' => 'required|numeric|min:0.1',
            'length' => 'required|numeric|min:0.1',
            'width' => 'required|numeric|min:0.1',
            'height' => 'required|numeric|min:0.1',
            'mail_class' => 'required|string',
            'processing_category' => 'required|string',
            'rate_indicator' => 'required|string',
            'destination_entry_facility_type' => 'required|string',
            'price_type' => 'required|string',
            'mailing_date' => 'sometimes|date_format:Y-m-d',
            'extra_services' => 'sometimes|array',
            'item_value' => 'sometimes|numeric|min:0'
        ]);

        try {
            $totalRateData = [
                'originZIPCode' => $validated['origin_zip_code'],
                'destinationZIPCode' => $validated['destination_zip_code'],
                'weight' => $validated['weight'],
                'length' => $validated['length'],
                'width' => $validated['width'],
                'height' => $validated['height'],
                'mailClass' => $validated['mail_class'],
                'processingCategory' => $validated['processing_category'],
                'rateIndicator' => $validated['rate_indicator'],
                'destinationEntryFacilityType' => $validated['destination_entry_facility_type'],
                'priceType' => $validated['price_type'],
                'mailingDate' => $validated['mailing_date'] ?? now()->format('Y-m-d')
            ];

            // Add optional fields
            if (isset($validated['extra_services'])) {
                $totalRateData['extraServices'] = $validated['extra_services'];
            }
            if (isset($validated['item_value'])) {
                $totalRateData['itemValue'] = $validated['item_value'];
            }

            $result = $this->uspsPricesService->searchTotalRates($totalRateData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'status_code' => $result['status_code'] ?? 400
                ], $result['status_code'] ?? 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Total rates retrieved successfully',
                'rates' => $result['rates'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Total rates search failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Search for Base Rates List (All eligible products)
     */
    public function searchBaseRatesList(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'origin_zip_code' => 'required|string|size:5',
            'destination_zip_code' => 'required|string|size:5',
            'weight' => 'required|numeric|min:0.1',
            'length' => 'required|numeric|min:0.1',
            'width' => 'required|numeric|min:0.1',
            'height' => 'required|numeric|min:0.1',
            'mailing_date' => 'sometimes|date_format:Y-m-d',
            'processing_category' => 'sometimes|string',
            'rate_indicator' => 'sometimes|string',
            'destination_entry_facility_type' => 'sometimes|string',
            'price_type' => 'sometimes|string',
            'mail_class' => 'sometimes|string',
            'mail_classes' => 'sometimes|array',
            'account_type' => 'sometimes|string',
            'account_number' => 'sometimes|string',
            'has_nonstandard_characteristics' => 'sometimes|boolean'
        ]);

        try {
            $rateListData = [
                'originZIPCode' => $validated['origin_zip_code'],
                'destinationZIPCode' => $validated['destination_zip_code'],
                'weight' => $validated['weight'],
                'length' => $validated['length'],
                'width' => $validated['width'],
                'height' => $validated['height'],
                'mailingDate' => $validated['mailing_date'] ?? now()->format('Y-m-d')
            ];

            // Add optional fields
            $optionalFields = [
                'processing_category', 'rate_indicator', 'destination_entry_facility_type', 
                'price_type', 'mail_class', 'mail_classes', 'account_type', 
                'account_number', 'has_nonstandard_characteristics'
            ];
            
            foreach ($optionalFields as $field) {
                if (isset($validated[$field])) {
                    $camelCaseField = lcfirst(str_replace('_', '', ucwords($field, '_')));
                    $rateListData[$camelCaseField] = $validated[$field];
                }
            }

            $result = $this->uspsPricesService->searchBaseRatesList($rateListData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'status_code' => $result['status_code'] ?? 400
                ], $result['status_code'] ?? 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Base rates list retrieved successfully',
                'rates' => $result['rates'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Base rates list search failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Search for Letter Rates
     */
    public function searchLetterRates(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'weight' => 'required|numeric|min:0.1',
            'length' => 'required|numeric|min:0.1',
            'height' => 'required|numeric|min:0.1',
            'thickness' => 'required|numeric|min:0.1',
            'processing_category' => 'required|string',
            'mailing_date' => 'sometimes|date_format:Y-m-d',
            'non_machinable_indicators' => 'sometimes|array',
            'extra_services' => 'sometimes|array',
            'item_value' => 'sometimes|numeric|min:0'
        ]);

        try {
            $letterRateData = [
                'weight' => $validated['weight'],
                'length' => $validated['length'],
                'height' => $validated['height'],
                'thickness' => $validated['thickness'],
                'processingCategory' => $validated['processing_category'],
                'mailingDate' => $validated['mailing_date'] ?? now()->format('Y-m-d')
            ];

            // Add optional fields
            $optionalFields = ['non_machinable_indicators', 'extra_services', 'item_value'];
            
            foreach ($optionalFields as $field) {
                if (isset($validated[$field])) {
                    $camelCaseField = lcfirst(str_replace('_', '', ucwords($field, '_')));
                    $letterRateData[$camelCaseField] = $validated[$field];
                }
            }

            $result = $this->uspsPricesService->searchLetterRates($letterRateData);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error'],
                    'status_code' => $result['status_code'] ?? 400
                ], $result['status_code'] ?? 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Letter rates retrieved successfully',
                'total_base_price' => $result['total_base_price'],
                'rates' => $result['rates'],
                'data' => $result['data']
            ]);

        } catch (\Exception $e) {
            Log::error('Letter rates search failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Test Prices API
     */
    public function testPricesApi(): JsonResponse
    {
        try {
            $results = $this->uspsPricesService->testPricesApi();
            
            return response()->json([
                'success' => true,
                'test_results' => $results,
                'environment' => env('APP_ENV'),
                'message' => 'USPS Prices API test completed'
            ]);
        } catch (\Exception $e) {
            Log::error('Prices API test failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Clear Prices API Cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $result = $this->uspsPricesService->clearCache();
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            Log::error('Clear cache failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Clear cache failed: ' . $e->getMessage(),
                'status_code' => 500
            ], 500);
        }
    }

    /**
     * Debug OAuth configuration
     */
    public function debugOAuth(): JsonResponse
    {
        try {
            $result = $this->uspsPricesService->debugOAuth();
            
            return response()->json([
                'success' => true,
                'debug_info' => $result,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth debug failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : 'Trace hidden in production'
            ], 500);
        }
    }

    /**
     * Test connection
     */
    public function testConnection(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Controller is working',
            'environment' => config('services.usps.environment'),
            'client_id_set' => !empty(config('services.usps.client_id')),
            'timestamp' => now()
        ]);
    }

    /**
     * Clear cache via POST
     */
    public function clearCachePost(): JsonResponse
    {
        return $this->clearCache();
    }
}