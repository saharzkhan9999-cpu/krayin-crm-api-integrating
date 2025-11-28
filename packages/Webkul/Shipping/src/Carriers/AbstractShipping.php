<?php

namespace Webkul\Shipping\Carriers;

use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\CartShippingRate;

abstract class AbstractShipping
{
    /**
     * Shipping method code
     *
     * @var string
     */
    protected $code;
    
    /**
     * @var array
     */
    protected $config = [];

    /**
     * Returns rate for shipping method
     *
     * @return CartShippingRate|false
     */
    abstract public function calculate();

    /**
     * Get shipping method code
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get shipping method title
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigData('title');
    }

    /**
     * Get shipping method description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getConfigData('description');
    }

    /**
     * Get shipping method configuration
     *
     * @param string $field
     * @return mixed
     */
    public function getConfigData($field)
    {
        return core()->getConfigData('sales.carriers.' . $this->getCode() . '.' . $field);
    }

    /**
     * Check if shipping method is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->getConfigData('active');
    }
}