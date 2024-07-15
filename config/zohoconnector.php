<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zoho connector config
    |--------------------------------------------------------------------------
    */

    'base_account_url' => env('ZOHO_API_BASE_URL', 'https://accounts.zoho.eu'),

    'api_base_url' => env('ZOHO_API_BASE_URL', 'https://creator.zoho.eu'),

    'client_id' => env('ZOHO_CLIENT_ID'),

    'client_secret' => env('ZOHO_CLIENT_SECRET'),

    'user' => env('ZOHO_USER', 'agencedoit'),

    'app_name' => env('ZOHO_APP_NAME', 'ypp-tmp'),

    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),

];
