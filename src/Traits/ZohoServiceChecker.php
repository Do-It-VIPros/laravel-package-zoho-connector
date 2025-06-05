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
            throw new Exception('ZohoCreatorService is not ready. Please init it.');
        }
        else if (config('zohoconnector.environment') != ""
                    && config('zohoconnector.environment') != "development"
                    && config('zohoconnector.environment') != "stage"
                    && config('zohoconnector.environment') != "production") {
            Log::error('zohoconnector.environment is not set correctly. (' . config('zohoconnector.environment') . '). Choices are : empty,development, stage or production.');
            throw new Exception('ZohoCreatorService is not ready. zohoconnector.environment is not correct.');
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
                    throw new Exception(implode($response->json()));
                }
            }
            else {
                if($response->json()["code"] != 3000){
                    throw new Exception(implode($response->json()));
                }
            }
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            Log::error('Received return ' . implode($response->json()));
            throw new Exception($e->getMessage());
        }
    }
}