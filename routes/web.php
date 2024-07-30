<?php

use Agencedoit\ZohoConnector\Http\Controllers\ZohoController;

use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;

if(!ZohoCreatorApi::isReady()) {
    Route::get('/zoho/request-code', [ZohoController::class, 'requestCode']);
    Route::get('/zoho/request-code-response', [ZohoController::class, 'requestCodeResponse']);
}
if(config('app.env') != 'production') {
    Route::get('/zoho/test', [ZohoController::class, 'test_connexion']);
    Route::get('/zoho/wip', [ZohoController::class, 'test']);
    Route::get('/zoho/reset-tokens', [ZohoController::class, 'reset_tokens']);
}