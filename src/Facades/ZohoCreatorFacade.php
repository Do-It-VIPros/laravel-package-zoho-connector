<?php

namespace Agencedoit\ZohoConnector\Facades;

use Illuminate\Support\Facades\Facade;

class ZohoCreatorFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'Agencedoit\ZohoConnector\Services\ZohoCreatorService';
    }
}