<?php

namespace Webkul\Shipping\Exceptions;

use Exception;

class USPSValidationException extends Exception
{
    protected $errors;

    public function __construct(string $message = "", array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return [
            'error' => true,
            'message' => $this->getMessage(),
            'errors' => $this->errors,
        ];
    }
}