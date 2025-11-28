<?php

namespace Webkul\Shipping\Facades;

use Illuminate\Support\Facades\Facade;

class USPSLabels extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'usps.labels';
    }
}