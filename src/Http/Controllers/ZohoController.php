<?php

namespace Agencedoit\ZohoConnector\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Services\ZohoCreatorService;
use ZohoCreatorApi;

use \Datetime;
use \DateInterval;
use \Exception;

class ZohoController extends Controller
{
    /**
     * 🌐🔐 requestCode()
     *
     * Redirects to Zoho Creator OAuth2 Authorization URL for obtaining authorization code.
     *
     * 🚀 Initiates the OAuth2 flow to redirect users to Zoho Creator for authorization.
     * 📝 Context: This method constructs the URL with necessary parameters for OAuth2 authorization
     *            to Zoho Creator.
     *
     * @return \Illuminate\Http\RedirectResponse Redirects the user to Zoho Creator's authorization page.
     *                                           If successful, user will be redirected back with an authorization code.
     *                                           Otherwise, redirects to '/' on error.
     *
     * @throws \Exception If an error occurs during the redirection process, it logs the error.
     */
    public static function requestCode() : RedirectResponse|string {

        try {
            if(config('zohoconnector.user') === null || config('zohoconnector.user') == "jason18") {
                $error = "Please fill the required environnement variables.<br>";
                $error .= "Refer to the README to discover the environnement variable to set.";
                throw new Exception($error, 404);
            }

            //? Redirect to Zoho Creator Authorization URL
            $url = config('zohoconnector.base_account_url') . '/oauth/v2/auth';

            $queryParams = [
                'response_type' =>'code',
                'client_id' => config('zohoconnector.client_id'),
                'scope' => config('zohoconnector.scope'),
                'redirect_uri' => env("APP_URL") . "/zoho/request-code-response",
                'access_type' => 'offline',
                'prompt' => 'consent',
                'content-length' => ''
            ];

            return redirect()->away($url . '?' . http_build_query($queryParams));

        }catch (Exception $e) {
            Log::error('Erreur lors de la redirection vers Zoho: ' . $e->getMessage());
            return $e;
        }

    }

    
    /**
     * 🌐🔐 requestCodeResponse()
     *
     *  Retrieving and storing the Creator authentication response code
     *
     * 🚀 Store the returned code of requestCode() function
     * 📝 Context: Storage of the auth token.
     *
     * @return string
     *
     * @throws \Exception If an error occurs during the redirection process, it logs the error.
     */
    public static function requestCodeResponse(Request $request) : string {

        try {
            if($request->input('code') === null) {
                throw new Exception("No client secret found", 404);    
            }

            if(!(new ZohoCreatorService)->generateFirstToken($request->input('code'))){
                //? Log error if process has failed
                throw new Exception("Process failed. ZohoCreatorService is not ready", 404);
            }
            return "Token generated. You can now close this page.";
        } catch (Exception $e) {
            //? Log any exceptions that occur during token request
            Log::error('Error on token request (requestAccessToken function) ' . $e->getMessage());
            return $e->getMessage();
        }

    }

    public static function test() {
        return ZohoCreatorApi::test();
    }

}