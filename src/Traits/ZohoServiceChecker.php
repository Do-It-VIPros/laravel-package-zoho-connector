<?php

namespace Agencedoit\ZohoConnector\Traits;

use Illuminate\Support\Facades\Log;
use Agencedoit\ZohoConnector\Services\ZohoCreatorService;

trait ZohoServiceChecker
{
    protected function ZohoServiceCheck()
    {
        if (!(new ZohoCreatorService)->isReady()) {
            Log::error('ZohoCreatorService is not ready. Please init it.');
            abort(503, 'ZohoCreatorService is not ready. Please init it.');
        }
    }
}