<?php

namespace Webkul\Shipping\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Webkul\Shipping\Exceptions\USPSApiException;
use Webkul\Shipping\Contracts\USPSLabelServiceInterface;

class USPSLabelService implements USPSLabelServiceInterface
{
    protected $baseUrl;
    protected $oauthUrl;
    protected $clientId;
    protected $clientSecret;
    protected $timeout;
    protected $maxRetries;
    protected $paymentService;
    protected $environment;

    public function __construct(USPSPaymentService $paymentService)
    {
        $this->environment = config('usps.environment', 'testing');
        $this->validateConfiguration();
        
        $this->baseUrl = config("usps.services.labels.base_url.{$this->environment}");
        $this->oauthUrl = config("usps.oauth.{$this->environment}");
        $this->clientId = config('usps.credentials.client_id');
        $this->clientSecret = config('usps.credentials.client_secret');
        $this->timeout = config('usps.api.timeout', 30);
        $this->maxRetries = config('usps.api.retry_attempts', 3);
        $this->paymentService = $paymentService;
    }

    /**
     * Validate critical configuration
     */
    protected function validateConfiguration(): void
    {
        $requiredConfig = [
            "usps.services.labels.base_url.{$this->environment}",
            "usps.oauth.{$this->environment}",
            'usps.credentials.client_id',
            'usps.credentials.client_secret',
        ];

        $missing = [];
        foreach ($requiredConfig as $configKey) {
            if (empty(config($configKey))) {
                $missing[] = $configKey;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                "Missing required USPS Labels configuration: " . implode(', ', $missing)
            );
        }

        if (!in_array($this->environment, ['testing', 'production'])) {
            throw new \RuntimeException("Invalid USPS environment: {$this->environment}");
        }
    }

    /**
     * Get OAuth Token with caching
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'usps_labels_oauth_token_' . md5($this->clientId);
        
        return Cache::remember($cacheKey, config('usps.cache.token_ttl', 3500), function () {
            return $this->retry(function () {
                $response = Http::timeout($this->timeout)
                    ->asForm()
                    ->post($this->oauthUrl, [
                        'grant_type' => 'client_credentials',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'scope' => 'labels',
                    ]);

                if (!$response->successful()) {
                    Log::error('USPS Labels OAuth Token Failed', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ]);
                    throw USPSApiException::fromResponse($response, 'OAuth token');
                }

                $data = $response->json();
                return $data['access_token'];
            }, 'OAuth token');
        });
    }

    /**
     * Create Domestic Shipping Label
     */
    public function createLabel(array $labelData): array
    {
        $this->validateLabelData($labelData);
        $payload = $this->buildLabelPayload($labelData);

        Log::debug('USPS Label Request Payload', [
            'payload' => $payload,
            'json_payload' => json_encode($payload, JSON_PRETTY_PRINT)
        ]);

        return $this->retry(function () use ($payload) {
            $token = $this->getAccessToken();
            $paymentToken = $this->paymentService->createPaymentAuthorization();

            Log::info('USPS Label Creation Request', [
                'mail_class' => $payload['packageDescription']['mailClass'] ?? 'unknown',
                'from_zip' => $payload['fromAddress']['ZIPCode'] ?? 'unknown',
                'to_zip' => $payload['toAddress']['ZIPCode'] ?? 'unknown',
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'X-Payment-Authorization-Token' => $paymentToken['paymentAuthorizationToken'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/label", $payload);

            Log::debug('USPS Label Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body_preview' => substr($response->body(), 0, 500)
            ]);

            if ($response->successful()) {
                $result = $this->handleResponse($response);
                
                // Log successful label creation
                $metadata = json_decode($result['labelMetadata'] ?? '{}', true);
                Log::info('USPS Label Created Successfully', [
                    'tracking_number' => $metadata['trackingNumber'] ?? 'unknown',
                    'total_cost' => $metadata['postage'] ?? 0,
                    'service' => $metadata['commitment']['name'] ?? 'unknown',
                ]);
                
                return $result;
            }

            throw USPSApiException::fromResponse($response, 'Label creation');
            
        }, 'Creating shipping label');
    }

    /**
     * Build complete label payload according to USPS API spec
     */
    protected function buildLabelPayload(array $labelData): array
    {
        // Build imageInfo using config defaults
        $imageInfo = [
            'imageType' => $labelData['imageInfo']['imageType'] ?? config('usps.defaults.image_type', 'PDF'),
            'labelType' => $labelData['imageInfo']['labelType'] ?? config('usps.defaults.label_type', '4X6LABEL'),
            'receiptOption' => $labelData['imageInfo']['receiptOption'] ?? config('usps.defaults.receipt_option', 'NONE'),
            'suppressPostage' => $labelData['imageInfo']['suppressPostage'] ?? config('usps.defaults.suppress_postage', false),
            'suppressMailDate' => $labelData['imageInfo']['suppressMailDate'] ?? config('usps.defaults.suppress_mail_date', true),
            'returnLabel' => $labelData['imageInfo']['returnLabel'] ?? config('usps.defaults.return_label', false),
        ];

        // Add optional imageInfo fields
        $optionalImageFields = [
            'brandingImageFormat', 'brandingImageUUIDs', 'includeLabelBrokerPDF', 
            'addZPLComments', 'packageNumber', 'totalPackages'
        ];
        
        foreach ($optionalImageFields as $field) {
            if (isset($labelData['imageInfo'][$field])) {
                $imageInfo[$field] = $labelData['imageInfo'][$field];
            }
        }

        // Build packageDescription with all required fields using config defaults
        $packageDescription = [
            'mailClass' => $labelData['packageDescription']['mailClass'],
            'rateIndicator' => $labelData['packageDescription']['rateIndicator'] ?? $this->getRateIndicator($labelData['packageDescription']['mailClass']),
            'weightUOM' => $labelData['packageDescription']['weightUOM'] ?? config('usps.defaults.weight_uom', 'lb'),
            'weight' => (float) $labelData['packageDescription']['weight'],
            'dimensionsUOM' => $labelData['packageDescription']['dimensionsUOM'] ?? config('usps.defaults.dimensions_uom', 'in'),
            'length' => (float) $labelData['packageDescription']['length'],
            'width' => (float) $labelData['packageDescription']['width'],
            'height' => (float) $labelData['packageDescription']['height'],
            'processingCategory' => $labelData['packageDescription']['processingCategory'],
            'mailingDate' => $labelData['packageDescription']['mailingDate'],
            'destinationEntryFacilityType' => $labelData['packageDescription']['destinationEntryFacilityType'] ?? config('usps.defaults.destination_entry_facility_type', 'NONE'),
        ];

        // Add conditional fields
        if (isset($labelData['packageDescription']['girth'])) {
            $packageDescription['girth'] = (float) $labelData['packageDescription']['girth'];
        }
        
        if (isset($labelData['packageDescription']['hasNonstandardCharacteristics'])) {
            $packageDescription['hasNonstandardCharacteristics'] = $labelData['packageDescription']['hasNonstandardCharacteristics'];
        }

        // Add optional arrays
        $packageDescription['extraServices'] = $labelData['packageDescription']['extraServices'] ?? [];
        $packageDescription['customerReference'] = $labelData['packageDescription']['customerReference'] ?? [];

        // Add packageOptions if provided
        if (isset($labelData['packageDescription']['packageOptions'])) {
            $packageDescription['packageOptions'] = $labelData['packageDescription']['packageOptions'];
        }

        // Add container if provided
        if (isset($labelData['packageDescription']['container'])) {
            $packageDescription['container'] = $labelData['packageDescription']['container'];
        }

        // Add optional package fields
        $optionalPackageFields = [
            'carrierRelease', 'physicalSignatureRequired', 'inductionZIPCode',
            'shipperVisibilityMethod', 'mailOwnerMID', 'logisticsManagerMID'
        ];
        
        foreach ($optionalPackageFields as $field) {
            if (isset($labelData['packageDescription'][$field])) {
                $packageDescription[$field] = $labelData['packageDescription'][$field];
            }
        }

        // Build complete payload
        $payload = [
            'imageInfo' => $imageInfo,
            'toAddress' => $this->formatAddress($labelData['toAddress']),
            'fromAddress' => $this->formatAddress($labelData['fromAddress']),
            'packageDescription' => $packageDescription,
        ];

        // Add account information from config
        $payload['payerCRID'] = config('usps.account.payer_crid');
        $payload['payerMID'] = config('usps.account.payer_mid');
        $payload['labelOwnerCRID'] = config('usps.account.label_owner_crid');
        $payload['labelOwnerMID'] = config('usps.account.label_owner_mid');

        // Add optional top-level fields
        if (isset($labelData['senderAddress'])) {
            $payload['senderAddress'] = $this->formatAddress($labelData['senderAddress']);
        }
        
        if (isset($labelData['returnAddress'])) {
            $payload['returnAddress'] = $this->formatAddress($labelData['returnAddress']);
        }
        
        if (isset($labelData['customsForm'])) {
            $payload['customsForm'] = $labelData['customsForm'];
        }

        return $payload;
    }

    /**
     * Format address according to USPS API requirements
     */
    protected function formatAddress(array $address): array
    {
        $formatted = [
            'streetAddress' => $address['streetAddress'],
            'city' => $address['city'],
            'state' => $address['state'],
            'ZIPCode' => $address['ZIPCode']
        ];

        // Handle name fields - either firmName OR firstName+lastName
        if (!empty($address['firmName'])) {
            $formatted['firmName'] = $address['firmName'];
        } else {
            $formatted['firstName'] = $address['firstName'] ?? '';
            $formatted['lastName'] = $address['lastName'] ?? '';
        }

        // Add optional fields
        $optionalAddressFields = [
            'secondaryAddress', 'ZIPPlus4', 'urbanization', 'phone', 'email',
            'ignoreBadAddress', 'parcelLockerDelivery', 'holdForPickup', 'facilityId'
        ];
        
        foreach ($optionalAddressFields as $field) {
            if (isset($address[$field])) {
                $formatted[$field] = $address[$field];
            }
        }

        return $formatted;
    }

    /**
     * Get rate indicator based on mail class
     */
    protected function getRateIndicator(string $mailClass): string
    {
        $rateIndicators = [
            'USPS_GROUND_ADVANTAGE' => 'SP',
            'PRIORITY_MAIL' => 'PM',
            'PRIORITY_MAIL_EXPRESS' => 'PME',
            'FIRST_CLASS' => 'FC',
            'MEDIA_MAIL' => 'MM',
            'LIBRARY_MAIL' => 'LM',
            'PARCEL_SELECT' => 'PS',
            'BOUND_PRINTED_MATERIAL' => 'BP',
            'USPS_CONNECT_LOCAL' => 'LC',
            'USPS_CONNECT_REGIONAL' => 'RC',
            'USPS_CONNECT_MAIL' => 'MC',
        ];

        return $rateIndicators[$mailClass] ?? 'SP';
    }

    /**
     * Comprehensive label data validation using config values
     */
    protected function validateLabelData(array $data): void
    {
        $validator = Validator::make($data, [
            // imageInfo validation using config arrays
            'imageInfo' => 'required|array',
            'imageInfo.imageType' => 'required|string|in:' . implode(',', config('usps.labels.image_types', ['PDF'])),
            'imageInfo.labelType' => 'required|string|in:' . implode(',', config('usps.labels.label_types', ['4X6LABEL'])),
            'imageInfo.receiptOption' => 'required|string|in:' . implode(',', config('usps.labels.receipt_options', ['NONE'])),
            'imageInfo.suppressPostage' => 'required|boolean',
            'imageInfo.suppressMailDate' => 'required|boolean',
            'imageInfo.returnLabel' => 'required|boolean',
            
            // fromAddress validation using config limits
            'fromAddress' => 'required|array',
            'fromAddress.streetAddress' => 'required|string|max:' . config('usps.validation.max_street_length', 100),
            'fromAddress.city' => 'required|string|max:' . config('usps.validation.max_city_length', 50),
            'fromAddress.state' => 'required|string|size:2|in:' . implode(',', config('usps.validation.states', [])),
            'fromAddress.ZIPCode' => 'required|string|regex:/^\d{5}$/',
            'fromAddress.firmName' => 'required_without_all:fromAddress.firstName,fromAddress.lastName|string|max:' . config('usps.validation.max_firm_length', 50) . '|nullable',
            'fromAddress.firstName' => 'required_without:fromAddress.firmName|string|max:' . config('usps.validation.max_first_name_length', 50) . '|nullable',
            'fromAddress.lastName' => 'required_without:fromAddress.firmName|string|max:' . config('usps.validation.max_last_name_length', 50) . '|nullable',
            
            // toAddress validation
            'toAddress' => 'required|array',
            'toAddress.streetAddress' => 'required|string|max:' . config('usps.validation.max_street_length', 100),
            'toAddress.city' => 'required|string|max:' . config('usps.validation.max_city_length', 50),
            'toAddress.state' => 'required|string|size:2|in:' . implode(',', config('usps.validation.states', [])),
            'toAddress.ZIPCode' => 'required|string|regex:/^\d{5}$/',
            'toAddress.firmName' => 'required_without_all:toAddress.firstName,toAddress.lastName|string|max:' . config('usps.validation.max_firm_length', 50) . '|nullable',
            'toAddress.firstName' => 'required_without:toAddress.firmName|string|max:' . config('usps.validation.max_first_name_length', 50) . '|nullable',
            'toAddress.lastName' => 'required_without:toAddress.firmName|string|max:' . config('usps.validation.max_last_name_length', 50) . '|nullable',
            
            // packageDescription validation using config arrays and limits
            'packageDescription' => 'required|array',
            'packageDescription.mailClass' => 'required|string|in:' . implode(',', config('usps.labels.mail_classes', [])),
            'packageDescription.processingCategory' => 'required|string|in:' . implode(',', config('usps.labels.processing_categories', [])),
            'packageDescription.destinationEntryFacilityType' => 'required|string|in:' . implode(',', config('usps.labels.destination_entry_facility_types', ['NONE'])),
            'packageDescription.weightUOM' => 'required|string|in:oz,lb',
            'packageDescription.weight' => 'required|numeric|min:0.01|max:' . config('usps.validation.max_weight', 70),
            'packageDescription.dimensionsUOM' => 'required|string|in:in,cm',
            'packageDescription.length' => 'required|numeric|min:0.1|max:' . config('usps.validation.max_length', 108),
            'packageDescription.width' => 'required|numeric|min:0.1|max:' . config('usps.validation.max_length', 108),
            'packageDescription.height' => 'required|numeric|min:0.1|max:' . config('usps.validation.max_length', 108),
            'packageDescription.mailingDate' => 'required|date|after:yesterday',
            'packageDescription.extraServices' => 'array|max:' . config('usps.validation.max_extra_services', 5),
            'packageDescription.extraServices.*' => 'integer',
            'packageDescription.customerReference' => 'array|max:' . config('usps.validation.max_customer_references', 4),
            'packageDescription.customerReference.*.referenceNumber' => 'string|max:' . config('usps.validation.max_customer_reference_length', 30),
            'packageDescription.customerReference.*.printReferenceNumber' => 'boolean',
        ], [
            'fromAddress.firmName.required_without_all' => 'From address must have either firmName OR firstName and lastName',
            'toAddress.firmName.required_without_all' => 'To address must have either firmName OR firstName and lastName',
            'packageDescription.extraServices.max' => 'Maximum ' . config('usps.validation.max_extra_services', 5) . ' extra services allowed',
            'packageDescription.customerReference.max' => 'Maximum ' . config('usps.validation.max_customer_references', 4) . ' customer references allowed',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid label data: ' . $validator->errors()->first());
        }

        // Validate mutual exclusivity for addresses
        $this->validateAddressNameFields($data['fromAddress'], 'fromAddress');
        $this->validateAddressNameFields($data['toAddress'], 'toAddress');
    }

    protected function validateAddressNameFields(array $address, string $addressType): void
    {
        $hasFirmName = !empty($address['firmName']);
        $hasPersonalName = !empty($address['firstName']) && !empty($address['lastName']);
        
        if (!$hasFirmName && !$hasPersonalName) {
            throw new \InvalidArgumentException("{$addressType} must have either firmName OR firstName and lastName");
        }
        
        if ($hasFirmName && $hasPersonalName) {
            throw new \InvalidArgumentException("{$addressType} cannot have both firmName AND firstName/lastName");
        }
    }

    /**
     * Handle API response (multipart or JSON)
     */
    protected function handleResponse($response): array
    {
        $contentType = $response->header('Content-Type');
        
        if (str_contains($contentType, 'multipart/form-data')) {
            return $this->parseMultipartResponse($response->body(), $contentType);
        }
        
        if (str_contains($contentType, 'application/vnd.usps.labels+json')) {
            return $response->json();
        }
        
        return $response->json();
    }

    /**
     * Parse multipart form-data response
     */
    protected function parseMultipartResponse(string $body, string $contentType): array
    {
        try {
            $boundary = $this->extractBoundary($contentType);
            $parts = $this->splitMultipart($body, $boundary);
            
            $result = [];
            foreach ($parts as $part) {
                $partData = $this->parseMultipartPart($part);
                if ($partData) {
                    $result[$partData['name']] = $partData['content'];
                }
            }
            
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to parse multipart response', [
                'error' => $e->getMessage(),
                'content_type' => $contentType
            ]);
            
            // Fallback to raw response
            return ['raw_response' => $body];
        }
    }

    protected function extractBoundary(string $contentType): ?string
    {
        if (preg_match('/boundary=(.*)$/i', $contentType, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B\"");
        }
        return null;
    }

    protected function splitMultipart(string $body, ?string $boundary): array
    {
        if (!$boundary) {
            return [];
        }
        
        $parts = explode("--{$boundary}", $body);
        // Remove preamble and epilogue
        array_shift($parts);
        array_pop($parts);
        
        return array_filter($parts);
    }

    protected function parseMultipartPart(string $part): ?array
    {
        $lines = explode("\r\n", trim($part));
        $headers = [];
        $content = [];
        $inHeaders = true;
        
        foreach ($lines as $line) {
            if ($inHeaders && empty(trim($line))) {
                $inHeaders = false;
                continue;
            }
            
            if ($inHeaders) {
                if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                    $headers[strtolower(trim($matches[1]))] = trim($matches[2]);
                }
            } else {
                $content[] = $line;
            }
        }
        
        $content = implode("\r\n", $content);
        
        // Extract name from Content-Disposition
        $name = null;
        if (isset($headers['content-disposition'])) {
            if (preg_match('/name="([^"]+)"/', $headers['content-disposition'], $matches)) {
                $name = $matches[1];
            }
        }
        
        if (!$name) {
            return null;
        }
        
        return [
            'name' => $name,
            'headers' => $headers,
            'content' => $content
        ];
    }

    /**
     * Retry logic with exponential backoff
     */
    protected function retry(callable $operation, string $operationName = 'Operation')
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (USPSApiException $e) {
                $lastException = $e;
                
                // Don't retry on client errors (4xx) except 429
                if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                    throw $e;
                }
                
                if ($attempt < $this->maxRetries) {
                    $delay = pow(2, $attempt) * 1000; // Exponential backoff in milliseconds
                    Log::warning("Retrying {$operationName} after {$delay}ms", [
                        'attempt' => $attempt,
                        'max_attempts' => $this->maxRetries,
                        'error' => $e->getMessage()
                    ]);
                    usleep($delay * 1000);
                }
            } catch (\Exception $e) {
                $lastException = $e;
                
                if ($attempt < $this->maxRetries) {
                    $delay = pow(2, $attempt) * 1000;
                    Log::warning("Retrying {$operationName} after {$delay}ms", [
                        'attempt' => $attempt,
                        'max_attempts' => $this->maxRetries,
                        'error' => $e->getMessage()
                    ]);
                    usleep($delay * 1000);
                }
            }
        }
        
        Log::error("All retry attempts failed for {$operationName}", [
            'max_attempts' => $this->maxRetries,
            'last_error' => $lastException ? $lastException->getMessage() : 'Unknown'
        ]);
        
        throw $lastException;
    }

    /**
     * Create Return Label
     */
    public function createReturnLabel(array $labelData): array
    {
        $this->validateReturnLabelData($labelData);
        $payload = $this->buildReturnLabelPayload($labelData);

        return $this->retry(function () use ($payload) {
            $token = $this->getAccessToken();
            $paymentToken = $this->paymentService->createPaymentAuthorization();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'X-Payment-Authorization-Token' => $paymentToken['paymentAuthorizationToken'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/return-label", $payload);

            if ($response->successful()) {
                return $this->handleResponse($response);
            }

            throw USPSApiException::fromResponse($response, 'Return label creation');
        }, 'Creating return label');
    }

    /**
     * Cancel Label or Request Refund
     */
    public function cancelLabel(string $trackingNumber): array
    {
        return $this->retry(function () use ($trackingNumber) {
            $token = $this->getAccessToken();
            $paymentToken = $this->paymentService->createPaymentAuthorization();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'X-Payment-Authorization-Token' => $paymentToken['paymentAuthorizationToken'],
                    'Accept' => 'application/json',
                ])
                ->delete("{$this->baseUrl}/label/{$trackingNumber}");

            if ($response->successful()) {
                return $response->json();
            }

            throw USPSApiException::fromResponse($response, 'Label cancellation');
        }, 'Canceling label');
    }

    /**
     * Edit Label Attributes
     */
    public function editLabel(string $trackingNumber, array $patchData): array
    {
        return $this->retry(function () use ($trackingNumber, $patchData) {
            $token = $this->getAccessToken();
            $paymentToken = $this->paymentService->createPaymentAuthorization();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'X-Payment-Authorization-Token' => $paymentToken['paymentAuthorizationToken'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->patch("{$this->baseUrl}/label/{$trackingNumber}", $patchData);

            if ($response->successful()) {
                return $response->json();
            }

            throw USPSApiException::fromResponse($response, 'Label edit');
        }, 'Editing label');
    }

    /**
     * Reprint Label
     */
    public function reprintLabel(string $trackingNumber, array $imageInfo): array
    {
        return $this->retry(function () use ($trackingNumber, $imageInfo) {
            $token = $this->getAccessToken();
            $paymentToken = $this->paymentService->createPaymentAuthorization();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'X-Payment-Authorization-Token' => $paymentToken['paymentAuthorizationToken'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->baseUrl}/label-reprint/{$trackingNumber}", ['imageInfo' => $imageInfo]);

            if ($response->successful()) {
                return $this->handleResponse($response);
            }

            throw USPSApiException::fromResponse($response, 'Label reprint');
        }, 'Reprinting label');
    }

    /**
     * Utility Methods
     */
    public function clearCachedToken(): void
    {
        $cacheKey = 'usps_labels_oauth_token_' . md5($this->clientId);
        Cache::forget($cacheKey);
    }

    public function testConnection(): array
    {
        try {
            $token = $this->getAccessToken();
            $paymentToken = $this->paymentService->createPaymentAuthorization();
            
            return [
                'success' => true,
                'message' => 'USPS Labels API connection successful',
                'environment' => $this->environment,
                'token_valid' => !empty($token),
                'payment_auth_valid' => !empty($paymentToken),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'USPS Labels API connection failed: ' . $e->getMessage(),
                'environment' => $this->environment,
            ];
        }
    }

    /**
     * Simple label creation helper
     */
    public function createSimpleLabel(
        array $fromAddress,
        array $toAddress, 
        float $weight, 
        string $mailClass = 'USPS_GROUND_ADVANTAGE',
        array $options = []
    ): array {
        // Ensure addresses have required name fields
        if (!isset($fromAddress['firmName']) && !isset($fromAddress['firstName'])) {
            $fromAddress['firmName'] = config('app.name', 'Shipping Company');
        }
        
        if (!isset($toAddress['firmName']) && !isset($toAddress['firstName'])) {
            $toAddress['firstName'] = 'Recipient';
            $toAddress['lastName'] = 'Customer';
        }

        $labelData = [
            'imageInfo' => array_merge([
                'imageType' => config('usps.defaults.image_type', 'PDF'),
                'labelType' => config('usps.defaults.label_type', '4X6LABEL'),
                'receiptOption' => config('usps.defaults.receipt_option', 'NONE'),
                'suppressPostage' => config('usps.defaults.suppress_postage', false),
                'suppressMailDate' => config('usps.defaults.suppress_mail_date', true),
                'returnLabel' => config('usps.defaults.return_label', false),
            ], $options['imageInfo'] ?? []),
            
            'fromAddress' => $fromAddress,
            'toAddress' => $toAddress,
            
            'packageDescription' => array_merge([
                'mailClass' => $mailClass,
                'rateIndicator' => $this->getRateIndicator($mailClass),
                'weightUOM' => config('usps.defaults.weight_uom', 'lb'),
                'weight' => $weight,
                'dimensionsUOM' => config('usps.defaults.dimensions_uom', 'in'),
                'length' => $options['length'] ?? 10.0,
                'width' => $options['width'] ?? 8.0,
                'height' => $options['height'] ?? 5.0,
                'processingCategory' => config('usps.defaults.processing_category', 'MACHINABLE'),
                'mailingDate' => now()->addDay()->format('Y-m-d'),
                'destinationEntryFacilityType' => config('usps.defaults.destination_entry_facility_type', 'NONE'),
                'extraServices' => $options['extraServices'] ?? [],
            ], $options['packageDescription'] ?? []),
        ];

        // Add return address if provided
        if (isset($options['returnAddress'])) {
            $labelData['returnAddress'] = $options['returnAddress'];
        }

        return $this->createLabel($labelData);
    }

    /**
     * Validate return label data (simplified version)
     */
    protected function validateReturnLabelData(array $data): void
    {
        // Similar to validateLabelData but for return labels
        $this->validateLabelData($data);
    }

    /**
     * Build return label payload
     */
    protected function buildReturnLabelPayload(array $labelData): array
    {
        $payload = $this->buildLabelPayload($labelData);
        
        // Return labels have some different defaults
        if (isset($payload['imageInfo'])) {
            $payload['imageInfo']['receiptOption'] = $payload['imageInfo']['receiptOption'] ?? 'NONE';
        }
        
        return $payload;
    }
}