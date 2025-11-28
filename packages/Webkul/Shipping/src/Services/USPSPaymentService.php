<?php

namespace Webkul\Shipping\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class USPSPaymentService
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
        
        $this->validateConfiguration($environment);
        
        $this->baseUrl = config("usps.services.payments.base_url.{$environment}");
        $this->oauthUrl = config("usps.oauth.{$environment}");
        $this->clientId = config('usps.credentials.client_id');
        $this->clientSecret = config('usps.credentials.client_secret');
        $this->timeout = config('usps.api.timeout', 30);
        $this->maxRetries = config('usps.api.retry_attempts', 3);
    }

    /**
     * Validate critical configuration
     */
    protected function validateConfiguration(string $environment): void
    {
        $requiredConfig = [
            "usps.services.payments.base_url.{$environment}",
            "usps.oauth.{$environment}",
            'usps.credentials.client_id',
            'usps.credentials.client_secret',
            'usps.account.payer_crid',
            'usps.account.payer_mid',
            'usps.account.label_owner_crid',
            'usps.account.label_owner_mid',
            'usps.account.account_number'
        ];

        foreach ($requiredConfig as $configKey) {
            if (empty(config($configKey))) {
                throw new \RuntimeException("Missing required USPS configuration: {$configKey}");
            }
        }

        if (!in_array($environment, ['testing', 'staging', 'production'])) {
            throw new \RuntimeException("Invalid USPS environment: {$environment}");
        }
    }

    /**
     * Get OAuth Token with retry logic and proper error handling
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'usps_payments_oauth_token_' . md5($this->clientId);
        
        return Cache::remember($cacheKey, config('usps.cache.token_ttl', 3500), function () {
            return $this->retry(function () {
                if (empty($this->clientId) || empty($this->clientSecret)) {
                    throw new \RuntimeException('USPS credentials are not configured.');
                }

                $response = Http::timeout($this->timeout)
                    ->asForm()
                    ->retry(3, 100)
                    ->post($this->oauthUrl, [
                        'grant_type' => 'client_credentials',
                        'client_id' => $this->clientId,
                        'client_secret' => $this->clientSecret,
                        'scope' => 'payments', // Payments scope only as per USPS spec
                    ]);

                if (!$response->successful()) {
                    Log::error('USPS Payments OAuth Token Failed', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                        'environment' => config('usps.environment')
                    ]);
                    
                    throw new \RuntimeException('Failed to get USPS OAuth token. Status: ' . $response->status());
                }

                $data = $response->json();
                
                if (!isset($data['access_token'])) {
                    throw new \RuntimeException('Access token not found in OAuth response');
                }

                Log::info('USPS Payments OAuth token obtained', [
                    'environment' => config('usps.environment'),
                    'token_length' => strlen($data['access_token'])
                ]);

                return $data['access_token'];
            });
        });
    }

    /**
     * Create Payment Authorization Token (Main Method)
     */
    public function createPaymentAuthorization(array $roles = []): array
    {
        $this->validateRoles($roles);
        
        return $this->retry(function () use ($roles) {
            $token = $this->getAccessToken();
            
            $payload = [
                'roles' => empty($roles) ? $this->getDefaultRoles() : $roles
            ];

            Log::info('USPS Payment Authorization Request', [
                'endpoint' => $this->baseUrl . '/payment-authorization',
                'roles_count' => count($payload['roles']),
                'environment' => config('usps.environment')
            ]);

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->retry(2, 100)
                ->post("{$this->baseUrl}/payment-authorization", $payload);

            if ($response->successful()) {
                $result = $response->json();
                
                if (empty($result['paymentAuthorizationToken'])) {
                    throw new \RuntimeException('Payment authorization token is empty in response');
                }
                
                Log::info('USPS Payment Authorization Successful', [
                    'token_length' => strlen($result['paymentAuthorizationToken']),
                    'environment' => config('usps.environment')
                ]);
                
                return $result;
            }

            $this->handleErrorResponse($response, 'Payment authorization');
            
        }, 'Creating payment authorization');
    }

    /**
     * Get Payment Account Information with validation
     */
    public function getPaymentAccount(string $accountNumber, string $accountType, ?float $amount = null): array
    {
        $this->validateAccountParameters($accountNumber, $accountType, $amount);

        return $this->retry(function () use ($accountNumber, $accountType, $amount) {
            $token = $this->getAccessToken();

            $queryParams = ['accountType' => $accountType];
            if ($amount !== null) {
                $queryParams['amount'] = number_format($amount, 2, '.', '');
            }

            // Add permitZIPCode for PERMIT accounts
            if ($accountType === 'PERMIT') {
                $queryParams['permitZIPCode'] = config('usps.payment.default_letter_origin', '10001');
            }

            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/payment-account/{$accountNumber}", $queryParams);

            if ($response->successful()) {
                $result = $response->json();
                Log::info('USPS Payment Account Inquiry', [
                    'account_number' => $accountNumber,
                    'account_type' => $accountType,
                    'sufficient_funds' => $result['sufficientFunds'] ?? false
                ]);
                return $result;
            }

            $this->handleErrorResponse($response, 'Payment account inquiry');
            
        }, 'Checking payment account');
    }

    /**
     * Check if account has sufficient funds with proper validation
     */
    public function hasSufficientFunds(string $accountNumber, string $accountType, float $amount): bool
    {
        try {
            $this->validateAmount($amount);
            
            $accountInfo = $this->getPaymentAccount($accountNumber, $accountType, $amount);
            return $accountInfo['sufficientFunds'] ?? false;
            
        } catch (\Exception $e) {
            Log::error('USPS Sufficient Funds Check Failed', [
                'account_number' => $accountNumber,
                'account_type' => $accountType,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create custom payment authorization with role validation
     */
    public function createCustomPaymentAuthorization(array $customRoles): array
    {
        $this->validateCustomRoles($customRoles);
        return $this->createPaymentAuthorization($customRoles);
    }

    /**
     * Get Default Roles Configuration (Production Ready)
     */
    protected function getDefaultRoles(): array
    {
        return [
            [
                'roleName' => 'PAYER',
                'CRID' => config('usps.account.payer_crid'),
                'accountType' => 'EPS',
                'accountNumber' => config('usps.account.account_number')
            ],
            [
                'roleName' => 'LABEL_OWNER',
                'CRID' => config('usps.account.label_owner_crid'),
                'MID' => config('usps.account.label_owner_mid'),
                'manifestMID' => config('usps.account.label_owner_mid')
            ]
        ];
    }

    /**
     * Get Return Label Roles Configuration
     */
    public function getReturnLabelRoles(): array
    {
        return [
            [
                'roleName' => 'PAYER',
                'CRID' => config('usps.account.payer_crid'),
                'accountType' => 'EPS',
                'accountNumber' => config('usps.account.account_number')
            ],
            [
                'roleName' => 'RETURN_LABEL_PAYER',
                'CRID' => config('usps.account.payer_crid'),
                'accountType' => 'EPS',
                'accountNumber' => config('usps.account.account_number')
            ],
            [
                'roleName' => 'LABEL_OWNER',
                'CRID' => config('usps.account.label_owner_crid'),
                'MID' => config('usps.account.label_owner_mid'),
                'manifestMID' => config('usps.account.label_owner_mid')
            ]
        ];
    }

    /**
     * Handle Error Responses with Detailed USPS API Errors
     */
    protected function handleErrorResponse($response, string $operation): void
    {
        $statusCode = $response->status();
        $errorBody = $response->body();
        
        $logContext = [
            'status' => $statusCode,
            'operation' => $operation,
            'environment' => config('usps.environment'),
            'response_preview' => substr($errorBody, 0, 1000)
        ];

        $errorMessage = "{$operation} failed with status: {$statusCode}";
        
        if ($errorBody) {
            try {
                $errorData = json_decode($errorBody, true);
                
                if (isset($errorData['error']['message'])) {
                    $errorMessage = $errorData['error']['message'];
                    
                    if (isset($errorData['error']['errors']) && is_array($errorData['error']['errors'])) {
                        $detailedErrors = [];
                        foreach ($errorData['error']['errors'] as $error) {
                            $detailedError = $error['title'] ?? 'Unknown error';
                            if (isset($error['detail'])) {
                                $detailedError .= ': ' . $error['detail'];
                            }
                            if (isset($error['source']['parameter'])) {
                                $detailedError .= ' (parameter: ' . $error['source']['parameter'] . ')';
                            }
                            $detailedErrors[] = $detailedError;
                        }
                        if (!empty($detailedErrors)) {
                            $errorMessage .= ' | Details: ' . implode('; ', $detailedErrors);
                        }
                    }
                } elseif (isset($errorData['message'])) {
                    $errorMessage = $errorData['message'];
                }
                
                $logContext['parsed_error'] = $errorMessage;
            } catch (\Exception $jsonException) {
                $logContext['json_parse_error'] = $jsonException->getMessage();
            }
        }

        Log::error("USPS Payments {$operation} Failed", $logContext);
        throw new \RuntimeException($errorMessage, $statusCode);
    }

    /**
     * Validation Methods
     */
    protected function validateRoles(array $roles): void
    {
        if (empty($roles)) {
            return;
        }

        $validator = Validator::make(['roles' => $roles], [
            'roles' => 'required|array|min:1',
            'roles.*.roleName' => 'required|string|in:PAYER,LABEL_OWNER,RATE_HOLDER,SHIPPER,MAIL_OWNER,PLATFORM,LABEL_PROVIDER,RETURN_LABEL_PAYER,RETURN_LABEL_RATE_HOLDER,RETURN_LABEL_OWNER',
            'roles.*.CRID' => 'required|string|min:2|max:18',
            'roles.*.accountType' => 'nullable|string|in:EPS,PERMIT,METER,OMAS',
            'roles.*.accountNumber' => 'nullable|string|max:50',
            'roles.*.MID' => 'nullable|string|size:6',
            'roles.*.manifestMID' => 'nullable|string|size:6',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid roles configuration: ' . $validator->errors()->first());
        }
    }

    protected function validateCustomRoles(array $customRoles): void
    {
        $this->validateRoles($customRoles);

        // Ensure at least PAYER and LABEL_OWNER roles are present
        $roleNames = array_column($customRoles, 'roleName');
        if (!in_array('PAYER', $roleNames) || !in_array('LABEL_OWNER', $roleNames)) {
            throw new \InvalidArgumentException('Custom roles must include both PAYER and LABEL_OWNER roles');
        }
    }

    protected function validateAccountParameters(string $accountNumber, string $accountType, ?float $amount): void
    {
        $validator = Validator::make([
            'account_number' => $accountNumber,
            'account_type' => $accountType,
            'amount' => $amount
        ], [
            'account_number' => 'required|string|max:50',
            'account_type' => 'required|string|in:EPS,PERMIT,METER,OMAS',
            'amount' => 'nullable|numeric|min:0.01|max:999999.99'
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Invalid account parameters: ' . $validator->errors()->first());
        }
    }

    protected function validateAmount(float $amount): void
    {
        if ($amount <= 0 || $amount > 999999.99) {
            throw new \InvalidArgumentException('Amount must be between 0.01 and 999,999.99');
        }
    }

    /**
     * Retry operation with exponential backoff
     */
    protected function retry(callable $operation, string $operationName = 'Operation')
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $lastException = $e;
                
                // Don't retry on client errors (4xx) except 429 (Too Many Requests)
                if ($e->getCode() >= 400 && $e->getCode() < 500 && $e->getCode() !== 429) {
                    throw $e;
                }
                
                if ($attempt < $this->maxRetries) {
                    $delay = pow(2, $attempt) * 100; // Exponential backoff: 200ms, 400ms, 800ms
                    Log::warning("USPS Payments API retry attempt {$attempt}/{$this->maxRetries} for {$operationName}", [
                        'error' => $e->getMessage(),
                        'next_attempt_in_ms' => $delay
                    ]);
                    usleep($delay * 1000);
                }
            }
        }
        
        Log::error("USPS Payments API {$operationName} failed after {$this->maxRetries} attempts", [
            'last_error' => $lastException->getMessage()
        ]);
        
        throw $lastException;
    }

    /**
     * Clear cached tokens
     */
    public function clearCachedToken(): void
    {
        $cacheKey = 'usps_payments_oauth_token_' . md5($this->clientId);
        Cache::forget($cacheKey);
        
        Log::info('USPS Payments tokens cleared');
    }

    /**
     * Test connection to USPS API
     */
    public function testConnection(): array
    {
        try {
            $this->validateConfiguration(config('usps.environment'));
            $token = $this->getAccessToken();
            
            // Test with a simple account inquiry if possible
            $accountTest = $this->getPaymentAccount(
                config('usps.account.account_number'),
                'EPS',
                1.00
            );
            
            return [
                'success' => true,
                'message' => 'USPS Payments API connection successful',
                'environment' => config('usps.environment'),
                'token_valid' => !empty($token),
                'account_active' => isset($accountTest['accountType'])
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'USPS Payments API connection failed: ' . $e->getMessage(),
                'environment' => config('usps.environment')
            ];
        }
    }

    /**
     * Get API configuration information
     */
    public function getConfigInfo(): array
    {
        return [
            'environment' => config('usps.environment'),
            'base_url' => $this->baseUrl,
            'oauth_url' => $this->oauthUrl,
            'timeout' => $this->timeout,
            'max_retries' => $this->maxRetries,
            'cache_ttl' => config('usps.cache.token_ttl'),
            'has_credentials' => !empty($this->clientId) && !empty($this->clientSecret),
            'account_configured' => !empty(config('usps.account.payer_crid'))
        ];
    }

    /**
     * Validate complete USPS configuration
     */
    public function validateFullConfiguration(): array
    {
        $issues = [];
        
        try {
            $this->validateConfiguration(config('usps.environment'));
        } catch (\RuntimeException $e) {
            $issues[] = $e->getMessage();
        }

        // Validate account configuration
        $requiredAccountFields = [
            'payer_crid' => config('usps.account.payer_crid'),
            'label_owner_crid' => config('usps.account.label_owner_crid'),
            'label_owner_mid' => config('usps.account.label_owner_mid'),
            'account_number' => config('usps.account.account_number')
        ];

        foreach ($requiredAccountFields as $field => $value) {
            if (empty($value)) {
                $issues[] = "Missing account configuration: {$field}";
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'environment' => config('usps.environment')
        ];
    }
}