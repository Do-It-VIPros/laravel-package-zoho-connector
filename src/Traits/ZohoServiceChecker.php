<?php

namespace Agencedoit\ZohoConnector\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

use ZohoCreatorApi;

use \Exception;

trait ZohoServiceChecker
{
    protected function ZohoServiceCheck() : void
    {
        if (!ZohoCreatorApi::isReady()) {
            Log::error('ZohoCreatorService is not ready. Please init it.');
            Log::error('See ' . env("APP_URL") . '/zoho/test for more informations.');
            abort(503, 'ZohoCreatorService is not ready. Please init it.');
        }
    }

    protected function ZohoResponseCheck(Response $response, string $specific="") : void
    {
        try {
            //request error
            if(!$response->successful()){
                //Zoho error
                if($response->json()["code"] == 2945){
                    //scope error
                    throw new Exception("Please add " . $specific . " in ZOHO_SCOPE env variable.");
                }
                else if($response->json()["code"] != 3000){
                    throw new Exception(implode ($response->json()));
                }
            }
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            throw new Exception($e->getMessage());
        }
    }
}