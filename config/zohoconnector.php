<?php

//$aliases['ZohoCreatorApi'] = Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade::class;

return [

    /*
    |--------------------------------------------------------------------------
    | Zoho connector config
    |--------------------------------------------------------------------------
    */

    'base_account_url' => ("https://accounts.zoho." . env('ZOHO_ACCOUNT_DOMAIN', 'eu')),

    'api_base_url' => ("https://creator.zoho." . env('ZOHO_ACCOUNT_DOMAIN', 'eu')),

    'client_id' => env('ZOHO_CLIENT_ID', "1000.8cb99dxxxxxxxxxxxxx9be93"),

    'client_secret' => env('ZOHO_CLIENT_SECRET', "9b8xxxxxxxxxxxxxxxf"),

    'user' => env('ZOHO_USER', 'jason18'),

    'scope' => env('ZOHO_SCOPE', 'ZohoCreator.report.ALL'),

    'app_name' => env('ZOHO_APP_NAME', 'zylker-store'),

    'tokens_table_name' => 'zoho_connector_tokens',
    
    'aliases' => [
        'ZohoCreatorApi' => Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade::class
    ],

];
