<?php

use Agencedoit\ZohoConnector\Http\Controllers\ZohoController;
use Agencedoit\ZohoConnector\Services\ZohoCreatorService;

if(!(new ZohoCreatorService)->isReady()) {
    Route::get('/zoho/request-code', [ZohoController::class, 'requestCode']);
    Route::get('/zoho/request-code-response', [ZohoController::class, 'requestCodeResponse']);
}
if(config('app.env') != 'production') {
    Route::get('/zoho/test', [ZohoController::class, 'test_connexion']);
}