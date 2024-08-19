<?php

namespace Agencedoit\ZohoConnector\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

use Agencedoit\ZohoConnector\Services\ZohoCreatorService;
use ZohoCreatorApi;

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
     *
     * @throws \Exception If an error occurs during the redirection process, it logs the error.
     */
    public function requestCode() : string|RedirectResponse {

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
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
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
     * @return string in production env the page will just send an OK Sentence and the page will not be available any more
     * @return RedirectResponse in all over environement to the test_connexion page
     *
     * @throws \Exception If an error occurs during the redirection process, it logs the error.
     */
    public function requestCodeResponse(Request $request) : string|RedirectResponse {

        try {
            if($request->input('code') === null) {
                throw new Exception("No client secret found", 404);    
            }

            if(!(new ZohoCreatorService)->generateFirstToken($request->input('code'))){
                //? Log error if process has failed
                throw new Exception("Process failed. ZohoCreatorService is not ready", 404);
            }
            return (config('app.env') != 'production' ? redirect()->action([ZohoController::class, 'test_connexion']): "Token is now generated, you can now leave this page." );
        } catch (Exception $e) {
            //? Log any exceptions that occur during token request
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return $e->getMessage();
        }

    }

    
    /**
     * 🌐🔐 reset_tokens()
     *
     *  Reset of the created tokens
     *
     * 🚀 Launch a truncate of the database
     * 📝 Context: Available only on dev environnement from /zoho/reset-tokens
     *
     * @return string if an error occured
     * @return RedirectResponse to the test page
     *
     * @throws \Exception If an error occurs during the redirection process, it logs the error.
     */
    public function reset_tokens() : string|RedirectResponse {
        try{
            ZohoCreatorApi::resetTokens();
            return redirect()->action([ZohoController::class, 'test_connexion']);
        } catch (Exception $e) {
            //? Log any exceptions that occur during token request
            Log::error('Error on ' . get_class($this) . '::' . __FUNCTION__ . ' => ' . $e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * 🌐🔐 test_connexion()
     *
     *  Show the CreatorService infos
     *
     * 🚀 Show the test_connexion page
     * 📝 Context: Available only on dev environnement from /zoho/test
     *
     * @return View of the test page
     *
     */
    public static function test_connexion() {
        return View::make('zohoconnector::test_connexion');
    }
    
    //TEST FUNCTION
    public function test() : string {
        return ZohoCreatorApi::getWithBulk("API_Produits","/test");
    }
}