<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UspsPricesService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $tokenUrl;
    protected $environment;
    protected $defaultLetterOrigin;
    protected $defaultLetterDest;

    public function __construct()
    {
        $config = config('services.usps');
        
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->environment = $config['environment'];
        $this->defaultLetterOrigin = $config['default_letter_origin'];
        $this->defaultLetterDest = $config['default_letter_dest'];
        
        // Use consistent URL logic with config
        $this->baseUrl = $this->environment === 'testing' 
            ? $config['prices_test_url'] 
            : $config['prices_base_url'];

        $this->tokenUrl = $config['oauth_token_url'];

        Log::debug('USPS Prices Service Initialized', [
            'environment' => $this->environment,
            'base_url' => $this->baseUrl,
            'token_url' => $this->tokenUrl
        ]);
    }

    /**
     * Get OAuth Access Token
     */
    public function getAccessToken(): ?string
    {
        $cacheKey = "usps_prices_access_token_{$this->environment}";
        
        if (Cache::has($cacheKey)) {
            $token = Cache::get($cacheKey);
            Log::debug('Using cached USPS Prices token');
            return $token;
        }

        try {
            Log::info('Requesting new USPS Prices OAuth token', [
                'token_url' => $this->tokenUrl,
                'environment' => $this->environment
            ]);

            $response = Http::asForm()
                ->timeout(30)
                ->retry(2, 100)
                ->post($this->tokenUrl, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (!isset($data['access_token'])) {
                    Log::error('USPS OAuth response missing access_token');
                    return null;
                }
                
                $token = $data['access_token'];
                $expiresIn = $data['expires_in'] ?? 3600;
                
                // Cache for 85% of token lifetime
                $cacheTime = floor($expiresIn * 0.85);
                Cache::put($cacheKey, $token, $cacheTime);
                
                Log::info('USPS Prices OAuth token obtained successfully');
                return $token;
            }

            Log::error('USPS Prices OAuth Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            
            return null;

        } catch (\Exception $e) {
            Log::error('USPS Prices OAuth Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Make authenticated API request to USPS Prices API
     */
    private function makeApiRequest(string $endpoint, array $data, string $method = 'post'): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Authentication failed - Unable to obtain access token',
                'status_code' => 401
            ];
        }

        $url = "{$this->baseUrl}{$endpoint}";
        
        try {
            Log::info('Making USPS Prices API Request', [
                'endpoint' => $endpoint,
                'method' => $method,
                'data_keys' => array_keys($data)
            ]);

            $http = Http::timeout(30)
                ->retry(2, 100)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]);

            $response = $method === 'get' 
                ? $http->get($url, $data)
                : $http->post($url, $data);

            return $this->handleApiResponse($response, $endpoint);

        } catch (\Exception $e) {
            Log::error('USPS Prices API Request Exception: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage(),
                'status_code' => 503
            ];
        }
    }

    /**
     * Handle API response consistently
     */
    private function handleApiResponse($response, string $endpoint): array
    {
        $statusCode = $response->status();
        
        Log::debug("USPS Prices API Response for {$endpoint}", [
            'status' => $statusCode,
            'successful' => $response->successful()
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            return [
                'success' => true,
                'data' => $data,
                'status_code' => $statusCode,
                'total_base_price' => $data['totalBasePrice'] ?? null,
                'rates' => $data['rates'] ?? $data['rateOptions'] ?? [],
                'extra_services' => $data['extraServices'] ?? []
            ];
        }

        return $this->handleErrorResponse($response, $endpoint);
    }

    /**
     * Handle error responses
     */
    private function handleErrorResponse($response, string $endpoint): array
    {
        $statusCode = $response->status();
        $errorResponse = $response->json();
        
        $errorMessages = [
            400 => 'Invalid request parameters',
            401 => 'Authentication failed',
            403 => 'Access denied',
            404 => 'Endpoint not found',
            422 => 'Validation failed',
            429 => 'Rate limit exceeded',
            500 => 'USPS server error',
            503 => 'USPS service unavailable'
        ];

        $errorMessage = $errorMessages[$statusCode] ?? "USPS Prices API error: {$statusCode}";

        // Enhanced error diagnostics
        $apiError = $errorResponse['error'] ?? $errorResponse['message'] ?? null;
        if ($apiError) {
            if (is_array($apiError)) {
                $errorMessage .= ': ' . ($apiError['message'] ?? json_encode($apiError));
            } else {
                $errorMessage .= ': ' . $apiError;
            }
        }

        Log::error("USPS Prices API Error for {$endpoint}", [
            'status_code' => $statusCode,
            'error_message' => $errorMessage,
            'api_error' => $errorResponse
        ]);

        return [
            'success' => false,
            'error' => $errorMessage,
            'status_code' => $statusCode,
            'api_error' => $errorResponse
        ];
    }

    /**
     * CORRECTED: Search for Base Rates - Uses /base-rates/search
     */
    public function searchBaseRates(array $rateData): array
    {
        // Validate required fields for base rates
        $requiredFields = [
            'originZIPCode', 'destinationZIPCode', 'weight', 'length', 
            'width', 'height', 'mailClass', 'processingCategory', 
            'rateIndicator', 'destinationEntryFacilityType', 'priceType'
        ];

        foreach ($requiredFields as $field) {
            if (empty($rateData[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}",
                    'status_code' => 400
                ];
            }
        }

        return $this->makeApiRequest('/base-rates/search', $rateData);
    }

    /**
     * CORRECTED: Search for Extra Service Rates - Uses /extra-service-rates/search
     */
    public function searchExtraServiceRates(array $extraServiceData): array
    {
        // Validate required fields for extra services
        if (empty($extraServiceData['mailClass']) || empty($extraServiceData['priceType'])) {
            return [
                'success' => false,
                'error' => 'Missing required fields: mailClass and priceType are required',
                'status_code' => 400
            ];
        }

        if (empty($extraServiceData['extraServices'])) {
            return [
                'success' => false,
                'error' => 'Missing required field: extraServices array is required',
                'status_code' => 400
            ];
        }

        // Normalize extraServices to array
        if (!is_array($extraServiceData['extraServices'])) {
            $extraServiceData['extraServices'] = [$extraServiceData['extraServices']];
        }

        // Set default itemValue if not provided
        if (!isset($extraServiceData['itemValue'])) {
            $extraServiceData['itemValue'] = 0;
        }

        return $this->makeApiRequest('/extra-service-rates/search', $extraServiceData);
    }

    /**
     * NEW: Search for Total Rates - Uses /total-rates/search endpoint
     */
    public function searchTotalRates(array $totalRateData): array
    {
        // Validate required fields for total rates
        $requiredFields = [
            'originZIPCode', 'destinationZIPCode', 'weight', 'length', 
            'width', 'height', 'mailClass', 'processingCategory', 
            'rateIndicator', 'destinationEntryFacilityType', 'priceType'
        ];

        foreach ($requiredFields as $field) {
            if (empty($totalRateData[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}",
                    'status_code' => 400
                ];
            }
        }

        // Ensure extraServices is an array if provided
        if (isset($totalRateData['extraServices']) && !is_array($totalRateData['extraServices'])) {
            $totalRateData['extraServices'] = [$totalRateData['extraServices']];
        }

        // Set default itemValue if not provided
        if (!isset($totalRateData['itemValue'])) {
            $totalRateData['itemValue'] = 0;
        }

        return $this->makeApiRequest('/total-rates/search', $totalRateData);
    }

    /**
     * NEW: Search for Base Rates List - Uses /base-rates-list/search endpoint
     */
    public function searchBaseRatesList(array $rateListData): array
    {
        // Validate required fields for rates list
        $requiredFields = [
            'originZIPCode', 'destinationZIPCode', 'weight', 'length', 'width', 'height'
        ];

        foreach ($requiredFields as $field) {
            if (empty($rateListData[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field: {$field}",
                    'status_code' => 400
                ];
            }
        }

        // Add default values for optional fields
        $defaultFields = [
            'processingCategory' => 'MACHINABLE',
            'rateIndicator' => 'SP',
            'destinationEntryFacilityType' => 'NONE',
            'priceType' => 'COMMERCIAL',
            'mailingDate' => now()->format('Y-m-d')
        ];

        foreach ($defaultFields as $field => $default) {
            if (!isset($rateListData[$field])) {
                $rateListData[$field] = $default;
            }
        }

        return $this->makeApiRequest('/base-rates-list/search', $rateListData);
    }

    /**
     * NEW: Search for Letter Rates - Uses /letter-rates/search endpoint
     */
    public function searchLetterRates(array $letterRateData): array
    {
        // Validate required fields for letter rates
        $requiredFields = ['weight', 'length', 'height', 'thickness', 'processingCategory'];
        
        foreach ($requiredFields as $field) {
            if (empty($letterRateData[$field])) {
                return [
                    'success' => false,
                    'error' => "Missing required field for letter rates: {$field}",
                    'status_code' => 400
                ];
            }
        }

        // Add default values for letter rates
        $defaultFields = [
            'originZIPCode' => $this->defaultLetterOrigin,
            'destinationZIPCode' => $this->defaultLetterDest,
            'mailingDate' => now()->format('Y-m-d'),
            'nonMachinableIndicators' => false
        ];

        foreach ($defaultFields as $field => $default) {
            if (!isset($letterRateData[$field])) {
                $letterRateData[$field] = $default;
            }
        }

        return $this->makeApiRequest('/letter-rates/search', $letterRateData);
    }

    /**
     * CORRECTED: Test Prices API with all endpoints
     */
    public function testPricesApi(): array
    {
        $results = [
            'environment' => $this->environment,
            'base_url' => $this->baseUrl,
            'timestamp' => now()->toISOString()
        ];

        // Test 1: OAuth Token
        $token = $this->getAccessToken();
        $results['oauth'] = [
            'success' => !empty($token),
            'token_obtained' => !empty($token),
            'message' => $token ? 'OAuth token obtained successfully' : 'Failed to get OAuth token'
        ];

        if (!$token) {
            $results['overall_success'] = false;
            return $results;
        }

        // Test 2: Base Rates
        $baseRateData = [
            'originZIPCode' => '22407',
            'destinationZIPCode' => '63118',
            'weight' => 1.5,
            'length' => 10.0,
            'width' => 8.0,
            'height' => 4.0,
            'mailClass' => 'USPS_GROUND_ADVANTAGE',
            'processingCategory' => 'MACHINABLE',
            'rateIndicator' => 'SP',
            'destinationEntryFacilityType' => 'NONE',
            'priceType' => 'COMMERCIAL',
            'mailingDate' => now()->format('Y-m-d')
        ];

        $results['base_rates'] = $this->searchBaseRates($baseRateData);

        // Test 3: Extra Services
        if ($results['base_rates']['success']) {
            $extraServiceData = [
                'mailClass' => 'USPS_GROUND_ADVANTAGE',
                'priceType' => 'COMMERCIAL',
                'extraServices' => [920], // USPS Tracking
                'itemValue' => 50.00,
                'weight' => 1.5,
                'originZIPCode' => '22407',
                'destinationZIPCode' => '63118',
                'mailingDate' => now()->format('Y-m-d'),
                'processingCategory' => 'MACHINABLE',
                'rateIndicator' => 'SP',
                'destinationEntryFacilityType' => 'NONE'
            ];

            $results['extra_services'] = $this->searchExtraServiceRates($extraServiceData);
        }

        // Test 4: Base Rates List
        $rateListData = [
            'originZIPCode' => '22407',
            'destinationZIPCode' => '63118',
            'weight' => 1.5,
            'length' => 10.0,
            'width' => 8.0,
            'height' => 4.0
        ];

        $results['base_rates_list'] = $this->searchBaseRatesList($rateListData);

        // Test 5: Total Rates
        $totalRateData = [
            'originZIPCode' => '22407',
            'destinationZIPCode' => '63118',
            'weight' => 1.5,
            'length' => 10.0,
            'width' => 8.0,
            'height' => 4.0,
            'mailClass' => 'USPS_GROUND_ADVANTAGE',
            'processingCategory' => 'MACHINABLE',
            'rateIndicator' => 'SP',
            'destinationEntryFacilityType' => 'NONE',
            'priceType' => 'COMMERCIAL',
            'mailingDate' => now()->format('Y-m-d'),
            'extraServices' => [920],
            'itemValue' => 50.00
        ];

        $results['total_rates'] = $this->searchTotalRates($totalRateData);

        // Test 6: Letter Rates - FIXED parameters
        $letterRateData = [
            'weight' => 1.0, // Increased weight for better compatibility
            'length' => 11.0, // Standard letter dimensions
            'height' => 5.0,
            'thickness' => 0.25,
            'processingCategory' => 'MACHINABLE',
            'originZIPCode' => '22407', // Use same ZIPs as other tests
            'destinationZIPCode' => '63118',
            'mailingDate' => now()->format('Y-m-d'),
            'priceType' => 'COMMERCIAL', // Changed from COMMERCIAL_BASE
            'mailClass' => 'USPS_GROUND_ADVANTAGE' // Added mailClass which is likely required
        ];

        $results['letter_rates'] = $this->searchLetterRates($letterRateData);

        $results['overall_success'] = $results['base_rates']['success'] ?? false;

        return $results;
    }

    /**
     * Debug OAuth configuration
     */
    public function debugOAuth(): array
    {
        $cacheKey = "usps_prices_access_token_{$this->environment}";
        
        return [
            'client_id_set' => !empty($this->clientId),
            'client_secret_set' => !empty($this->clientSecret),
            'base_url' => $this->baseUrl,
            'token_url' => $this->tokenUrl,
            'environment' => $this->environment,
            'has_cached_token' => Cache::has($cacheKey)
        ];
    }

    /**
     * Clear cached token
     */
    public function clearCache(): array
    {
        $cacheKey = "usps_prices_access_token_{$this->environment}";
        Cache::forget($cacheKey);
        
        Log::info('USPS Prices token cache cleared');
        
        return [
            'success' => true, 
            'message' => 'USPS Prices token cache cleared successfully'
        ];
    }
}