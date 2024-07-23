<?php

namespace Agencedoit\ZohoConnector\Services;

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Traits\ZohoServiceChecker;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use \Datetime;
use \DateInterval;
use \Exception;

class ZohoCreatorService {

    use ZohoServiceChecker;

    private string $api_base_url;

    public function __construct()
    {
        $this->api_base_url = config('zohoconnector.api_base_url') . "/api/v2.1/" . config('zohoconnector.user') . "/" . config('zohoconnector.app_name');
    }

    public function isReady() : bool {
        return Schema::hasTable(config('zohoconnector.tokens_table_name')) && $this->getToken() !== null;
    }

    private function getToken() : string|null {
        //get a valid token
        $token_line = ZohoConnectorToken::where('token_created_at', '<=', new DateTime('NOW'))
                                        ->where('token_peremption_at', '>', new DateTime('NOW'))
                                        ->first();
        if($token_line != null) {
            //Token already generated and available
            return $token_line->token;
        }
        else if(sizeof(ZohoConnectorToken::all()) != 0) {
            //No token valid but generated once
            //Try to refresh the token 
            $last_token = ZohoConnectorToken::orderBy('created_at', 'desc')->first();

            return $this->refreshToken($last_token->refresh_token);
        }
        return null;
    }

    private function registerToken($token_datas, $refresh_token=null) : void {
        $now = new DateTime('NOW');
        $end_time = new DateTime('NOW');
        $end_time->add(DateInterval::createFromDateString($token_datas['expires_in'] . ' second'));
    
        ZohoConnectorToken::create([
            'token' => $token_datas['access_token'],
            'refresh_token' => ($refresh_token != null ? $refresh_token : $token_datas['refresh_token']),
            'token_created_at' => $now,
            'token_peremption_at' => $end_time,
            'token_duration' => $token_datas['expires_in'],
        ]);
    }

    private function refreshToken($refresh_token) : string|null {
        try {
            if($refresh_token === null) {
                throw new Exception("No refresh token found", 404);    
            }

            //? Send request to Zoho OAuth2 token endpoint
            $response = Http::asForm()->post(
                config('zohoconnector.base_account_url') . '/oauth/v2/token',
                [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('zohoconnector.client_id'),
                    'client_secret' => config('zohoconnector.client_secret'),
                    'refresh_token' => $refresh_token,
                    'redirect_uri' => env("APP_URL") . "/zoho/request-code-response",
                ]
            );

            //? Handle successful response
            if (!$response->successful()) {
                //? Log error if request fails
                throw new Exception($response->status(), 404);
            }
            
            $this->registerToken($response->json(),$refresh_token);

            if(!(new ZohoCreatorService)->isReady()){
                //? Log error if process has failed
                throw new Exception("Process failed. ZohoCreatorService is not ready", 404);
            }
            return $this->getToken();
        } catch (Exception $e) {
            //? Log any exceptions that occur during token request
            Log::error('Error on token request (refreshToken function) ' . $e->getMessage());
            return null;
        }
    }

    public function generateFirstToken($code) : bool {
        try {
            //? Send request to Zoho OAuth2 token endpoint
            $response = Http::asForm()->post(
                config('zohoconnector.base_account_url') . '/oauth/v2/token',
                [
                    'grant_type' => 'authorization_code',
                    'client_id' => config('zohoconnector.client_id'),
                    'client_secret' => config('zohoconnector.client_secret'),
                    'redirect_uri' => env("APP_URL") . "/zoho/request-code-response",
                    'code' => $code,
                    'prompt' => 'consent',
                ]
            );

            //? Handle successful response
            if (!$response->successful()) {
                //? Log error if request fails
                throw new Exception($response->status(), 404);
            }

            $this->registerToken($response->json());

            return $this->isReady();
        } catch (Exception $e) {
            //? Log any exceptions that occur during token request
            Log::error('Error on token request (generateFirstToken function) ' . $e->getMessage());
            return false;
        }
    }

    public function get() : array|null {

        try {
            $this->ZohoServiceCheck();

            $report = "API_Marques";

            $full_url = $this->api_base_url . '/report/' . $report;

            //return ["url" => $full_url];

            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $this->getToken(),
            ])->get($full_url,
                ['criteria' => 'active==true&&type=="VIPros"&&platform.test==false&&is_test==false'
            ]);
            if(!$response->successful()){
                throw new Exception($response->status(), 404);
            }
            return $response->json();
        } catch (Exception $e) {
            Log::error('Error on ZohoCreatorService::get => ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    public function test() : string {
        $this->ZohoServiceCheck();
        return "blob";

    }
}