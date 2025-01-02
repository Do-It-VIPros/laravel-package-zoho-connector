<?php

namespace Agencedoit\ZohoConnector\Helpers;

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

use \Datetime;
use \DateInterval;
use \Exception;

class ZohoTokenManagement {

    protected string $data_base_url; // => https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>
    protected string $bulk_base_url; // => https://<base_url>/creator/v2.1/bulk/<account_owner_name>/<app_link_name>/report/
    protected string $custom_base_url; // => https://<base_url>/creator/custom/<account_owner_name>/;
    protected string $meta_base_url; // => https://<base_url>/creator/v2.1/meta/<account_owner_name>/<app_link_name>/;
    

    /**
     * 🌐🔐 isReady()
     *
     *  Check if the Service is ready to be used
     *
     * 🚀 Check if the required token table is here and if a token is available
     * 📝 Context: -
     *
     * @return bool status of the service
     *
     * @throws \Exception If an error occurs
     */
    public function isReady() : bool {
        try {
            return Schema::hasTable(config('zohoconnector.tokens_table_name')) && $this->getToken() !== null;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 🌐🔐 getToken()
     *
     *  Get an available token
     *
     * 🚀 Get the current token or generate an new one
     * 📝 Context: private function cause it's a private data
     *
     * @return string the valid token
     * @return null if there is an error
     *
     * @throws \Exception If an error occurs during the token generation process, it logs the error.
     */
    private function getToken() : string|null {
        try{
            //get a valid token
            $token_line = ZohoConnectorToken::where('token_created_at', '<=', Carbon::now())
                                            ->where('token_peremption_at', '>', Carbon::now())
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
            //no valid token found
            return null;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 🌐🔐 registerToken()
     *
     *  Save the token
     *
     * 🚀 Save the current token into the database table
     * 📝 Context: private function cause it's have to be called by the service only 
     *
     * @throws \Exception If an error occurs during the token save process, it logs the error.
     */
    private function registerToken($token_datas, $refresh_token=null) : void {
        try{
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
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
        }
    }

    /**
     * 🌐🔐 refreshToken()
     *
     *  Refresh the access token
     *
     * 🚀 Refresh the access token with an available refresh token
     * 📝 Context: private function cause it's have to be called by the service only 
     *
     * @return string the new generated token
     * @return null if there is an error
     *
     * @throws \Exception If an error occurs during the token generation process, it logs the error.
     */
    private function refreshToken(string $refresh_token) : string|null {
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

            if(!$this->isReady()){
                //? Log error if process has failed
                throw new Exception("Process failed. ZohoCreatorService is not ready", 404);
            }
            return $this->getToken();
        } catch (Exception $e) {
            //? Log any exceptions that occur during token request
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 🌐🔐 generateFirstToken()
     *
     *  Generate the first access token from code
     *
     * 🚀 Once you get a code with the request_code, this function allow to request the first access token 
     * 📝 Context: public cause it has to be called only from the redirect request_code route
     *
     * @return bool return isReady()
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function generateFirstToken(string $code) : bool {
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

            $this->registerToken($response->json());

            return $this->isReady();
        } catch (Exception $e) {
            //? Log any exceptions that occur during token request
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 🌐🔐 getHeaders()
     *
     *  return the right header token to make request
     *
     * 🚀 Get the token and put it into an array to set the headers of all basics request to Zoho API
     * 📝 Context: protected cause it has to be called only from an Zoho Service
     *
     * @return array the zoho header
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    protected function getHeaders() : array {
        try {
            return [
                'Authorization' => 'Zoho-oauthtoken ' . $this->getToken(),
                'environment' => config('zohoconnector.environment')
            ];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 🌐🔐 resetTokens()
     *
     *  Reset the token with a truncate of the tokens table
     *
     * 🚀 Reset the token so then the Service
     * 📝 Context: -
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function resetTokens() : void {
        try {
            ZohoConnectorToken::truncate();
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
        }
    }
}