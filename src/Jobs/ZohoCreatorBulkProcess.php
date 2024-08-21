<?php
 
namespace Agencedoit\ZohoConnector\Jobs;

use Agencedoit\ZohoConnector\Models\ZohoBulkHistory;
use Exception;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use \ZipArchive;

class ZohoCreatorBulkProcess implements ShouldQueue
{
    use Queueable,Dispatchable,InteractsWithQueue,SerializesModels;

    private $report;
    private $call_back_url;
    private $criteria;
    /**
     * Create a new job instance.
     */
    public function __construct(string $report, string $call_back_url, string|array $criteria = "") {
        $this->report = $report;
        $this->call_back_url = $call_back_url;
        $this->criteria = $criteria;
    }
 
    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try{
            ///////Information save
            $bulk_history_id = ZohoCreatorApi::createBulkAuto($this->report,$this->call_back_url,$this->criteria);
            $bulk_history = ZohoBulkHistory::find($bulk_history_id);
            $bulk_history->step = "reading";
            $bulk_history->save();
            ///////Waiting for ready status
            while(!ZohoCreatorApi::bulkIsReadyAuto($bulk_history_id)) {
                sleep(10);
            }
            $bulk_history->step = "ready";
            $bulk_history->save();
            ///////Download of the ZIP file
            $downloaded_zip_path = ZohoCreatorApi::downloadBulkAuto($bulk_history_id);
            $extracted_zip_path = substr_replace($downloaded_zip_path, '', -4) . "/extracted";
            $bulk_history->step = "downloaded";
            $bulk_history->save();           
            ///////EXTRACT OF THE ZIP
            $extracted_csv = ZohoCreatorApi::extractCsvFromZip($downloaded_zip_path, $extracted_zip_path, $bulk_history->report,$bulk_history->bulk_id);
            $bulk_history->step = "extracted";
            $bulk_history->save();
            ///////TRANSFORM TO JSON
            $json_location = ZohoCreatorApi::transformCsvToJson($extracted_csv);
            $bulk_history->step = "transformed";
            $bulk_history->save();
            ///////CALLBACK
            Http::get($bulk_history->call_back_url, [
                'json_location' => $json_location]);
            $bulk_history->step = "finished";
            $bulk_history->save();
        } catch (Exception $e) {
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
        }    
    }
}