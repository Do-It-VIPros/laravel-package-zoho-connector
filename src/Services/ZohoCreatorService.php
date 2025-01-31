<?php

namespace Agencedoit\ZohoConnector\Services;

use Agencedoit\ZohoConnector\Traits\ZohoServiceChecker;
use Agencedoit\ZohoConnector\Helpers\ZohoTokenManagement;
use Agencedoit\ZohoConnector\Models\ZohoBulkHistory;
use Agencedoit\ZohoConnector\Jobs\ZohoCreatorBulkProcess;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

use \Exception;
use ZipArchive;

class ZohoCreatorService extends ZohoTokenManagement {

    use ZohoServiceChecker;

    public function __construct()
    {
        $this->data_base_url = config('zohoconnector.api_base_url') . "/creator/v2.1/data/" . config('zohoconnector.user') . "/" . config('zohoconnector.app_name');
        $this->bulk_base_url = config('zohoconnector.api_base_url') . "/creator/v2.1/bulk/" . config('zohoconnector.user') . "/" . config('zohoconnector.app_name') . "/report/";
        $this->custom_base_url = config('zohoconnector.api_base_url') . "/creator/custom/" . config('zohoconnector.user') . "/";
        $this->meta_base_url = config('zohoconnector.api_base_url') . "/creator/v2.1/meta/" . config('zohoconnector.user') . "/" . config('zohoconnector.app_name') . "/";
    }

    /**
     * 🌐🔐 get()
     *
     *  Return at the maximum 1000 records from the given report and the given criteria.
     *  If the report can return more than 1000 records and a $cursor is given, it will be filled.
     *  The return value is an array from JSON.
     *
     * 🚀 Basic get from a report
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report     required    name of the report where to get informations
     * @param string|array  $criteria   optional    criteria as indicated in https://www.zoho.com/creator/help/api/v2.1/get-records.html#search_criteria
     * @param string        $cursor     optional    /!\by reference. Will be filed with a cursor if exists (more than 1000 results)
     *
     * @return array datas as an json array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function get(string $report, string|array $criteria = "", string &$cursor = "") : array|string {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "")) {
                //? Log error if request fails
                throw new Exception("Missing required report parameter", 503);
            }

            //URL
            $full_url = $this->data_base_url . "/report/" . $report;

            //PARAMETERS
            $parmeters = [];
            $parmeters['max_records'] = 1000;
            $parmeters['field_config'] = "all";
            $criteria_as_string = (gettype($criteria) == "array" ? $this->criteriaFormater($criteria) : $criteria);
            if($criteria_as_string != null && $criteria_as_string != "") {
                $parmeters['criteria'] = $criteria_as_string;
            }

            //HEADERS
            $headers = $this->getHeaders();
            if($cursor != "") {$headers["record_cursor"] = $cursor;}

            //REQUEST
            $response = Http::withHeaders($headers)->get(
                $full_url,
                $parmeters
            );

            //CHECK RESPONSE
            $this->ZohoResponseCheck($response,"ZohoCreator.report.READ");
            
            // set the cursor if exist or reset
            $cursor = (array_key_exists("record_cursor",$response->headers())? $response->headers()["record_cursor"][0] : "");

            return $response->json()["data"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 🌐🔐 getAll()
     *
     *  Return the maximum of records with the get function used recursively. 
     *  The return value is an array from JSON.
     *  /!\ The function lanch multiple get so it can be too long for the server timeout
     *
     * 🚀 Basic get from a report
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report     required    name of the report where to get informations
     * @param string|array  $criteria   optional    criteria as indicated in https://www.zoho.com/creator/help/api/v2.1/get-records.html#search_criteria
     * @param int           $delay      optional    delay in seconds (default 2 to avoid the limitation of 50 calls by seconds)
     *
     * @return array datas as an json array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function getAll(string $report, string|array $criteria = "", int $delay = 2) : array {
        try {
            $this->ZohoServiceCheck();
           
            $cursor = "";

            $found_datas = $this->get($report, $criteria, $cursor);
            while($cursor != "") {
                sleep($delay);
                $found_datas = array_merge($found_datas,$this->get($report, $criteria, $cursor));
            }

            return $found_datas;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 🌐🔐 getByID()
     *
     *  Return the object by id from a report
     *
     * 🚀 Basic get by id from a report
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report      required    name of the report where to get informations
     * @param string        $object_id   required    id of the desired object
     *
     * @return array datas as an json array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function getByID(string $report, string $object_id) : array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "")) {
                //? Log error if request fails
                throw new Exception("Missing required report parameter", 503);
            }

            $full_url = $this->data_base_url . "/report/" . $report . "/" . $object_id;

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

    /**
     * 🌐🔐 create()
     *
     *  Add a record into the given form
     *
     * 🚀 Add a record into the given form
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $form                required    name of the form to fill
     * @param array         $attributes          required    fields as array
     * @param array         $additional_fields   optional    fields to return with the ID
     *
     * @return array   datas added. By default the ID only, $additional_field can be returned too
     *                  In cas of multiple add, return each data return as array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function create(string $form, array $attributes, array $additional_fields = []) : array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($form === null || $form === "")) {
                //? Log error if request fails
                throw new Exception("Missing required form parameter", 503);
            }

            //URL
            $full_url = $this->data_base_url . "/form/" . $form;

            $json_body = ["data" => $attributes];
            $json_body["result"] = ["fields" => $additional_fields];

            /*$log = [
                'full_url' => $full_url,
                'json_body' => $json_body,
                'headers' => $this->getHeaders(),
            ];
            return $log;*/
            //REQUEST
            $response = Http::withHeaders(array_merge($this->getHeaders(),['Content-type' => 'application/json']))->post(
                $full_url,
                $json_body
            );

            //CHECK RESPONSE
            $this->ZohoResponseCheck($response,"ZohoCreator.form.CREATE");
            
            //RETURN
            //return $response->json();
            //return multiple
            if(isset($response->json()["result"])) {
                $return_response = [];
                foreach($response->json()["result"] as $result) {
                    $return_response[] = $result["data"];
                }
                return $return_response;
            }
            return $response->json()["data"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return array();
        }
    }

    /**
     * 🌐🔐 update()
     *
     *  Update a record into the given report
     *
     * 🚀 Update a record into the given report
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report                required    name of the report to update
     * @param int           $id                    required    id of the entity to update
     * @param array         $attributes            required    fields as array
     * @param array         $additional_fields     optional    fields to return with the ID
     *
     * @return array   datas added. By default the ID only, $additional_field can be returned too
     *                  In cas of multiple add, return each data return as array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function update(string $report, int|string $id, array $attributes, array $additional_fields = []) : array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "")) {
                //? Log error if request fails
                throw new Exception("Missing required report parameter", 503);
            }

            //URL
            $full_url = $this->data_base_url . "/report/" . $report . "/" . $id;

            $json_body = ["data" => $attributes];
            $json_body["result"] = ["fields" => $additional_fields];

            //REQUEST
            $response = Http::withHeaders(array_merge($this->getHeaders(),['Content-type' => 'application/json']))->patch(
                $full_url,
                $json_body
            );
            
            //CHECK RESPONSE
            $this->ZohoResponseCheck($response,"ZohoCreator.report.UPDATE");
            
            //RETURN
            //return $response->json();
            //return multiple
            if(isset($response->json()["result"])) {
                $return_response = [];
                foreach($response->json()["result"] as $result) {
                    $return_response[] = $result["data"];
                }
                return $return_response;
            }
            return $response->json()["data"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return ["KO"];
        }
    }

    /**
     * 🌐🔐 upload()
     *
     *  upload a file into a record into the given report
     *
     * 🚀 upload a file into a record into the given report
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report                required    name of the report where to upload
     * @param int           $id                    required    id of the entity where to upload
     * @param string        $field                 required    name of the field where to upload
     * @param string        $file                  required    url/path of the file to upload
     *
     * @return string       filename of the uploaded file
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function upload(string $report, int|string $id, string $field, string $file) : string|array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "")
                || ($id === null || $id === "")
                || ($field === null || $field === "")
                || ($file === null || $file === "")) {
                //? Log error if request fails
                throw new Exception("Missing required parameter", 503);
            }

            //URL
            $full_url = $this->data_base_url . "/report/" . $report . "/" . $id . "/" . $field . "/upload";
            //GENERATION OF A LOCAL FILE TMP IF FROM URL
            //TODO rendre le path du fichier paramétrable :)
            /*if(filter_var($file, FILTER_VALIDATE_URL)){
                $tmp_file = basename(parse_url($file, PHP_URL_PATH));
                file_put_contents($tmp_file, file_get_contents($file));
                $file = public_path($tmp_file);
            }*/
            //return $file;
            //REQUEST
            $response = Http::withHeaders($this->getHeaders())->attach(
                'file', fopen($file, 'r'), basename($file)
            )->post(
                $full_url
            );
            //DELETE THE TMP FILE
            if(isset($tmp_file)){unlink($tmp_file);}
            //CHECK RESPONSE
            $this->ZohoResponseCheck($response,"ZohoCreator.report.CREATE");
            return $response;
            //RETURN
            return $response->json()["filepath"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "KO";
        }
    }

    /**
     * 🌐🔐 customFunctionGet()
     *
     * call a zoho creator custom with GET method
     * The endpoint is protected
     *  - or by auth (default process and need a token with Zohocreator.customapi.EXECUTE authorisation)
     *  - or by publickey 
     *
     * 🚀 execute a zoho creator custom with GET method 
     * 📝 Context: ZohoCreatorService need to be ready (only if endpoint is auth protected)
     * 
     * @param string        $url                   required    refer to the link name in the basic details part when the custom endpoint is created
     * @param string        $parameters            optional    Additionnals URL parameters
     * @param string        $public_key            optional    the public key if the endpoint is not protected by a auth
     *
     * @return array   the response of the custom function
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function customFunctionGet(string $url, array $parameters = [], string $public_key = "") {
        try {
            if($public_key == "") {$this->ZohoServiceCheck();}

            //required variables check
            if (($url === null || $url === "")) {
                //? Log error if request fails
                throw new Exception("Missing required url parameter", 503);
            }

            //URL
            $full_url = $this->custom_base_url . $url;

            //PARAMETERS
            $headers = array();
            ($public_key != "")? $parameters["publickey"] = $public_key : $headers = $this->getHeaders();

            //REQUEST
            $response = Http::withHeaders($headers)->get(
                $full_url,
                $parameters
            );

            //CHECK RESPONSE
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $response);
            $this->ZohoResponseCheck($response, "Zohocreator.customapi.EXECUTE");
            return $response;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "KO";
        }
    }

    /**
     * 🌐🔐 customFunctionPost()
     *
     * call a zoho creator custom with POST method
     * The endpoint is protected
     *  - or by auth (default process and need a token with Zohocreator.customapi.EXECUTE authorisation)
     *  - or by publickey 
     *
     * 🚀 execute a zoho creator custom with POST method 
     * 📝 Context: ZohoCreatorService need to be ready (only if endpoint is auth protected)
     * 
     * @param string        $url                   required    refer to the link name in the basic details part when the custom endpoint is created
     * @param array         $body                  optional    the json body as array
     * @param string        $public_key            optional    the public key if the endpoint is not protected by a auth
     *
     * @return array   the response of the custom function
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function customFunctionPost(string $url, array $body = [], string $public_key = "") {
        try {
            $this->ZohoServiceCheck();

            //required variables check
            if (($url === null || $url === "")) {
                //? Log error if request fails
                throw new Exception("Missing required url parameter", 503);
            }

            //URL
            $full_url = $this->custom_base_url . $url . (($public_key != "") ? ("?publickey=" . $public_key) : "" );

            //REQUEST
            $response = Http::withHeaders(array_merge($this->getHeaders(),['Content-type' => 'application/json']))->post(
                $full_url,
                $body
            );

            //CHECK RESPONSE
            $this->ZohoResponseCheck($response, "Zohocreator.customapi.EXECUTE");
            return $response;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "KO";
        }
    }

    ///////// BULK FUNCTIONS //////////

    
    /**
     * 🌐🔐 createBulk()
     *
     *  Create a bulk read request
     *
     * 🚀 Launch a bulk read request and return the id of the generated bulk
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report      required    name of the report where to get informations
     * @param string        $criteria    optional    criteria as indicated in https://www.zoho.com/creator/help/api/v2.1/get-records.html#search_criteria
     *
     * @return string id of the created bulk as string
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function createBulk(string $report, array|string $criteria = "") : string|array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "")) {
                //? Log error if request fails
                throw new Exception("Missing required report parameter", 503);
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

    /**
     * 🌐🔐 readBulk()
     *
     *  Return the informations datas from a created bulk 
     *
     * 🚀 Read a bulk infos
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report      required    name of the report where to get informations
     * @param string        $id          required    ID of the created bulk
     *
     * @return array bulk infos datas
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function readBulk(string $report, string $id) : string|array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "") || ($id === null || $id === "")) {
                //? Log error if request fails
                throw new Exception("Missing required report parameter", 503);
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

    /**
     * 🌐🔐 bulkIsReady()
     *
     *  Return if a bulk is ready to be download
     *
     * 🚀 Read a bulk infos and then return the ready status
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report      required    name of the report where to get informations
     * @param string        $id          required    ID of the created bulk
     *
     * @return bool the bulk ready status
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
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

    /**
     * 🌐🔐 downloadBulk()
     *
     *  Create the zohoconnector.bulk_download_path if not exists
     *  Download the bulk result as an .zip in the bulk_download_path
     *  Return the ZIP location
     *
     * 🚀 Download the bulk result and return the location
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report      required    name of the report where to get informations
     * @param string        $id          required    ID of the created bulk
     *
     * @return string the ZIP location
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function downloadBulk(string $report, string $id) : string|array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "") || ($id === null || $id === "")) {
                //? Log error if request fails
                throw new Exception("Missing required report parameter", 503);
            }

            $full_url = $this->bulk_base_url  . $report . "/read/" . $id . "/result";
            
            $stored_path = config('zohoconnector.bulk_download_path');

            File::makeDirectory($stored_path, 0755, true, true);

            $zip_location = $stored_path . "/bulk_job_" . $id . ".zip";

            $response = Http::withHeaders($this->getHeaders())->sink($zip_location)->get(
                $full_url,
            );

            //$this->ZohoResponseCheck($response,"ZohoCreator.bulk.READ");

            return $zip_location;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            abort(503, 'An error occured');
        }
    }

    /**
     * 🌐🔐 createBulkAuto()
     *
     *  Create a bulk read request but with an automatic service
     *  This one will render the result on a call back request
     *
     * 🚀 Launch a bulk read request and return the id of the generated bulk
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report         required    name of the report where to get informations
     * @param string        $call_back_url  required    url to send the result of the operation
     * @param string        $criteria       optional    criteria as indicated in https://www.zoho.com/creator/help/api/v2.1/get-records.html#search_criteria
     *
     * @return string   id of the created bulk as string
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function createBulkAuto(string $report, string $call_back_url, string|array $criteria = "") : string|int {
        try {
            $bulk_id = $this->createBulk($report, $criteria);
            $bulk_history = ZohoBulkHistory::create([
                'bulk_id' => $bulk_id,
                'report' => $report,
                'criterias' => $criteria,
                'step' => "created",
                'call_back_url' => $call_back_url,
                'last_launch' => now()
            ]);
            return $bulk_history->id;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "";
        }
    }

    /**
     * 🌐🔐 readBulkAuto()
     *
     *  Read the bulk info with a bulk_history_id but with an automatic service
     *
     * 🚀 Launch a bulk read request and return the id of the generated bulk
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string    $bulk_history_id     required    ID of the bulk_history to read
     *
     * @return array   the result of readBulk
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function readBulkAuto(int $bulk_history_id) : string|array {
        try {
            $bulk_history = ZohoBulkHistory::find($bulk_history_id);
            $bulk_infos = $this->readBulk($bulk_history->report, $bulk_history->bulk_id);
            return $bulk_infos;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "";
        }
    }

    /**
     * 🌐🔐 bulkIsReadyAuto()
     *
     *  Return if a bulk is ready to be download but with a bulk_history_id
     *
     * 🚀 Read a bulk infos and then return the ready status
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string    $bulk_history_id     required    ID of the bulk_history to read
     *
     * @return bool the bulk ready status
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function bulkIsReadyAuto(int $bulk_history_id) : bool {
        try {
            $bulk_infos = $this->readBulkAuto($bulk_history_id);
            return ($bulk_infos != "" && $bulk_infos["details"]["status"] == "Completed");
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            abort(503, 'An error occured');
            return false;
        }
    }

    /**
     * 🌐🔐 downloadBulkAuto()
     *
     *  Launch the downloadBulk function from a bulk_hitory_infos
     *
     * 🚀 Download the bulk result and return the location
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string    $bulk_history_id     required    ID of the bulk_history to download
     *
     * @return string the ZIP location
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function downloadBulkAuto(int $bulk_history_id) : string|array {
        try {
            $bulk_history = ZohoBulkHistory::find($bulk_history_id);
            $bulk_download_path = $this->downloadBulk($bulk_history->report, $bulk_history->bulk_id);
            return $bulk_download_path;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            abort(503, 'An error occured');
        }
    }

    /**
     * 🌐🔐 getWithBulk()
     *
     *  Create a bulk read request but with an automatic service
     *  This one will render the result on a call back request
     *
     * 🚀 Launch a bulk read request and return the id of the generated bulk
     * 📝 Context: ZohoCreatorService need to be ready
     * 
     * @param string        $report         required    name of the report where to get informations
     * @param string        $call_back_url  required    url to send the result of the operation
     * @param string        $criteria       optional    criteria as indicated in https://www.zoho.com/creator/help/api/v2.1/get-records.html#search_criteria
     *
     * @return string   id of the created bulk as string
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function getWithBulk(string $report, string $call_back_url, string|array $criteria = "") : string|array {
        try {
            $this->ZohoServiceCheck();
            //required variables check
            if (($report === null || $report === "" || $call_back_url === null || $call_back_url === "")) {
                //? Log error if request fails
                throw new Exception("Missing required report parameter", 503);
            }
            ZohoCreatorBulkProcess::dispatch($report, $call_back_url, $criteria)->onQueue(config('zohoconnector.bulk_queue'));
            return "OK";
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "KO";
        }
    }

    ///////// META INFORMATIONS /////////

    /**
     * 🌐🔐 getFormsMeta()
     *
     *  Return the meta information of all the forms present in a Zoho Creator application.
     *
     * 🚀 Return the meta information of all the forms present in a Zoho Creator application.
     * 📝 Context: ZohoCreatorService need to be ready
     *
     * @return array forms meta as an json array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function getFormsMeta() : array|string {
        try {
            $this->ZohoServiceCheck();

            //URL
            $full_url = $this->meta_base_url . "forms";

            //REQUEST
            $response = Http::withHeaders($this->getHeaders())->get(
                $full_url
            );

            //CHECK RESPONSE
            $this->ZohoResponseCheck($response,"ZohoCreator.meta.application.READ");

            return $response->json()["forms"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 🌐🔐 getFieldsMeta()
     *
     *  Return the meta information of all the fields present in a form of a Zoho Creator application.
     *
     * 🚀 Return the meta information of all the fields present in a form of a Zoho Creator application.
     * 📝 Context: ZohoCreatorService need to be ready
     *
     * @param string        $form         required    name of the form to gather fields
     * 
     * @return array fields meta informations as an json array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function getFieldsMeta(string $form) : array|string {
        try {
            $this->ZohoServiceCheck();

            //URL
            $full_url = $this->meta_base_url . "form/" . $form . "/fields";

            //REQUEST
            $response = Http::withHeaders($this->getHeaders())->get(
                $full_url
            );

            //CHECK RESPONSE
            $this->ZohoResponseCheck($response,"ZohoCreator.meta.form.READ");

            return $response->json()["fields"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 🌐🔐 getReportsMeta()
     *
     *  Return the meta information of all the reports present in a Zoho Creator application.
     *
     * 🚀 Return the meta information of all the reports present in a Zoho Creator application.
     * 📝 Context: ZohoCreatorService need to be ready
     *
     * @return array reports meta as an json array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function getReportsMeta() : array|string {
        try {
            $this->ZohoServiceCheck();

            //URL
            $full_url = $this->meta_base_url . "reports";

            //REQUEST
            $response = Http::withHeaders($this->getHeaders())->get(
                $full_url
            );

            //CHECK RESPONSE
            $this->ZohoResponseCheck($response,"ZohoCreator.meta.application.READ");

            return $response->json()["reports"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 🌐🔐 getPagesMeta()
     *
     *  Return the meta information of all the pages present in a Zoho Creator application.
     *
     * 🚀 Return the meta information of all the pages present in a Zoho Creator application.
     * 📝 Context: ZohoCreatorService need to be ready
     *
     * @return array page meta as an json array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function getPagesMeta() : array|string {
        try {
            $this->ZohoServiceCheck();

            //URL
            $full_url = $this->meta_base_url . "pages";

            //REQUEST
            $response = Http::withHeaders($this->getHeaders())->get(
                $full_url
            );

            //CHECK RESPONSE
            $this->ZohoResponseCheck($response,"ZohoCreator.meta.application.READ");

            return $response->json()["pages"];
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return [];
        }
    }

    ///////// TOOLS SECTION ////////

    /**
     * 🌐🔐 extractCsvFromZip()
     *
     *  Extract with ZipArchive from PHP the CSV of a ZIP bulk request
     *
     * 🚀 Extract with ZipArchive from PHP the CSV of a ZIP bulk request
     * 📝 Context: ZohoCreatorService don't need to be ready
     * 
     * @param string        $zip_location         required    Location of the ZIP from Bulk to extract
     * @param string        $extracted_location   required    Destination of the extracted CSV
     * @param string        $report               required    report from the bulk request
     * @param string        $bulk_id              required    ID of the bulk request
     *
     * @return string       path to the extracted CSV
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function extractCsvFromZip(string $zip_location, string $extracted_location, string $report, string $bulk_id) : string|array {
        try {
            
            $zip = new ZipArchive;
            $zip->open($zip_location);
            $zip->extractTo($extracted_location);
            $zip->close();
            return $extracted_location . "/" . $report . "_" . $bulk_id . ".csv";;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "KO";
        }
    }

    /**
     * 🌐🔐 transformCsvToJson()
     *
     *  Extract and transform the data from a CSV file to a JSON file at the same location
     *
     * 🚀 Transform a CSV to a JSON
     * 📝 Context: ZohoCreatorService don't need to be ready
     * 
     * @param string        $csv_location         required    Location of the CSV to transform
     *
     * @return string       path to the resulted JSON
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function transformCsvToJson(string $csv_location) : string|array {
        try {
            $json_location = substr_replace($csv_location, '', -4) . ".json";
            $csv_reader = fopen($csv_location, 'r');
            $csv_headers = fgetcsv($csv_reader); // Get column headers
            foreach($csv_headers as &$csv_header) {
                if(str_contains($csv_header,".")){
                    $csv_header = str_replace(".","->",$csv_header);
                }
            }
            $bulk_results_as_array = array();
            while (($row = fgetcsv($csv_reader))) {
                $bulk_results_as_array[] = array_combine($csv_headers, $row);
            }
            fclose($csv_reader);
            $bulk_results_as_json = json_encode($bulk_results_as_array, JSON_PRETTY_PRINT);
            file_put_contents($json_location, $bulk_results_as_json);
            return $json_location;
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "KO";
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

    //TEST FUNCTION
    public function test() : string {
        return "blob";
    }
}