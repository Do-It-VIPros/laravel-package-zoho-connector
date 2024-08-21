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
        $this->api_base_url = config('zohoconnector.api_base_url') . "/api/v2.1/" . config('zohoconnector.user') . "/" . config('zohoconnector.app_name');
        $this->bulk_base_url = config('zohoconnector.bulk_base_url') . "/creator/v2.1/bulk/" . config('zohoconnector.user') . "/" . config('zohoconnector.app_name') . "/report/";
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
            $full_url = $this->api_base_url . "/report/" . $report;

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
     *
     * @return array datas as an json array
     *
     * @throws \Exception If an error occurs during the process, it logs the error.
     */
    public function getAll(string $report, string|array $criteria = "") : array {
        try {
            $this->ZohoServiceCheck();
           
            $cursor = "";

            $found_datas = $this->get($report, $criteria, $cursor);
            while($cursor != "") {
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
    public function createBulk(string $report, string|array $criteria = "") : string|array {
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

            $this->ZohoResponseCheck($response,"ZohoCreator.bulk.READ");

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
            ZohoCreatorBulkProcess::dispatch($report, $call_back_url, $criteria);
            return "OK";
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return "KO";
        }
    }

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