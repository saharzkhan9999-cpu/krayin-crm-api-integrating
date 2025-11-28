<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UspsService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;

    public function __construct()
    {
        $this->clientId = env('USPS_CLIENT_ID');
        $this->clientSecret = env('USPS_CLIENT_SECRET');
        
        // Based on OpenAPI spec servers
        $this->baseUrl = env('APP_ENV') === 'local' 
            ? 'https://apis-tem.usps.com/addresses/v3' 
            : 'https://apis.usps.com/addresses/v3';
    }

    /**
     * Get OAuth Access Token - CORRECTED based on OpenAPI spec
     */
    public function getAccessToken()
    {
        if (Cache::has('usps_access_token')) {
            return Cache::get('usps_access_token');
        }

        try {
            // Token URL from OpenAPI securitySchemes
            $tokenUrl = str_replace('/addresses/v3', '', $this->baseUrl) . '/oauth2/v3/token';
            
            $response = Http::asForm()->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['access_token'];
                
                Cache::put('usps_access_token', $token, now()->addMinutes(50));
                return $token;
            }

            Log::error('USPS OAuth Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('USPS OAuth Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Address Standardization - CORRECTED based on OpenAPI spec
     * GET /address
     */
    public function standardizeAddress($addressData)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Failed to authenticate with USPS API'
            ];
        }

        try {
            // Build query parameters EXACTLY as specified in OpenAPI
            $queryParams = [
                'streetAddress' => $addressData['streetAddress'], // required
                'state' => $addressData['state'], // required
            ];

            // Add optional parameters exactly as per OpenAPI spec
            $optionalParams = [
                'firm' => ['maxLength' => 50],
                'secondaryAddress' => [],
                'city' => [],
                'urbanization' => [],
                'ZIPCode' => ['pattern' => '^\d{5}$'],
                'ZIPPlus4' => ['pattern' => '^\d{4}$']
            ];

            foreach ($optionalParams as $param => $constraints) {
                if (!empty($addressData[$param])) {
                    $queryParams[$param] = $addressData[$param];
                }
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/address", $queryParams);

            // Handle response based on OpenAPI spec
            if ($response->successful()) {
                $result = $response->json();
                
                return [
                    'success' => true,
                    'valid' => true,
                    'firm' => $result['firm'] ?? null,
                    'address' => $result['address'] ?? null,
                    'additionalInfo' => $result['additionalInfo'] ?? null,
                    'corrections' => $result['corrections'] ?? [],
                    'matches' => $result['matches'] ?? [],
                    'warnings' => $result['warnings'] ?? [],
                    'raw_response' => $result
                ];
            }

            // Handle specific error responses from OpenAPI spec
            $statusCode = $response->status();
            $errorResponse = $response->json();
            
            $errorMessages = [
                400 => 'Bad Request - Invalid address parameters',
                401 => 'Unauthorized - Check USPS credentials',
                403 => 'Access Denied - Check API permissions',
                404 => 'Address Not Found',
                429 => 'Too Many Requests - Rate limit exceeded',
                503 => 'Service Unavailable - USPS service down'
            ];

            return [
                'success' => false,
                'error' => $errorMessages[$statusCode] ?? 'USPS API error: ' . $statusCode,
                'status_code' => $statusCode,
                'api_error' => $errorResponse['error'] ?? null,
                'details' => $errorResponse
            ];

        } catch (\Exception $e) {
            Log::error('USPS API Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * City/State Lookup - CORRECTED based on OpenAPI spec
     * GET /city-state
     */
    public function getCityState($zipCode)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        try {
            // Validate ZIP code pattern from OpenAPI spec
            if (!preg_match('/^\d{5}$/', $zipCode)) {
                return [
                    'success' => false,
                    'error' => 'Invalid ZIP Code format. Must be 5 digits.'
                ];
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/city-state", [
                    'ZIPCode' => $zipCode
                ]);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'city' => $result['city'] ?? null,
                    'state' => $result['state'] ?? null,
                    'ZIPCode' => $result['ZIPCode'] ?? $zipCode
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get city/state',
                'status_code' => $response->status(),
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('USPS City-State Lookup Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ZIP Code Lookup - CORRECTED based on OpenAPI spec
     * GET /zipcode
     */
    public function getZipCode($addressData)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        try {
            // Build required parameters from OpenAPI spec
            $queryParams = [
                'streetAddress' => $addressData['streetAddress'], // required
                'city' => $addressData['city'], // required
                'state' => $addressData['state'], // required
            ];

            // Add optional parameters
            $optionalParams = ['firm', 'secondaryAddress', 'ZIPCode', 'ZIPPlus4'];
            foreach ($optionalParams as $param) {
                if (!empty($addressData[$param])) {
                    $queryParams[$param] = $addressData[$param];
                }
            }

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/zipcode", $queryParams);

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'firm' => $result['firm'] ?? null,
                    'address' => $result['address'] ?? null,
                    'raw_response' => $result
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to get ZIP code',
                'status_code' => $response->status(),
                'details' => $response->json()
            ];

        } catch (\Exception $e) {
            Log::error('USPS ZIP Code Lookup Error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Test all endpoints with sample data
     */
 /**
 * Test all endpoints with real USPS-valid addresses
 */
public function testAllEndpoints()
{
    $results = [];

    // ✅ Test 1: Address Standardization (valid USPS address)
    $testAddress = [
        'streetAddress' => '1600 Pennsylvania Avenue NW', // White House address
        'city' => 'Washington',
        'state' => 'DC',
        'ZIPCode' => '20500'
    ];
    $results['address_standardization'] = $this->standardizeAddress($testAddress);

    // ✅ Test 2: City/State Lookup (ZIPCode 20500 = Washington, DC)
    $results['city_state_lookup'] = $this->getCityState('20500');

    // ✅ Test 3: ZIP Code Lookup (valid USPS address)
    $zipTestData = [
        'streetAddress' => '1600 Pennsylvania Avenue NW',
        'city' => 'Washington',
        'state' => 'DC'
    ];
    $results['zipcode_lookup'] = $this->getZipCode($zipTestData);

    return $results;
}

}