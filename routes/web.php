<?php

use Agencedoit\ZohoConnector\Http\Controllers\ZohoController;
use Agencedoit\ZohoConnector\Services\ZohoCreatorService;

use Illuminate\Support\Facades\Schema;

if(Schema::hasTable('zoho_connector_tokens') && !(new ZohoCreatorService)->isReady()) {
    Route::get('/zoho/request-code', [ZohoController::class, 'requestCode']);
    Route::get('/zoho/request-code-response', [ZohoController::class, 'requestCodeResponse']);
}
if(config('app.env') != 'production') {
    Route::get('/zoho/test', [ZohoController::class, 'test_connexion']);
    Route::get('/zoho/wip', [ZohoController::class, 'test']);
}