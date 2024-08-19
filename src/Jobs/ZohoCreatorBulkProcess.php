<?php
 
namespace Agencedoit\ZohoConnector\Jobs;

use ZohoCreatorApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
        $bulk_history_id = ZohoCreatorApi::createBulkAuto($this->report,$this->call_back_url,$this->criteria);
        while(!ZohoCreatorApi::readBulkAuto($bulk_history_id)) {
            sleep(30);
        }
        ZohoCreatorApi::downloadBulkAuto($bulk_history_id);
        //TODO Ajouter la fonction de transformation en JSON
    }
}