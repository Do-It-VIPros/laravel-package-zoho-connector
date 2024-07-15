<?php

use Agencedoit\ZohoConnector\Http\Controllers\ZohoController;

if(config('app.env') != 'production') {
    Route::get('/zoho/request-code', [ZohoController::class, 'requestCode']);
    Route::get('/zoho/request-code-response', [ZohoController::class, 'requestCodeResponse']);
}