<?php

namespace App\Http\Controllers;

use App\Services\UspsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class UspsAddressController extends Controller
{
    protected $uspsService;

    public function __construct(UspsService $uspsService)
    {
        $this->uspsService = $uspsService;
    }

    /**
     * Validate customer address for Krayin CRM - CORRECTED
     */
    public function validateCustomerAddress(Request $request): JsonResponse
    {
        $request->validate([
            'address1' => 'required|string|max:255',
            'city' => 'nullable|string|max:100', // City is optional per OpenAPI
            'state' => 'required|string|size:2',
            'postcode' => 'nullable|string|max:10' // ZIPCode is optional per OpenAPI
        ]);

        // Map Krayin fields to USPS API fields
        $addressData = [
            'streetAddress' => $request->address1,
            'state' => $request->state,
        ];

        // Add optional fields only if provided
        if ($request->filled('city')) {
            $addressData['city'] = $request->city;
        }
        if ($request->filled('postcode')) {
            $addressData['ZIPCode'] = $request->postcode;
        }
        if ($request->filled('address2')) {
            $addressData['secondaryAddress'] = $request->address2;
        }

        $result = $this->uspsService->standardizeAddress($addressData);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'],
                'valid' => false,
                'status_code' => $result['status_code'] ?? 400
            ], 400);
        }

        // Check if address has corrections or warnings
        $hasCorrections = !empty($result['corrections']);
        $hasWarnings = !empty($result['warnings']);

        return response()->json([
            'success' => true,
            'valid' => true,
            'has_corrections' => $hasCorrections,
            'has_warnings' => $hasWarnings,
            'message' => $hasCorrections ? 'Address validated with corrections' : 'Address validated successfully',
            'original_address' => [
                'address1' => $request->address1,
                'address2' => $request->address2,
                'city' => $request->city,
                'state' => $request->state,
                'postcode' => $request->postcode
            ],
            'standardized_address' => $result['address'],
            'additional_info' => $result['additionalInfo'],
            'corrections' => $result['corrections'],
            'matches' => $result['matches'],
            'warnings' => $result['warnings']
        ]);
    }

    /**
     * City/State from ZIP Code - CORRECTED
     */
    public function getCityStateFromZip(Request $request): JsonResponse
    {
        $request->validate([
            'zip_code' => 'required|string|regex:/^\d{5}$/'
        ]);

        $result = $this->uspsService->getCityState($request->zip_code);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'city' => $result['city'],
            'state' => $result['state'],
            'zip_code' => $result['ZIPCode']
        ]);
    }

    /**
     * ZIP Code from Address - CORRECTED
     */
    public function getZipFromAddress(Request $request): JsonResponse
    {
        $request->validate([
            'address1' => 'required|string',
            'city' => 'required|string', // Required for this endpoint per OpenAPI
            'state' => 'required|string|size:2'
        ]);

        $result = $this->uspsService->getZipCode([
            'streetAddress' => $request->address1,
            'city' => $request->city,
            'state' => $request->state
        ]);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'firm' => $result['firm'],
            'address' => $result['address']
        ]);
    }

    /**
     * Comprehensive test endpoint
     */
    public function testAllFeatures(): JsonResponse
    {
        $results = $this->uspsService->testAllEndpoints();
        
        return response()->json([
            'success' => true,
            'test_results' => $results,
            'environment' => env('APP_ENV'),
            'message' => 'Comprehensive USPS API test completed'
        ]);
    }

    /**
     * Clear cached token (for testing)
     */
    public function clearCache(): JsonResponse
    {
        Cache::forget('usps_access_token');
        
        return response()->json([
            'success' => true,
            'message' => 'USPS token cache cleared'
        ]);
    }
}