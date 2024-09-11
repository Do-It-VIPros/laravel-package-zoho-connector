<?php

//$aliases['ZohoCreatorApi'] = Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade::class;

return [

    /*
    |--------------------------------------------------------------------------
    | Zoho connector config
    |--------------------------------------------------------------------------
    */

    'base_account_url' => ("https://accounts.zoho." . env('ZOHO_ACCOUNT_DOMAIN', 'eu')),

    'api_base_url' => ("https://www.zohoapis." . env('ZOHO_ACCOUNT_DOMAIN', 'eu')),

    'client_id' => env('ZOHO_CLIENT_ID', "1000.8cb99dxxxxxxxxxxxxx9be93"),

    'client_secret' => env('ZOHO_CLIENT_SECRET', "9b8xxxxxxxxxxxxxxxf"),

    'user' => env('ZOHO_USER', 'jason18'),

    'scope' => env('ZOHO_SCOPE', 'ZohoCreator.report.READ'),

    'app_name' => env('ZOHO_APP_NAME', 'zylker-store'),

    'tokens_table_name' => env('ZOHO_TOKENS_TABLE', 'zoho_connector_tokens'),
    'bulks_table_name' => env('ZOHO_BULKS_TABLE', 'zoho_connector_bulk_history'),

    'bulk_download_path' => env('ZOHO_BULK_DOWNLOAD_PATH', storage_path("zohoconnector")),

];
