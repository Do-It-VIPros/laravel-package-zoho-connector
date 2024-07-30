<?php

namespace Agencedoit\ZohoConnector\Services;

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Traits\ZohoServiceChecker;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

use ZohoCreatorApi;

use \Datetime;
use \DateInterval;
use \Exception;

class ZohoCreatorService {

    use ZohoServiceChecker;

    private string $api_base_url;
    private string $bulk_base_url;

    public function __construct()
    {
        $this->api_base_url = config('zohoconnector.api_base_url') . "/api/v2.1/" . config('zohoconnector.user') . "/" . config('zohoconnector.app_name');
        $this->bulk_base_url = config('zohoconnector.bulk_base_url') . "/creator/v2.1/bulk/" . config('zohoconnector.user') . "/" . config('zohoconnector.app_name') . "/report/";
    }

    public function isReady() : bool {
        try {
            return Schema::hasTable(config('zohoconnector.tokens_table_name')) && $this->getToken() !== null;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return false;
        }
    }

    private function getToken() : string|null {
        try{
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
            //no valid token found
            return null;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return null;
        }
    }

    private function getHeaders() : array {
        try {
            return ['Authorization' => 'Zoho-oauthtoken ' . $this->getToken()];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

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

            $this->ZohoResponseCheck($response);

            $this->registerToken($response->json());

            return $this->isReady();
        } catch (Exception $e) {
            //? Log any exceptions that occur during token request
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return false;
        }
    }

    public function get(string $report, string|array $criteria = "") : array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "")) {
                //? Log error if request fails
                throw new Exception("Missing parameter", 503);
            }

            $criteria_as_string = (gettype($criteria) == "array" ? $this->criteriaFormater($criteria) : $criteria);
            $full_url = $this->api_base_url . "/report/" . $report;

            $parmeters = [];
            $parmeters['field_config'] = "all";
            if($criteria_as_string != null && $criteria_as_string != "") {
                $parmeters['criteria'] = $criteria_as_string;
            }

            $response = Http::withHeaders($this->getHeaders())->get(
                $full_url,
                $parmeters
            );
        
            $this->ZohoResponseCheck($response,"ZohoCreator.report.READ");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

    public function getByID(string $report, string $object_id) : array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "")) {
                //? Log error if request fails
                throw new Exception("Missing parameter", 503);
            }

            $full_url = $this->api_base_url . "/report/" . $report . "/" . $object_id;

            $response = Http::withHeaders($this->getHeaders())->get(
                $full_url,
                [
                    'field_config' => 'all',
                ]
            );
        
            $this->ZohoResponseCheck($response,"ZohoCreator.report.READ");

            return $response->json();
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

    public function createBulk(string $report, string|array $criteria = "") : string|array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "")) {
                //? Log error if request fails
                throw new Exception("Missing parameter", 503);
            }

            $full_url = $this->bulk_base_url  . $report . "/read";

            $criteria_as_string = (gettype($criteria) == "array" ? $this->criteriaFormater($criteria) : $criteria);

            $query_content = ["max_records" => 200000];
            if($criteria_as_string != null && $criteria_as_string != "") {
                $query_content['criteria'] = $criteria_as_string;
            }

            $json_body = ["query" => $query_content];

            $response = Http::withHeaders(array_merge($this->getHeaders(),['Content-type' => 'application/json']))->post(
                $full_url,
                $json_body
            );

            $this->ZohoResponseCheck($response,"ZohoCreator.bulk.CREATE");

            return $response->json()["details"]["id"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "";
        }
    }

    public function readBulk(string $report, string $id) : string|array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "") || ($id === null || $id === "")) {
                //? Log error if request fails
                throw new Exception("Missing parameter", 503);
            }

            $full_url = $this->bulk_base_url  . $report . "/read/" . $id;
            
            $response = Http::withHeaders($this->getHeaders())->get(
                $full_url,
            );

            $this->ZohoResponseCheck($response,"ZohoCreator.bulk.READ");

            return ($response->json());
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            abort(503, 'An error occured');
        }
    }

    public function bulkIsReady(string $report, string $id) : bool {
        try {
            $this->ZohoServiceCheck();
            
            $bulk_infos = $this->readBulk($report, $id);

            return ($bulk_infos != "" && $bulk_infos["details"]["status"] == "Completed");
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            abort(503, 'An error occured');
            return false;
        }
    }

    public function downloadBulk(string $report, string $id) : string|array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "") || ($id === null || $id === "")) {
                //? Log error if request fails
                throw new Exception("Missing parameter", 503);
            }

            $full_url = $this->bulk_base_url  . $report . "/read/" . $id . "/result";
            
            $stored_path = config('zohoconnector.bulk_download_path');

            File::makeDirectory($stored_path, 0755, true, true);

            $zip_location = $stored_path . "/bulk_job_" . $id . ".zip";

            $response = Http::withHeaders($this->getHeaders())->sink($zip_location)->get(
                $full_url,
            );

            $this->ZohoResponseCheck($response,"ZohoCreator.bulk.READ");

            return $zip_location;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            abort(503, 'An error occured');
        }
    }

    //WIP See if it's realy useful
    private function criteriaFormater(array $criteria) : string {
        try {
            $formated_criterias = "";
            foreach($criteria as $field=>$filters) {
                //Here is the tricky point
                $formated_criterias .= $field . $filters['comparaison'] . $filters['value'] . "&&";
            }
            if($formated_criterias != "") {
                $formated_criterias = substr_replace($formated_criterias, '', -2);
            }
            return $formated_criterias;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "";
        }
    }

    public function resetTokens() : void {
        try {
            ZohoConnectorToken::truncate();
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
        }
    }

    public function test() : string {
        $this->ZohoServiceCheck();
        return "blob";
    }
}