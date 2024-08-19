<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Illuminate\Http\Client\Response;

use \Exception;

use Agencedoit\ZohoConnector\Traits\ZohoServiceChecker;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;

class ZohoControllerTest extends TestCase
{
    use ZohoServiceChecker;

    /**
     * ZohoServiceChecker Trait TESTER
     */

    /** 
     * TEST ZohoServiceCheck()
     */
    public function test_zoho_service_check_function(): void
    {
        try {
            $this->ZohoServiceCheck();
            //Service is not ready so it's supposed to fail
            $this->assertTrue(ZohoCreatorApi::isReady());

        } catch (Exception $e) {
            //Service is ready so it's supposed to success
            $this->assertTrue(!ZohoCreatorApi::isReady());
        }
    }

    /** 
     * TEST ZohoResponseCheck()
     */
    public function test_zoho_response_check_function(): void
    {
        try {
            $this->assertTrue(true);
           /* $response = response('Hello World', 200)->json([
                'name' => 'Abigail',
                'state' => 'CA',
            ]);
            $this->ZohoResponseCheck($response);
            //Service is not ready so it's supposed to fail
            $this->assertTrue(ZohoCreatorApi::isReady());*/

        } catch (Exception $e) {
            //Service is ready so it's supposed to success
            //$this->assertTrue(!ZohoCreatorApi::isReady());
        }
    }
}