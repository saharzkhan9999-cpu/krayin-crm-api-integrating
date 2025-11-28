<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UspsLabelsService
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    protected $paymentAuthToken;

    public function __construct()
    {
        $this->clientId = env('USPS_CLIENT_ID');
        $this->clientSecret = env('USPS_CLIENT_SECRET');
        
        // Labels API base URL
        $this->baseUrl = env('APP_ENV') === 'local' 
            ? env('USPS_LABELS_TEST_URL', 'https://apis-tem.usps.com/labels/v3')
            : env('USPS_LABELS_BASE_URL', 'https://apis.usps.com/labels/v3');

        // Payment authorization token (you'll need to get this from USPS Payments API)
        $this->paymentAuthToken = env('USPS_PAYMENT_AUTH_TOKEN');
    }

    /**
     * Get OAuth Access Token for Labels API
     */
    public function getAccessToken()
    {
        $cacheKey = 'usps_labels_access_token';
        
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $tokenUrl = str_replace('/labels/v3', '', $this->baseUrl) . '/oauth2/v3/token';
            
            $response = Http::asForm()->post($tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'labels' // Specific scope for Labels API
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = $data['access_token'];
                
                // Cache for 50 minutes (tokens typically expire in 1 hour)
                Cache::put($cacheKey, $token, now()->addMinutes(50));
                return $token;
            }

            Log::error('USPS Labels OAuth Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            return null;

        } catch (\Exception $e) {
            Log::error('USPS Labels OAuth Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create Domestic Shipping Label
     */
    public function createDomesticLabel($labelData)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Failed to authenticate with USPS Labels API'
            ];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'X-Payment-Authorization-Token' => $this->paymentAuthToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/label", $labelData);

            return $this->handleLabelResponse($response);

        } catch (\Exception $e) {
            Log::error('USPS Labels API Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create Return Label
     */
    public function createReturnLabel($returnLabelData)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Failed to authenticate with USPS Labels API'
            ];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'X-Payment-Authorization-Token' => $this->paymentAuthToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/return-label", $returnLabelData);

            return $this->handleLabelResponse($response);

        } catch (\Exception $e) {
            Log::error('USPS Return Labels API Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel a Label or Request Refund
     */
    public function cancelLabel($trackingNumber)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'error' => 'Failed to authenticate with USPS Labels API'
            ];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'X-Payment-Authorization-Token' => $this->paymentAuthToken,
                    'Accept' => 'application/json',
                ])
                ->delete("{$this->baseUrl}/label/{$trackingNumber}");

            if ($response->successful()) {
                $result = $response->json();
                return [
                    'success' => true,
                    'tracking_number' => $result['trackingNumber'],
                    'status' => $result['status'],
                    'dispute_id' => $result['disputeId'] ?? null,
                    'message' => $result['status'] === 'CANCELED' 
                        ? 'Label successfully canceled' 
                        : 'Refund request submitted'
                ];
            }

            return $this->handleErrorResponse($response);

        } catch (\Exception $e) {
            Log::error('USPS Cancel Label API Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Edit Label Attributes
     */
    public function editLabel($trackingNumber, $patchData)
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'X-Payment-Authorization-Token' => $this->paymentAuthToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->patch("{$this->baseUrl}/label/{$trackingNumber}", $patchData);

            return $this->handleLabelResponse($response);

        } catch (\Exception $e) {
            Log::error('USPS Edit Label API Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reprint a Label
     */
    public function reprintLabel($trackingNumber, $imageInfo = [])
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['success' => false, 'error' => 'Authentication failed'];
        }

        try {
            $requestData = ['imageInfo' => $imageInfo];

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'X-Payment-Authorization-Token' => $this->paymentAuthToken,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/label-reprint/{$trackingNumber}", $requestData);

            return $this->handleLabelResponse($response);

        } catch (\Exception $e) {
            Log::error('USPS Reprint Label API Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Service unavailable: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle Label Creation Responses
     */
    private function handleLabelResponse($response)
    {
        if ($response->successful()) {
            $result = $response->json();
            
            return [
                'success' => true,
                'label_metadata' => $result['labelMetadata'] ?? null,
                'label_image' => $result['labelImage'] ?? null,
                'receipt_image' => $result['receiptImage'] ?? null,
                'return_label_metadata' => $result['returnLabelMetadata'] ?? null,
                'return_label_image' => $result['returnLabelImage'] ?? null,
                'tracking_number' => $result['labelMetadata']['trackingNumber'] ?? null,
                'postage' => $result['labelMetadata']['postage'] ?? null,
                'raw_response' => $result
            ];
        }

        return $this->handleErrorResponse($response);
    }

    /**
     * Handle Error Responses
     */
    private function handleErrorResponse($response)
    {
        $statusCode = $response->status();
        $errorResponse = $response->json();
        
        $errorMessages = [
            400 => 'Bad Request - Invalid parameters',
            401 => 'Unauthorized - Check USPS credentials',
            403 => 'Access Denied - Check API permissions or payment authorization',
            404 => 'Label Not Found',
            429 => 'Too Many Requests - Rate limit exceeded',
            503 => 'Service Unavailable - USPS service down'
        ];

        return [
            'success' => false,
            'error' => $errorMessages[$statusCode] ?? 'USPS Labels API error: ' . $statusCode,
            'status_code' => $statusCode,
            'api_error' => $errorResponse['error'] ?? null,
            'details' => $errorResponse
        ];
    }

    /**
     * Test Labels API with sample data
     */
    public function testLabelsApi()
    {
        $results = [];

        // Test 1: Create a simple domestic label
        $testLabelData = [
            'toAddress' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'streetAddress' => '123 Main St',
                'city' => 'Anytown',
                'state' => 'CA',
                'ZIPCode' => '12345'
            ],
            'fromAddress' => [
                'firstName' => 'Jane',
                'lastName' => 'Smith',
                'streetAddress' => '456 Oak Ave',
                'city' => 'Somewhere',
                'state' => 'NY',
                'ZIPCode' => '67890'
            ],
            'packageDescription' => [
                'mailClass' => 'USPS_GROUND_ADVANTAGE',
                'rateIndicator' => 'SP',
                'weight' => 1.5,
                'weightUOM' => 'lb',
                'length' => 10,
                'height' => 8,
                'width' => 5,
                'dimensionsUOM' => 'in',
                'processingCategory' => 'MACHINABLE',
                'mailingDate' => now()->addDay()->format('Y-m-d'),
                'destinationEntryFacilityType' => 'NONE'
            ],
            'imageInfo' => [
                'imageType' => 'PDF',
                'labelType' => '4X6LABEL',
                'receiptOption' => 'SAME_PAGE',
                'suppressPostage' => false,
                'suppressMailDate' => false
            ]
        ];

        $results['create_domestic_label'] = $this->createDomesticLabel($testLabelData);

        return $results;
    }

    /**
     * Clear cached token (for testing)
     */
    public function clearCache()
    {
        Cache::forget('usps_labels_access_token');
        return ['success' => true, 'message' => 'USPS Labels token cache cleared'];
    }
}