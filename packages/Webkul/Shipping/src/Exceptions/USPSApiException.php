<?php

namespace Webkul\Shipping\Exceptions;

use Exception;

class USPSApiException extends Exception
{
    protected $context;
    public function __construct($message = "", $code = 0, $context = [], ?Exception $previous = null)
    {
parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext()
    {
        return $this->context;
    }

    public static function fromResponse($response, string $operation): self
    {
        $statusCode = $response->status();
        $errorBody = $response->body();
        
        $errorMessage = "USPS API Error [{$statusCode}] for {$operation}";
        $context = [
            'status' => $statusCode,
            'operation' => $operation,
            'response_body' => $errorBody
        ];

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
                
                $context['parsed_error'] = $errorMessage;
            } catch (\Exception $jsonException) {
                $context['json_parse_error'] = $jsonException->getMessage();
                $errorMessage = substr($errorBody, 0, 200);
            }
        }

        return new self($errorMessage, $statusCode, $context);
    }
}