<?php

namespace Webkul\Shipping\Contracts;

interface UspsInternationalInterface
{
    public function createInternationalLabel(array $labelData): array;
    public function reprintInternationalLabel(string $trackingNumber, array $imageInfo): array;
    public function cancelInternationalLabel(string $trackingNumber): array;
    public function testConnection(): array;
    public function validateInternationalLabelData(array $data): array;
    public function getSupportedCountries(): array;
    public function getRateInternational(array $rateData): array;
}