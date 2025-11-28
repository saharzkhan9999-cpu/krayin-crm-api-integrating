<?php

namespace Webkul\Shipping\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Webkul\Shipping\Exceptions\USPSApiException;

class USPSAddressService
{
    protected $baseUrl;
    protected $oauthUrl;
    protected $clientId;
    protected $clientSecret;
    protected $timeout;
    protected $maxRetries;

    public function __construct()
    {
        $environment = config('usps.environment', 'testing');
        
        $this->baseUrl = config("usps.services.addresses.base_url.{$environment}");
        $this->oauthUrl = config("usps.oauth.{$environment}");
        $this->clientId = config('usps.credentials.client_id');
        $this->clientSecret = config('usps.credentials.client_secret');
        $this->timeout = config('usps.api.timeout', 30);
        $this->maxRetries = config('usps.api.retry_attempts', 3);
    }

    /**
     * Get OAuth Token with retry mechanism
     */
    public function getAccessToken(): string
    {
        return Cache::remember('usps_oauth_token', config('usps.cache.token_ttl', 3500), function () {
            $retryCount = 0;
            
            while ($retryCount <= $this->maxRetries) {
                try {
                    $response = Http::timeout($this->timeout)
                        ->asForm()
                        ->post($this->oauthUrl, [
                            'grant_type' => 'client_credentials',
                            'client_id' => $this->clientId,
                            'client_secret' => $this->clientSecret,
                            'scope' => 'addresses',
                        ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        Log::info('USPS OAuth token obtained successfully');
                        return $data['access_token'];
                    }

                    Log::warning('USPS OAuth token request failed', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                        'attempt' => $retryCount + 1
                    ]);

                    if ($retryCount === $this->maxRetries) {
                        throw new USPSApiException(
                            'Failed to obtain OAuth token after ' . $this->maxRetries . ' attempts: ' . $response->body(),
                            $response->status()
                        );
                    }

                    $retryCount++;
                    sleep(1); // Wait before retry

                } catch (\Exception $e) {
                    Log::error('USPS OAuth token exception', [
                        'error' => $e->getMessage(),
                        'attempt' => $retryCount + 1
                    ]);

                    if ($retryCount === $this->maxRetries) {
                        throw new USPSApiException('OAuth token request failed: ' . $e->getMessage(), 500);
                    }

                    $retryCount++;
                    sleep(1);
                }
            }

            throw new USPSApiException('Max retry attempts reached for OAuth token');
        });
    }

    /**
     * Standardize Address with comprehensive validation
     */
    public function standardizeAddress(array $addressData): array
    {
        $this->validateAddressData($addressData);

        $cacheKey = 'usps_standardized_address_' . md5(serialize($addressData));
        
        return Cache::remember($cacheKey, config('usps.cache.address_ttl', 86400), function () use ($addressData) {
            return $this->makeApiRequest('/address', $addressData, 'Address standardization');
        });
    }

    /**
     * Get City and State by ZIP Code
     */
    public function getCityStateByZip(string $zipCode): array
    {
        $this->validateZipCode($zipCode);

        $cacheKey = 'usps_city_state_' . $zipCode;
        
        return Cache::remember($cacheKey, config('usps.cache.address_ttl', 86400), function () use ($zipCode) {
            return $this->makeApiRequest('/city-state', ['ZIPCode' => $zipCode], 'City/State lookup');
        });
    }

    /**
     * Get ZIP Code by Address
     */
    public function getZipCodeByAddress(array $addressData): array
    {
        $this->validateZipCodeLookupData($addressData);

        $cacheKey = 'usps_zipcode_' . md5(serialize($addressData));
        
        return Cache::remember($cacheKey, config('usps.cache.address_ttl', 86400), function () use ($addressData) {
            return $this->makeApiRequest('/zipcode', $addressData, 'ZIP Code lookup');
        });
    }

    /**
     * Validate and Standardize Complete Address (Main method)
     */
    public function validateAddress(
        string $streetAddress,
        string $state,
        ?string $city = null,
        ?string $zipCode = null,
        ?string $firm = null,
        ?string $secondaryAddress = null,
        ?string $urbanization = null
    ): array {
        $addressData = array_filter([
            'streetAddress' => $streetAddress,
            'state' => $state,
            'city' => $city,
            'ZIPCode' => $zipCode,
            'firm' => $firm,
            'secondaryAddress' => $secondaryAddress,
            'urbanization' => $urbanization,
        ], fn($value) => !empty($value));

        return $this->standardizeAddress($addressData);
    }

    /**
     * Extract standardized address components for easy use
     */
    public function extractStandardizedAddress(array $apiResponse): array
    {
        $address = $apiResponse['address'] ?? [];
        $additionalInfo = $apiResponse['additionalInfo'] ?? [];

        return [
            'street_address' => $address['streetAddress'] ?? null,
            'street_address_abbreviation' => $address['streetAddressAbbreviation'] ?? null,
            'secondary_address' => $address['secondaryAddress'] ?? null,
            'city' => $address['city'] ?? null,
            'city_abbreviation' => $address['cityAbbreviation'] ?? null,
            'state' => $address['state'] ?? null,
            'zip_code' => $address['ZIPCode'] ?? null,
            'zip_plus4' => $address['ZIPPlus4'] ?? null,
            'urbanization' => $address['urbanization'] ?? null,
            'delivery_point' => $additionalInfo['deliveryPoint'] ?? null,
            'carrier_route' => $additionalInfo['carrierRoute'] ?? null,
            'dpv_confirmation' => $additionalInfo['DPVConfirmation'] ?? null,
            'business' => $additionalInfo['business'] ?? null,
            'vacant' => $additionalInfo['vacant'] ?? null,
            'is_valid' => $this->isAddressValid($apiResponse),
            'corrections' => $apiResponse['corrections'] ?? [],
            'warnings' => $apiResponse['warnings'] ?? [],
        ];
    }

    /**
     * Check if address is valid based on API response
     */
    public function isAddressValid(array $apiResponse): bool
    {
        $additionalInfo = $apiResponse['additionalInfo'] ?? [];
        
        // Address is considered valid if DPV confirmation is Y, D, or S
        $dpvConfirmation = $additionalInfo['DPVConfirmation'] ?? null;
        $isDpvConfirmed = in_array($dpvConfirmation, ['Y', 'D', 'S']);
        
        // And there are no major corrections needed
        $corrections = $apiResponse['corrections'] ?? [];
        $hasMajorCorrections = !empty($corrections);

        return $isDpvConfirmed && !$hasMajorCorrections;
    }

    /**
     * Batch validate multiple addresses
     */
    public function validateMultipleAddresses(array $addresses): array
    {
        $results = [];

        foreach ($addresses as $index => $address) {
            try {
                $results[$index] = [
                    'success' => true,
                    'data' => $this->validateAddress(
                        $address['streetAddress'] ?? '',
                        $address['state'] ?? '',
                        $address['city'] ?? null,
                        $address['zipCode'] ?? null,
                        $address['firm'] ?? null,
                        $address['secondaryAddress'] ?? null,
                        $address['urbanization'] ?? null
                    )
                ];
            } catch (USPSApiException $e) {
                $results[$index] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ];
            }
        }

        return $results;
    }

    /**
     * Make API request with retry logic
     */
    protected function makeApiRequest(string $endpoint, array $params, string $operation): array
    {
        $retryCount = 0;

        while ($retryCount <= $this->maxRetries) {
            try {
                $token = $this->getAccessToken();

                $response = Http::timeout($this->timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$token}",
                        'Content-Type' => 'application/json',
                    ])
                    ->get($this->baseUrl . $endpoint, $params);

                if ($response->successful()) {
                    Log::info("USPS {$operation} successful", ['endpoint' => $endpoint]);
                    return $response->json();
                }

                // Handle specific error cases
                if ($response->status() === 429) {
                    Log::warning('USPS rate limit hit', ['operation' => $operation]);
                    $retryCount++;
                    sleep(2); // Wait longer for rate limits
                    continue;
                }

                throw new USPSApiException(
                    "{$operation} failed: " . $response->body(),
                    $response->status()
                );

            } catch (USPSApiException $e) {
                throw $e; // Re-throw our custom exceptions
            } catch (\Exception $e) {
                Log::error("USPS {$operation} exception", [
                    'error' => $e->getMessage(),
                    'endpoint' => $endpoint,
                    'attempt' => $retryCount + 1
                ]);

                if ($retryCount === $this->maxRetries) {
                    throw new USPSApiException("{$operation} failed after retries: " . $e->getMessage(), 500);
                }

                $retryCount++;
                sleep(1);
            }
        }

        throw new USPSApiException("{$operation} failed after {$this->maxRetries} retries");
    }

    /**
     * Validation methods
     */
    protected function validateAddressData(array $data): void
    {
        $validator = Validator::make($data, [
            'streetAddress' => 'required|string|max:100',
            'state' => 'required|string|size:2|in:' . implode(',', config('usps.validation.states', [])),
            'city' => 'nullable|string|max:50',
            'ZIPCode' => 'nullable|string|regex:/^\d{5}$/',
            'firm' => 'nullable|string|max:50',
            'secondaryAddress' => 'nullable|string|max:50',
            'urbanization' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            throw new USPSApiException('Invalid address data: ' . $validator->errors()->first(), 400);
        }

        // Ensure either city or ZIP is provided
        if (empty($data['city']) && empty($data['ZIPCode'])) {
            throw new USPSApiException('Either city or ZIP code must be provided', 400);
        }
    }

    protected function validateZipCode(string $zipCode): void
    {
        if (!preg_match('/^\d{5}$/', $zipCode)) {
            throw new USPSApiException('Invalid ZIP code format. Must be 5 digits.', 400);
        }
    }

    protected function validateZipCodeLookupData(array $data): void
    {
        $validator = Validator::make($data, [
            'streetAddress' => 'required|string|max:100',
            'city' => 'required|string|max:50',
            'state' => 'required|string|size:2|in:' . implode(',', config('usps.validation.states', [])),
            'firm' => 'nullable|string|max:50',
            'secondaryAddress' => 'nullable|string|max:50',
            'ZIPCode' => 'nullable|string|regex:/^\d{5}$/',
            'ZIPPlus4' => 'nullable|string|regex:/^\d{4}$/',
        ]);

        if ($validator->fails()) {
            throw new USPSApiException('Invalid ZIP code lookup data: ' . $validator->errors()->first(), 400);
        }
    }

    /**
     * Utility methods
     */
    public function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget($key);
        } else {
            Cache::forget('usps_oauth_token');
            // You might want to clear all USPS-related cache
            Cache::forget('usps_standardized_address_*');
            Cache::forget('usps_city_state_*');
            Cache::forget('usps_zipcode_*');
        }
    }

    public function getConfigInfo(): array
    {
        return [
            'environment' => config('usps.environment', 'testing'),
            'base_url' => $this->baseUrl,
            'oauth_url' => $this->oauthUrl,
            'timeout' => $this->timeout,
            'max_retries' => $this->maxRetries,
            'has_credentials' => !empty($this->clientId) && !empty($this->clientSecret),
            'cache_ttl' => config('usps.cache.token_ttl', 3500),
        ];
    }

    public function testConnection(): array
    {
        try {
            $config = $this->getConfigInfo();
            $token = $this->getAccessToken();
            $testResult = $this->getCityStateByZip('90210');
            
            return [
                'success' => true,
                'message' => 'USPS Address API connection successful',
                'environment' => $config['environment'],
                'test_data' => $testResult
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'USPS Address API connection failed: ' . $e->getMessage(),
                'environment' => config('usps.environment', 'testing')
            ];
        }
    }
}