<?php

namespace Webkul\Shipping\Carriers;

use Webkul\Checkout\Models\CartShippingRate;
use App\Services\UspsPricesService;

class Usps extends AbstractShipping
{
    /**
     * Shipping method code
     *
     * @var string
     */
    protected $code = 'usps';

    /**
     * USPS Prices Service
     *
     * @var UspsPricesService
     */
    protected $uspsService;

    public function __construct()
    {
        $this->uspsService = app(UspsPricesService::class);
    }

    /**
     * Calculate rate for USPS shipping
     */
    public function calculate()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        $cart = \Webkul\Checkout\Facades\Cart::getCart();
        
        if (!$cart || !$cart->shipping_address) {
            return false;
        }

        // Get origin from Krayin configuration or use default
        $originZip = core()->getConfigData('sales.shipping.origin.zipcode') ?: '10001';
        
        // Get destination from cart
        $destinationZip = $cart->shipping_address->postcode;
        
        if (empty($destinationZip) || strlen($destinationZip) < 5) {
            return false;
        }

        try {
            // Calculate cart weight and dimensions
            $weight = $this->getCartWeight($cart);
            $dimensions = $this->getCartDimensions($cart);

            // Get USPS rates
            $rateData = [
                'originZIPCode' => $originZip,
                'destinationZIPCode' => $destinationZip,
                'weight' => $weight,
                'length' => $dimensions['length'],
                'width' => $dimensions['width'],
                'height' => $dimensions['height'],
                'priceType' => 'COMMERCIAL',
                'processingCategory' => 'MACHINABLE',
                'rateIndicator' => 'SP',
                'destinationEntryFacilityType' => 'NONE',
                'mailingDate' => now()->format('Y-m-d')
            ];

            $result = $this->uspsService->searchBaseRatesList($rateData);

            if ($result['success'] && !empty($result['rates'])) {
                return $this->formatShippingRates($result['rates']);
            }

        } catch (\Exception $e) {
            \Log::error('USPS Shipping Calculation Error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Format USPS rates for Krayin
     */
    protected function formatShippingRates(array $uspsRates)
    {
        $formattedRates = [];

        foreach ($uspsRates as $rate) {
            $price = $rate['price'] ?? $rate['totalPrice'] ?? 0;
            
            if ($price <= 0) continue;

            $cartShippingRate = new CartShippingRate;
            
            $cartShippingRate->carrier = $this->code;
            $cartShippingRate->carrier_title = $this->getTitle();
            $cartShippingRate->method = $rate['mailClass'] ?? 'usps_standard';
            $cartShippingRate->method_title = $this->getRateTitle($rate);
            $cartShippingRate->price = $price;
            $cartShippingRate->base_price = $price;
            
            // Store additional rate information
            $cartShippingRate->additional = [
                'mail_class' => $rate['mailClass'] ?? '',
                'service_description' => $rate['description'] ?? '',
                'delivery_days' => $rate['deliveryDays'] ?? '',
                'rate_indicator' => $rate['rateIndicator'] ?? ''
            ];

            $formattedRates[] = $cartShippingRate;
        }

        return $formattedRates;
    }

    /**
     * Generate user-friendly rate title
     */
    protected function getRateTitle(array $rate): string
    {
        $mailClass = $rate['mailClass'] ?? '';
        
        $titles = [
            'USPS_GROUND_ADVANTAGE' => 'USPS Ground Advantage',
            'PRIORITY_MAIL' => 'Priority Mail',
            'PRIORITY_MAIL_EXPRESS' => 'Priority Mail Express',
            'FIRST_CLASS_MAIL' => 'First Class Mail',
            'MEDIA_MAIL' => 'Media Mail',
            'LIBRARY_MAIL' => 'Library Mail',
            'PARCEL_SELECT' => 'Parcel Select'
        ];

        return $titles[$mailClass] ?? ($rate['description'] ?? 'USPS Shipping');
    }

    /**
     * Calculate total cart weight
     */
    protected function getCartWeight($cart): float
    {
        $totalWeight = 0;

        foreach ($cart->items as $item) {
            $productWeight = $item->product->weight ?? 1.0;
            $totalWeight += $productWeight * $item->quantity;
        }

        return max($totalWeight, 0.1); // Minimum 0.1 lbs
    }

    /**
     * Calculate package dimensions
     */
    protected function getCartDimensions($cart): array
    {
        // Simple dimension calculation based on item count
        $itemCount = $cart->items->sum('quantity');
        
        if ($itemCount <= 2) {
            return ['length' => 10.0, 'width' => 8.0, 'height' => 4.0];
        } elseif ($itemCount <= 5) {
            return ['length' => 12.0, 'width' => 10.0, 'height' => 6.0];
        } else {
            return ['length' => 14.0, 'width' => 12.0, 'height' => 8.0];
        }
    }
}