<?php

namespace Agencedoit\ZohoConnector\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

use ZohoCreatorApi;

use \Exception;

trait ZohoServiceChecker
{
    protected function ZohoServiceCheck(): void
    {
        // En mode test, ignorer les vérifications de service
        if (config('zohoconnector.test_mode', false)) {
            return;
        }

        if (!ZohoCreatorApi::isReady()) {
            Log::error('ZohoCreatorService is not ready. Please init it.');
            Log::error('See ' . env("APP_URL") . '/zoho/test for more informations.');
            throw new Exception('ZohoCreatorService is not ready. Please init it.');
        } else if (
            config('zohoconnector.environment') != ""
            && config('zohoconnector.environment') != "development"
            && config('zohoconnector.environment') != "stage"
            && config('zohoconnector.environment') != "production"
        ) {
            Log::error('zohoconnector.environment is not set correctly. (' . config('zohoconnector.environment') . '). Choices are : empty,development, stage or production.');
            throw new Exception('ZohoCreatorService is not ready. zohoconnector.environment is not correct.');
        }
    }

    protected function ZohoResponseCheck(Response $response, string $specific = ""): void
{
    try {
        $json = $response->json();

        if (!$response->successful() || !isset($json['code']) || (int)$json['code'] !== 3000) {
            // Gestion spécifique code 2945 (scope)
            if (isset($json['code']) && (int)$json['code'] === 2945) {
                throw new Exception("Please add {$specific} in ZOHO_SCOPE env variable.");
            }

            // 🔎 Construction du message d'erreur lisible et SANS implode sur des sous-tableaux
            $message = 'Erreur Zoho : ';

            if (array_key_exists('error', (array)$json)) {
                $message .= $this->stringifyZohoPart($json['error']);
            } elseif (array_key_exists('message', (array)$json)) {
                $message .= $this->stringifyZohoPart($json['message']);
            } elseif (!empty($json)) {
                $message .= json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $message .= 'Réponse vide ou non décodable (code HTTP: ' . $response->status() . ')';
            }

            throw new Exception($message);
        }
    } catch (Exception $e) {
        Log::error('❌ Erreur dans ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
        throw new Exception($e->getMessage(), 503);
    }
}

/**
 * Sérialise proprement une structure Zoho (string|scalar|array|objets) en string.
 */
private function stringifyZohoPart(mixed $part): string
{
    if (is_string($part)) {
        return $part;
    }
    if (is_scalar($part) || $part === null) {
        return (string)$part;
    }
    // Tableaux/objets : JSON lisible, sans échapper l’unicode ni les slashs
    return json_encode($part, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

}
