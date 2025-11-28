<?php

namespace Webkul\Shipping\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Webkul\Shipping\Contracts\UspsInternationalInterface;
use Webkul\Shipping\Exceptions\USPSApiException;

class UspsInternationalService implements UspsInternationalInterface
{
    protected $baseUrl;
    protected $environment;
    protected $timeout;
    protected $maxRetries;
    protected $paymentService;

    public function __construct(USPSPaymentService $paymentService)
    {
        $this->environment = config('usps.environment', 'testing');
        $this->validateConfiguration();
        
        $this->baseUrl = config("usps.services.international_labels.base_url.{$this->environment}");
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
            "usps.services.international_labels.base_url.{$this->environment}",
            'usps.credentials.client_id',
            'usps.credentials.client_secret',
            'usps.account.payer_crid',
            'usps.account.payer_mid',
            'usps.account.label_owner_crid',
            'usps.account.label_owner_mid',
        ];

        $missing = [];
        foreach ($requiredConfig as $configKey) {
            if (empty(config($configKey))) {
                $missing[] = $configKey;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                "Missing required USPS International Labels configuration: " . implode(', ', $missing)
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
        $cacheKey = 'usps_international_oauth_token_' . md5(config('usps.credentials.client_id'));
        
        return Cache::remember($cacheKey, config('usps.cache.token_ttl', 3500), function () {
            return $this->retry(function () {
                $response = Http::timeout($this->timeout)
                    ->asForm()
                    ->post(config("usps.oauth.{$this->environment}"), [
                        'grant_type' => 'client_credentials',
                        'client_id' => config('usps.credentials.client_id'),
                        'client_secret' => config('usps.credentials.client_secret'),
                        'scope' => 'international-labels',
                    ]);

                if (!$response->successful()) {
                    Log::error('USPS International Labels OAuth Token Failed', [
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
 * Create International Shipping Label
 */
public function createInternationalLabel(array $labelData): array
{
    $validationResult = $this->validateInternationalLabelData($labelData);
    if (!$validationResult['valid']) {
        throw new \InvalidArgumentException('Invalid international label data: ' . implode(', ', $validationResult['errors']));
    }

    $payload = $this->buildInternationalLabelPayload($labelData);

    Log::debug('USPS International Label Request Payload', [
        'payload' => $payload,
        'json_payload' => json_encode($payload, JSON_PRETTY_PRINT)
    ]);

    return $this->retry(function () use ($payload) {
        $token = $this->getAccessToken();
        $paymentToken = $this->paymentService->createPaymentAuthorization();

        Log::info('USPS International Label Creation Request', [
            'mail_class' => $payload['packageDescription']['mailClass'] ?? 'unknown',
            'from_zip' => $payload['fromAddress']['ZIPCode'] ?? 'unknown',
            'to_country' => $payload['toAddress']['countryISOAlpha2Code'] ?? 'unknown',
            'customs_type' => $payload['customsForm']['customsContentType'] ?? 'unknown',
        ]);

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => "Bearer {$token}",
                'X-Payment-Authorization-Token' => $paymentToken['paymentAuthorizationToken'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post("{$this->baseUrl}/international-label", $payload);

        Log::debug('USPS International Label Response', [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body_preview' => substr($response->body(), 0, 500)
        ]);

        if ($response->successful()) {
            $result = $this->handleInternationalResponse($response);
            
            // Validate that we have the expected structure
            if (!isset($result['labelMetadata']) || !is_array($result['labelMetadata'])) {
                Log::error('Invalid label response structure', ['result' => $result]);
                throw new \RuntimeException('Invalid response structure from USPS API');
            }
            
            Log::info('USPS International Label Created Successfully', [
                'tracking_number' => $result['labelMetadata']['internationalTrackingNumber'] ?? 'unknown',
                'total_cost' => $result['labelMetadata']['postage'] ?? 0,
                'service' => $payload['packageDescription']['mailClass'] ?? 'unknown',
            ]);
            
            return $result;
        }

        throw USPSApiException::fromResponse($response, 'International label creation');
        
    }, 'Creating international shipping label');
}
    /**
     * Reprint International Label
     */
    public function reprintInternationalLabel(string $trackingNumber, array $imageInfo): array
    {
        $this->validateReprintData($trackingNumber, $imageInfo);

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
                ->post("{$this->baseUrl}/international-label-reprint/{$trackingNumber}", [
                    'imageInfo' => $imageInfo
                ]);

            if ($response->successful()) {
                return $this->handleInternationalResponse($response);
            }

            throw USPSApiException::fromResponse($response, 'International label reprint');
        }, 'Reprinting international label');
    }

    /**
     * Cancel International Label
     */
    public function cancelInternationalLabel(string $trackingNumber): array
    {
        $this->validateTrackingNumber($trackingNumber);

        return $this->retry(function () use ($trackingNumber) {
            $token = $this->getAccessToken();
            $paymentToken = $this->paymentService->createPaymentAuthorization();

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'X-Payment-Authorization-Token' => $paymentToken['paymentAuthorizationToken'],
                    'Accept' => 'application/json',
                ])
                ->delete("{$this->baseUrl}/international-label/{$trackingNumber}");

            if ($response->successful()) {
                $result = $response->json();
                Log::info('USPS International Label Cancellation Successful', [
                    'tracking_number' => $trackingNumber,
                    'status' => $result['status'] ?? 'unknown',
                ]);
                return $result;
            }

            throw USPSApiException::fromResponse($response, 'International label cancellation');
        }, 'Canceling international label');
    }

   /**
 * Build complete international label payload
 */
protected function buildInternationalLabelPayload(array $labelData): array
{
    // Build imageInfo with null coalescing for safety
    $imageInfo = [
        'imageType' => $labelData['imageInfo']['imageType'] ?? config('usps.defaults.image_type', 'PDF'),
        'labelType' => $labelData['imageInfo']['labelType'] ?? config('usps.defaults.label_type', '4X6LABEL'),
        'suppressPostage' => (bool) ($labelData['imageInfo']['suppressPostage'] ?? config('usps.defaults.suppress_postage', false)),
        'includeLabelBrokerPDF' => (bool) ($labelData['imageInfo']['includeLabelBrokerPDF'] ?? false),
        'addZPLComments' => (bool) ($labelData['imageInfo']['addZPLComments'] ?? false),
    ];

    // Build packageDescription - handle packagingType and rateIndicator mutual exclusivity
    $packageDescription = [
        'mailClass' => $labelData['packageDescription']['mailClass'],
        'weightUOM' => $labelData['packageDescription']['weightUOM'] ?? config('usps.defaults.weight_uom', 'lb'),
        'weight' => (float) $labelData['packageDescription']['weight'],
        'dimensionsUOM' => $labelData['packageDescription']['dimensionsUOM'] ?? config('usps.defaults.dimensions_uom', 'in'),
        'length' => (float) ($labelData['packageDescription']['length'] ?? 0),
        'width' => (float) ($labelData['packageDescription']['width'] ?? 0),
        'height' => (float) ($labelData['packageDescription']['height'] ?? 0),
        'processingCategory' => $labelData['packageDescription']['processingCategory'],
        'destinationEntryFacilityType' => $labelData['packageDescription']['destinationEntryFacilityType'] ?? config('usps.defaults.destination_entry_facility_type', 'NONE'),
        'mailingDate' => $labelData['packageDescription']['mailingDate'],
    ];

    // Handle packagingType and rateIndicator - they are mutually exclusive
    if (isset($labelData['packageDescription']['packagingType']) && 
        !empty($labelData['packageDescription']['packagingType'])) {
        $packageDescription['packagingType'] = $labelData['packageDescription']['packagingType'];
    } elseif (isset($labelData['packageDescription']['rateIndicator']) && 
              !empty($labelData['packageDescription']['rateIndicator'])) {
        $packageDescription['rateIndicator'] = $labelData['packageDescription']['rateIndicator'];
    } else {
        // Default to rateIndicator if neither is provided
        $packageDescription['rateIndicator'] = $this->getInternationalRateIndicator($labelData['packageDescription']['mailClass']);
    }

    // Add optional package fields (EXCLUDE packagingType and rateIndicator from this loop)
    $optionalPackageFields = [
        'girth', 'shape', 'diameter', 'extraServices', 'customerReference',
        'packageOptions', 'inductionZIPCode', 'mailOwnerMID', 'logisticsManagerMID'
    ];
    
    foreach ($optionalPackageFields as $field) {
        if (isset($labelData['packageDescription'][$field]) && 
            !empty($labelData['packageDescription'][$field])) {
            $packageDescription[$field] = $labelData['packageDescription'][$field];
        }
    }

    // Build the main payload
    $payload = [
        'imageInfo' => $imageInfo,
        'toAddress' => $this->formatInternationalAddress($labelData['toAddress']),
        'fromAddress' => $this->formatDomesticAddress($labelData['fromAddress']),
        'packageDescription' => $packageDescription,
        'customsForm' => $this->buildCustomsForm($labelData['customsForm']),
    ];

    // Add optional addresses only if they exist and are not empty
    if (isset($labelData['senderAddress']) && !empty(array_filter($labelData['senderAddress']))) {
        $payload['senderAddress'] = $this->formatDomesticAddress($labelData['senderAddress']);
    }
    
    if (isset($labelData['returnAddress']) && !empty(array_filter($labelData['returnAddress']))) {
        $payload['returnAddress'] = $this->formatDomesticAddress($labelData['returnAddress']);
    }

    return $payload;
}
    /**
     * Format international address
     */
    protected function formatInternationalAddress(array $address): array
    {
        $formatted = [
            'streetAddress' => $address['streetAddress'],
            'city' => $address['city'],
            'country' => $address['country'],
            'countryISOAlpha2Code' => $address['countryISOAlpha2Code'],
        ];

        // Handle name fields
        if (!empty($address['firmName'])) {
            $formatted['firmName'] = $address['firmName'];
        } else {
            $formatted['firstName'] = $address['firstName'] ?? '';
            $formatted['lastName'] = $address['lastName'] ?? '';
        }

        // Add optional fields
        $optionalFields = [
            'postalCode', 'province', 'phone', 'email', 'secondaryAddress'
        ];
        
        foreach ($optionalFields as $field) {
            if (isset($address[$field])) {
                $formatted[$field] = $address[$field];
            }
        }

        return $formatted;
    }

    /**
     * Format domestic address
     */
    protected function formatDomesticAddress(array $address): array
    {
        $formatted = [
            'streetAddress' => $address['streetAddress'],
            'city' => $address['city'],
            'state' => $address['state'],
            'ZIPCode' => $address['ZIPCode']
        ];

        // Handle name fields
        if (!empty($address['firmName'])) {
            $formatted['firmName'] = $address['firmName'];
        } else {
            $formatted['firstName'] = $address['firstName'] ?? '';
            $formatted['lastName'] = $address['lastName'] ?? '';
        }

        // Add optional fields
        $optionalFields = [
            'secondaryAddress', 'ZIPPlus4', 'urbanization', 'phone', 'email'
        ];
        
        foreach ($optionalFields as $field) {
            if (isset($address[$field])) {
                $formatted[$field] = $address[$field];
            }
        }

        return $formatted;
    }

    /**
     * Build customs form data
     */
    protected function buildCustomsForm(array $customsData): array
    {
        $customsForm = [
            'AESITN' => $customsData['AESITN'],
            'customsContentType' => $customsData['customsContentType'],
            'contents' => array_map(function ($item) {
                $contentItem = [
                    'itemDescription' => $item['itemDescription'],
                    'itemQuantity' => (int) $item['itemQuantity'],
                    'itemTotalValue' => (float) $item['itemTotalValue'],
                    'itemTotalWeight' => (float) $item['itemTotalWeight'],
                    'countryofOrigin' => $item['countryofOrigin'],
                    'weightUOM' => $item['weightUOM'] ?? 'lb',
                ];

                // Add optional fields
                if (isset($item['HSTariffNumber'])) {
                    $contentItem['HSTariffNumber'] = $item['HSTariffNumber'];
                }
                if (isset($item['itemCategory'])) {
                    $contentItem['itemCategory'] = $item['itemCategory'];
                }
                if (isset($item['itemSubcategory'])) {
                    $contentItem['itemSubcategory'] = $item['itemSubcategory'];
                }

                return $contentItem;
            }, $customsData['contents'])
        ];

        // Add optional customs fields
        $optionalCustomsFields = [
            'contentComments', 'restrictionType', 'restrictionComments',
            'invoiceNumber', 'licenseNumber', 'certificateNumber',
            'importersReference', 'exportersReference'
        ];
        
        foreach ($optionalCustomsFields as $field) {
            if (isset($customsData[$field])) {
                $customsForm[$field] = $customsData[$field];
            }
        }

        return $customsForm;
    }

    /**
     * Validate international label data
     */
    public function validateInternationalLabelData(array $data): array
    {
        $validator = Validator::make($data, [
            // ImageInfo validation
            'imageInfo.imageType' => 'required|string|in:LABEL_BROKER,PDF,TIFF,ZPL203DPI,ZPL300DPI,NONE',
            'imageInfo.labelType' => 'required|string|in:4X6LABEL',
            'imageInfo.suppressPostage' => 'boolean',
            'imageInfo.includeLabelBrokerPDF' => 'boolean',
            'imageInfo.addZPLComments' => 'boolean',

            // FromAddress validation (domestic)
            'fromAddress' => 'required|array',
            'fromAddress.streetAddress' => 'required|string|max:50',
            'fromAddress.city' => 'required|string|max:28',
            'fromAddress.state' => 'required|string|size:2|in:' . implode(',', config('usps.validation.states', [])),
            'fromAddress.ZIPCode' => 'required|string|regex:/^\d{5}$/',
            'fromAddress.firmName' => 'required_without_all:fromAddress.firstName,fromAddress.lastName|string|max:50|nullable',
            'fromAddress.firstName' => 'required_without:fromAddress.firmName|string|max:50|nullable',
            'fromAddress.lastName' => 'required_without:fromAddress.firmName|string|max:50|nullable',

            // ToAddress validation (international)
            'toAddress' => 'required|array',
            'toAddress.streetAddress' => 'required|string|max:50',
            'toAddress.city' => 'required|string|max:28',
            'toAddress.country' => 'required|string|max:50',
            'toAddress.countryISOAlpha2Code' => 'required|string|size:2',
            'toAddress.postalCode' => 'sometimes|string|max:11',
            'toAddress.province' => 'sometimes|string|max:40',
            'toAddress.firmName' => 'required_without_all:toAddress.firstName,toAddress.lastName|string|max:50|nullable',
            'toAddress.firstName' => 'required_without:toAddress.firmName|string|max:50|nullable',
            'toAddress.lastName' => 'required_without:toAddress.firmName|string|max:50|nullable',

            // PackageDescription validation
            'packageDescription' => 'required|array',
            'packageDescription.mailClass' => 'required|string|in:' . implode(',', config('usps.labels.international.mail_classes', [])),
            'packageDescription.processingCategory' => 'required|string|in:LETTERS,FLATS,MACHINABLE,NONSTANDARD',
            'packageDescription.destinationEntryFacilityType' => 'required|string|in:NONE,INTERNATIONAL_SERVICE_CENTER',
            'packageDescription.weightUOM' => 'required|string|in:lb',
            'packageDescription.weight' => 'required|numeric|min:0.01|max:70',
            'packageDescription.dimensionsUOM' => 'required|string|in:in',
            'packageDescription.length' => 'required|numeric|min:0.1|max:108',
            'packageDescription.width' => 'required|numeric|min:0.1|max:108',
            'packageDescription.height' => 'required|numeric|min:0.1|max:108',
            'packageDescription.mailingDate' => 'required|date|after:yesterday',
            'packageDescription.rateIndicator' => 'sometimes|string|in:E4,E6,FA,FB,FE,FP,FS,PA,PL,SP',
            'packageDescription.packagingType' => 'sometimes|string|in:FLAT_RATE_ENVELOPE,LEGAL_FLAT_RATE_ENVELOPE,PADDED_FLAT_RATE_ENVELOPE,SM_FLAT_RATE_BOX,MD_FLAT_RATE_BOX,LG_FLAT_RATE_BOX,VARIABLE',
            'packageDescription.extraServices' => 'array|max:3',
            'packageDescription.extraServices.*' => 'integer|in:' . implode(',', array_keys(config('usps.labels.international.extra_services', []))),

            // CustomsForm validation
            'customsForm' => 'required|array',
            'customsForm.AESITN' => 'required|string|max:35',
            'customsForm.customsContentType' => 'required|string|in:' . implode(',', config('usps.labels.international.customs_content_types', [])),
            'customsForm.contents' => 'required|array|min:1|max:30',
            'customsForm.contents.*.itemDescription' => 'required|string|max:30',
            'customsForm.contents.*.itemQuantity' => 'required|integer|min:1|max:9999',
            'customsForm.contents.*.itemTotalValue' => 'required|numeric|min:0.01|max:999999.99',
            'customsForm.contents.*.itemTotalWeight' => 'required|numeric|min:0.0001',
            'customsForm.contents.*.countryofOrigin' => 'required|string|size:2',
            'customsForm.contents.*.HSTariffNumber' => 'sometimes|string|min:6|max:14',
            'customsForm.contentComments' => 'sometimes|string|max:25',
            'customsForm.restrictionType' => 'sometimes|string|in:QUARANTINE,SANITARY_INSPECTION,PHYTOSANITARY_INSPECTION,OTHER',
            'customsForm.restrictionComments' => 'required_if:customsForm.restrictionType,OTHER|string|max:25',
            'customsForm.invoiceNumber' => 'sometimes|string|max:15',
            'customsForm.licenseNumber' => 'sometimes|string|max:16',
            'customsForm.certificateNumber' => 'sometimes|string|max:12',
        ], [
            'fromAddress.firmName.required_without_all' => 'From address must have either firmName OR firstName and lastName',
            'toAddress.firmName.required_without_all' => 'To address must have either firmName OR firstName and lastName',
            'customsForm.restrictionComments.required_if' => 'Restriction comments are required when restriction type is OTHER',
            'packageDescription.extraServices.max' => 'Maximum 3 extra services allowed for international labels',
        ]);

        // Validate address name field mutual exclusivity
        $errors = [];
        if ($validator->fails()) {
            $errors = $validator->errors()->all();
        }

        // Validate address name fields
        try {
            $this->validateAddressNameFields($data['fromAddress'], 'fromAddress');
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        try {
            $this->validateAddressNameFields($data['toAddress'], 'toAddress');
        } catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }

        // Validate customs form for dangerous goods
        if (isset($data['customsForm']['customsContentType']) && 
            $data['customsForm']['customsContentType'] === 'DANGEROUS_GOODS' &&
            empty($data['packageDescription']['extraServices'])) {
            $errors[] = 'Dangerous goods require appropriate hazardous materials extra services';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
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

    protected function validateReprintData(string $trackingNumber, array $imageInfo): void
    {
        $validator = Validator::make([
            'trackingNumber' => $trackingNumber,
            'imageInfo' => $imageInfo
        ], [
            'trackingNumber' => 'required|string|min:13|max:13',
            'imageInfo.imageType' => 'required|string|in:PDF,TIFF,ZPL203DPI,ZPL300DPI',
            'imageInfo.labelType' => 'required|string|in:4X6LABEL',
            'imageInfo.suppressPostage' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid reprint data: ' . $validator->errors()->first());
        }
    }

    protected function validateTrackingNumber(string $trackingNumber): void
    {
        if (strlen($trackingNumber) !== 13) {
            throw new \InvalidArgumentException('Tracking number must be exactly 13 characters');
        }
    }

    /**
 * Handle international API response
 */
protected function handleInternationalResponse($response): array
{
    $contentType = $response->header('Content-Type');
    
    Log::debug('USPS International Response Content-Type', ['content_type' => $contentType]);
    
    if (str_contains($contentType, 'multipart/form-data')) {
        $multipartResult = $this->parseMultipartResponse($response->body(), $contentType);
        return $this->processLabelResponse($multipartResult);
    }
    
    if (str_contains($contentType, 'application/vnd.usps.labels+json')) {
        $vendorResult = $response->json();
        return $this->processLabelResponse($vendorResult);
    }
    
    // For regular JSON responses
    $jsonResult = $response->json();
    return $this->processLabelResponse($jsonResult);
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
     * Get international rate indicator
     */
protected function getInternationalRateIndicator(string $mailClass): string
{
    $rateIndicators = [
        'FIRST-CLASS_PACKAGE_INTERNATIONAL_SERVICE' => 'SP',
        'PRIORITY_MAIL_INTERNATIONAL' => 'SP', // CHANGED FROM 'PM' TO 'SP'
        'PRIORITY_MAIL_EXPRESS_INTERNATIONAL' => 'PA',
    ];

    return $rateIndicators[$mailClass] ?? 'SP';
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
                    $delay = pow(2, $attempt) * 1000;
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
     * Test connection to USPS International API
     */
    public function testConnection(): array
    {
        try {
            $token = $this->getAccessToken();
            $paymentToken = $this->paymentService->createPaymentAuthorization();
            
            return [
                'success' => true,
                'message' => 'USPS International Labels API connection successful',
                'environment' => $this->environment,
                'token_valid' => !empty($token),
                'payment_auth_valid' => !empty($paymentToken),
                'base_url' => $this->baseUrl,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'USPS International Labels API connection failed: ' . $e->getMessage(),
                'environment' => $this->environment,
                'base_url' => $this->baseUrl,
            ];
        }
    }

    /**
     * Get supported countries
     */
    public function getSupportedCountries(): array
    {
        // This would typically come from a configuration or API call
        // For now, returning a sample list
        return [
            'AU' => 'Australia',
            'CA' => 'Canada',
            'CN' => 'China',
            'FR' => 'France',
            'DE' => 'Germany',
            'JP' => 'Japan',
            'MX' => 'Mexico',
            'GB' => 'United Kingdom',
            // Add more countries as needed
        ];
    }

    /**
     * Get international rates
     */
    public function getRateInternational(array $rateData): array
    {
        // This would integrate with the USPS Prices API
        // For now, this is a placeholder
        throw new \RuntimeException('International rates API not implemented yet');
    }

    /**
     * Clear cached tokens
     */
    public function clearCachedToken(): void
    {
        $cacheKey = 'usps_international_oauth_token_' . md5(config('usps.credentials.client_id'));
        Cache::forget($cacheKey);
        
        Log::info('USPS International tokens cleared');
    }

    /**
     * Simple international label creation helper
     */
    public function createSimpleInternationalLabel(
        array $fromAddress,
        array $toAddress,
        array $customsData,
        float $weight,
        string $mailClass = 'PRIORITY_MAIL_INTERNATIONAL',
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
                'suppressPostage' => config('usps.defaults.suppress_postage', false),
            ], $options['imageInfo'] ?? []),
            
            'fromAddress' => $fromAddress,
            'toAddress' => $toAddress,
            
            'packageDescription' => array_merge([
                'mailClass' => $mailClass,
                'rateIndicator' => $this->getInternationalRateIndicator($mailClass),
                'weightUOM' => config('usps.defaults.weight_uom', 'lb'),
                'weight' => $weight,
                'dimensionsUOM' => config('usps.defaults.dimensions_uom', 'in'),
                'length' => $options['length'] ?? 10.0,
                'width' => $options['width'] ?? 8.0,
                'height' => $options['height'] ?? 5.0,
                'processingCategory' => $options['processingCategory'] ?? 'MACHINABLE',
                'destinationEntryFacilityType' => config('usps.defaults.destination_entry_facility_type', 'NONE'),
                'mailingDate' => now()->addDay()->format('Y-m-d'),
                'extraServices' => $options['extraServices'] ?? [],
            ], $options['packageDescription'] ?? []),

            'customsForm' => $customsData,
        ];

        return $this->createInternationalLabel($labelData);
    }
    /**
 * Process USPS API response - handles multipart and JSON responses
 */
protected function processLabelResponse(array $response): array
{
    $processed = [];
    
    // Process labelMetadata if it's a JSON string
    if (isset($response['labelMetadata']) && is_string($response['labelMetadata'])) {
        $processed['labelMetadata'] = json_decode($response['labelMetadata'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to decode labelMetadata JSON', [
                'json_error' => json_last_error_msg(),
                'labelMetadata' => substr($response['labelMetadata'], 0, 200)
            ]);
            throw new \RuntimeException('Failed to parse label metadata from USPS response');
        }
    } else {
        $processed['labelMetadata'] = $response['labelMetadata'] ?? [];
    }
    
    // Process label image - handle both key variations
    $processed['labelImage'] = $response['labelImage.pdf'] ?? $response['labelImage'] ?? null;
    
    // Handle any other parts that might be in the response
    foreach ($response as $key => $value) {
        if (!in_array($key, ['labelMetadata', 'labelImage', 'labelImage.pdf'])) {
            $processed[$key] = $value;
        }
    }
    
    return $processed;
}
}