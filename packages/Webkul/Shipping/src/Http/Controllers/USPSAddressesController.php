<?php

namespace Webkul\Shipping\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Webkul\Shipping\Services\USPSAddressService;
use Webkul\Shipping\Exceptions\USPSApiException;

class USPSAddressesController extends Controller
{
    protected $addressService;

    public function __construct(USPSAddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    /**
     * Validate and standardize single address
     */
    public function validateAddress(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'street_address' => 'required|string|max:100',
                'city' => 'nullable|string|max:50',
                'state' => 'required|string|size:2',
                'zip_code' => 'nullable|string|size:5',
                'firm' => 'nullable|string|max:50',
                'secondary_address' => 'nullable|string|max:50',
                'urbanization' => 'nullable|string|max:50',
            ]);

            $result = $this->addressService->validateAddress(
                streetAddress: $validated['street_address'],
                state: $validated['state'],
                city: $validated['city'] ?? null,
                zipCode: $validated['zip_code'] ?? null,
                firm: $validated['firm'] ?? null,
                secondaryAddress: $validated['secondary_address'] ?? null,
                urbanization: $validated['urbanization'] ?? null
            );

            $standardized = $this->addressService->extractStandardizedAddress($result);

            return response()->json([
                'success' => true,
                'data' => [
                    'original' => $validated,
                    'standardized' => $standardized,
                    'raw_response' => $result,
                ],
                'is_valid' => $standardized['is_valid'],
                'message' => $standardized['is_valid'] ? 'Address validated successfully' : 'Address needs correction',
            ]);

        } catch (USPSApiException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 400);

        } catch (\Exception $e) {
            \Log::error('Address validation error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Validate multiple addresses in batch
     */
    public function validateBatchAddresses(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'addresses' => 'required|array|max:100', // Limit batch size
                'addresses.*.street_address' => 'required|string|max:100',
                'addresses.*.city' => 'nullable|string|max:50',
                'addresses.*.state' => 'required|string|size:2',
                'addresses.*.zip_code' => 'nullable|string|size:5',
                'addresses.*.firm' => 'nullable|string|max:50',
                'addresses.*.secondary_address' => 'nullable|string|max:50',
            ]);

            $results = $this->addressService->validateMultipleAddresses($validated['addresses']);

            return response()->json([
                'success' => true,
                'data' => $results,
                'processed' => count($results),
                'successful' => count(array_filter($results, fn($r) => $r['success'])),
            ]);

        } catch (\Exception $e) {
            \Log::error('Batch address validation error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Batch validation failed: ' . $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Get city/state by ZIP code
     */
    public function getCityStateByZip(Request $request, string $zipCode): JsonResponse
    {
        try {
            $result = $this->addressService->getCityStateByZip($zipCode);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (USPSApiException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 400);

        } catch (\Exception $e) {
            \Log::error('ZIP code lookup error', ['zip' => $zipCode, 'error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Get ZIP code by address
     */
    public function getZipCodeByAddress(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'street_address' => 'required|string|max:100',
                'city' => 'required|string|max:50',
                'state' => 'required|string|size:2',
                'firm' => 'nullable|string|max:50',
                'secondary_address' => 'nullable|string|max:50',
            ]);

            $result = $this->addressService->getZipCodeByAddress([
                'streetAddress' => $validated['street_address'],
                'city' => $validated['city'],
                'state' => $validated['state'],
                'firm' => $validated['firm'] ?? null,
                'secondaryAddress' => $validated['secondary_address'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (USPSApiException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ], $e->getCode() >= 400 && $e->getCode() < 500 ? $e->getCode() : 400);

        } catch (\Exception $e) {
            \Log::error('ZIP code by address error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): JsonResponse
    {
        try {
            $result = $this->addressService->testConnection();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }

    /**
     * Clear cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $this->addressService->clearCache();

            return response()->json([
                'success' => true,
                'message' => 'USPS cache cleared successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}