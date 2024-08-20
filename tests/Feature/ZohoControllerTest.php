<?php

namespace Tests\Feature;

use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;

use Mockery;
use Tests\TestCase;

class ZohoControllerTest extends TestCase 
{


    /************************
     ******* ROUTES ********
     ************************/
    /**
     * TEST of /zoho/request-code route
     */
    public function test_request_code_route(): void
    {
        //CAS 1 : No token
        $apiMock = Mockery::mock('alias:ZohoCreatorApi')->makePartial();
        $apiMock->shouldReceive('isReady')
                ->andReturnTrue();
        $this->assertTrue(ZohoCreatorApi::isReady());        
        $response = $this->get('/zoho/request-code');
        $response->assertStatus(302);
        $response->assertStatus(404);
        //ZohoCreatorApi::shouldReceive('isReady')->andReturnTrue();
        //$response = $this->get('/zoho/request-code');
        //CAS 2 : got a token
    }

    /**
     * TEST of /zoho/request-code-response route
     
    public function test_request_code_response_route(): void
    {
        $response = $this->get('/zoho/request-code');
        if(!ZohoCreatorApi::isReady()) {
            $response->assertStatus(302);
        }
        else {
            $response->assertStatus(404);
        }
    }*/

    /**
     * TEST of /zoho/test route
     
    public function test_test_page_route(): void
    {
        $response = $this->get('/zoho/test');
        if(config('app.env') != 'production') {
            $response->assertStatus(200);
        }
        else {
            $response->assertStatus(404);
        }
    }*/
    
    /**
     * TEST of /zoho/reset-tokens route
     
    public function test_reset_token_route(): void
    {
        $response = $this->get('/zoho/reset-tokens');
        if(config('app.env') != 'production') {
            $response->assertStatus(302);
        }
        else {
            $response->assertStatus(404);
        }
    }*/

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
