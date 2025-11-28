<?php

namespace Webkul\Shipping\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array validateAddress(array $addressData)
 * @method static array getCityStateByZip(string $zipCode)
 * @method static array getZipCodeByAddress(array $addressData)
 * @method static array quickValidate(array $addressData)
 * @method static array standardizeAddress(array $addressData)
 * @method static bool testConnection()
 *
 * @see \Webkul\Shipping\Services\USPSAddressesService
 */
class USPSAddresses extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'usps_addresses';
    }
}