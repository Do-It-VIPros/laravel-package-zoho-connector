<?php

namespace Agencedoit\ZohoConnector\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

use ZohoCreatorApi;

use \Exception;

trait ZohoServiceChecker
{
    protected function ZohoServiceCheck() : void
    {
        if (!ZohoCreatorApi::isReady()) {
            Log::error('ZohoCreatorService is not ready. Please init it.');
            Log::error('See ' . env("APP_URL") . '/zoho/test for more informations.');
            throw new Exception('ZohoCreatorService is not ready. Please init it.');
        }
        else if (config('zohoconnector.environment') != ""
                    && config('zohoconnector.environment') != "development"
                    && config('zohoconnector.environment') != "stage"
                    && config('zohoconnector.environment') != "production") {
            Log::error('zohoconnector.environment is not set correctly. (' . config('zohoconnector.environment') . '). Choices are : empty,development, stage or production.');
            throw new Exception('ZohoCreatorService is not ready. zohoconnector.environment is not correct.');
        }
    }

   protected function ZohoResponseCheck(Response $response, string $specific = ""): void
{
    Log::info('📨 Réponse complète de Zoho', [
    'zoho_response' => $response->json() ?? [],
]);

    try {
        $json = $response->json();

        if (!$response->successful() || !isset($json['code']) || $json['code'] != 3000) {
            // Gestion spécifique code 2945 (scope)
            if (isset($json['code']) && $json['code'] == 2945) {
                throw new \Exception("Please add " . $specific . " in ZOHO_SCOPE env variable.");
            }

            // 🔎 Construction du message d'erreur lisible
            $message = 'Erreur Zoho : ';

            if (isset($json['error']) && is_array($json['error'])) {
                $message .= implode('; ', $json['error']);
            } elseif (isset($json['message'])) {
                $message .= $json['message'];
            } else {
                $message .= json_encode($json);
            }

            throw new \Exception($message);
        }
    } catch (Exception $e) {
        Log::error('❌ Erreur dans ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
        throw new \Exception($e->getMessage(), 503);
    }
}
}
