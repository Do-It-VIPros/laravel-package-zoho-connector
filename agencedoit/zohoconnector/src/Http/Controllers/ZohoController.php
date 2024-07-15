<?php

namespace Agencedoit\ZohoConnector\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;


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
    public static function requestCode() : RedirectResponse {

        try {

            //? Redirect to Zoho Creator Authorization URL
            $url = 'https://accounts.zoho.eu/oauth/v2/auth';

            $queryParams = [
                'response_type' =>'code',
                'client_id' => config('zohoApi.client_id'),
                'scope' => 'ZohoCreator.report.ALL',
                'redirect_uri' => env("APP_URL") . "/zoho/request-code-response",
                'access_type' => 'offline',
                'prompt' => 'consent',
                'content-length' => ''
            ];

            return redirect()->away($url . '?' . http_build_query($queryParams));

        }catch (\Exception $e) {
            Log::error('Erreur lors de la redirection vers Zoho: ' . $e->getMessage());
            return redirect('/');
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
            return $request->input('code');
        }catch (\Exception $e) {
            Log::error('Erreur lors de la réponse de Zoho: ' . $e->getMessage());
            return $e->getMessage();
        }

    }

    /**
     * 🔑 requestAccessToken($code)
     *
     * Requests access token from Zoho OAuth2 service using authorization code.
     *
     * 🚀 Initiates the request to Zoho OAuth2 service to exchange authorization code
     *    for access token and refresh token.
     * 📝 Context: This method should be called after receiving authorization code
     *            from Zoho Creator's OAuth2 authorization flow.
     *
     * @param string $code The authorization code received from Zoho Creator.
     * @return \Illuminate\Http\RedirectResponse Redirects to '/' if tokens are successfully retrieved and saved.
     *                                           Logs error and redirects to '/' on failure.
     */
    public static function requestAccessToken($code) {

        try {

            //? Send request to Zoho OAuth2 token endpoint
            $response = Http::asForm()->post('https://accounts.zoho.eu/oauth/v2/token', [
                'grant_type' => 'authorization_code',
                'client_id' => config('zoho.client_id'),
                'client_secret' => config('zoho.client_secret'),
                'redirect_uri' => env("APP_URL"),
                'code' => $code,
                'prompt' => 'consent',
            ]);

            //? Handle successful response
            if ($response->successful()) {
                $token = $response->json();

                //? Save tokens in database
                ZohoToken::create([
                    'access_token' => $token['access_token'],
                    'refresh_token' => $token['refresh_token'],
                    'expires_in' => $token['expires_in'],
                ]);

                return redirect('/');

            } else {

                //? Log error if request fails
                Log::error('La demande de access_token a échoué avec le code ' . $response->status());
            }
        } catch (\Exception $e) {

            //? Log any exceptions that occur during token request
            Log::error('Erreur lors de la demande de token (requestAccessToken function) ' . $e->getMessage());

            //? Redirect to '/' in case of error
            return redirect('/');
        }

    }


    /**
     * 🔑 refreshAccessToken()
     *
     * Refreshes access token using the stored refresh token.
     *
     * 🚀 Initiates the request to Zoho OAuth2 service to refresh the access token
     *    using the stored refresh token.
     * 📝 Context: This method assumes there is a single record in the database containing
     *            the refresh token.
     *
     * @return void
     */
    public static function refreshAccessToken() {

        try {

            //? Retrieve the refresh token from database (assuming there's only one record)
            $tokens = ZohoToken::where('id', 1)->first();

            if (!$tokens) {
                Log::error('No refresh token found in database.');
                return;
            }

            //? Prepare HTTP request to refresh access token
            $response = Http::asForm()->post('https://accounts.zoho.eu/oauth/v2/token', [
                'refresh_token' => $tokens['refresh_token'],
                'grant_type' => 'refresh_token',
                'client_id' => config('zoho.client_id'),
                'client_secret' => config('zoho.client_secret'),
                'redirect_uri' => 'https://marques.vipros.fr',
            ]);

            //? Handle successful response
            if ($response->successful()) {
                $token = $response->json();

                //? Updates access token in database.
                DB::table('zoho_tokens')->where('id', 1)->update(['access_token' => $token['access_token'], 'updated_at' => now()]);
                log::info('le token a été mis à jour');

            } else {
                Log::error('La demande de refresh_token a échoué avec le code ' . $response->status());
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors de la demande de refresh_token (refreshAccessToken function) ' . $e->getMessage());
            return redirect('/');
        }

    }

    /**
     * 🔑 checkRefreshToken()
     *
     * Checks if the access token needs refreshing based on its expiration.
     *
     * 🚀 Checks if the stored access token is expired (older than 1 hour).
     *    If expired, triggers the refreshAccessToken method to obtain a new token.
     * 📝 Context: This method assumes there is a single record in the database containing
     *            the Zoho access token.
     *
     * @return void
     */
    public static function checkRefreshToken() : void {

        try {
            $zohoToken = ZohoToken::first();

            if ($zohoToken && $zohoToken->updated_at->diffInSeconds(now()) > 3600) {
                ZohoController::refreshAccessToken();
            }

        } catch (\Exception $e) {
            Log::error('Error while checking refresh token: ' . $e->getMessage());
        }

    }

}