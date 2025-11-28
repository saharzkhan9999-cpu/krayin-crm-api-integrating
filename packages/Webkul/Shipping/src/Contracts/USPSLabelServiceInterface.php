<?php

namespace Webkul\Shipping\Contracts;

interface USPSLabelServiceInterface
{
    public function createLabel(array $labelData): array;
    public function createReturnLabel(array $labelData): array;
    public function cancelLabel(string $trackingNumber): array;
    public function editLabel(string $trackingNumber, array $patchData): array;
    public function reprintLabel(string $trackingNumber, array $imageInfo): array;
    public function createSimpleLabel(array $fromAddress, array $toAddress, float $weight, string $mailClass = 'USPS_GROUND_ADVANTAGE', array $options = []): array;
    public function testConnection(): array;
    public function clearCachedToken(): void;
    public function getAccessToken(): string;
}