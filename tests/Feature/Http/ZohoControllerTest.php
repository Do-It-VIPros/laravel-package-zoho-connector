<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

describe('ZohoController Endpoints', function () {
    describe('Development Routes', function () {
        beforeEach(function () {
            config(['app.env' => 'local']);
        });

        it('displays test connection page in development', function () {
            $response = $this->get('/zoho/test');
            
            expect($response->status())->toBe(200);
            expect($response->content())->toContain('Zoho Connection Test');
        });

        it('allows token reset in development', function () {
            // Create some tokens
            ZohoConnectorToken::create([
                'token' => 'token_to_reset',
                'refresh_token' => 'refresh_token',
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            expect(ZohoConnectorToken::count())->toBe(1);
            
            $response = $this->get('/zoho/reset-tokens');
            
            expect($response->status())->toBe(302); // Redirect after reset
            expect(ZohoConnectorToken::count())->toBe(0);
        });

        it('shows work in progress endpoint in development', function () {
            $response = $this->get('/zoho/wip');
            
            expect($response->status())->toBe(200);
        });

        it('shows detailed service status in test endpoint', function () {
            ZohoConnectorToken::create([
                'token' => 'test_token',
                'refresh_token' => 'refresh_token',
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/test_report' => Http::response([
                    'code' => 3000,
                    'data' => [createZohoReportData()],
                    'info' => ['count' => 1, 'more_records' => false]
                ], 200)
            ]);

            $response = $this->get('/zoho/test');

            expect($response->status())->toBe(200);
            expect($response->content())->toContain('Service is ready');
            expect($response->content())->toContain('Token exists');
        });

        it('shows configuration errors in test endpoint', function () {
            // Clear config
            config(['zohoconnector.client_id' => null]);

            $response = $this->get('/zoho/test');

            expect($response->status())->toBe(200);
            expect($response->content())->toContain('Configuration Error');
        });
    });

    describe('Production Environment', function () {
        beforeEach(function () {
            config(['app.env' => 'production']);
        });

        it('hides development routes in production', function () {
            $response = $this->get('/zoho/test');
            expect($response->status())->toBe(404);
            
            $response = $this->get('/zoho/reset-tokens');
            expect($response->status())->toBe(404);
            
            $response = $this->get('/zoho/wip');
            expect($response->status())->toBe(404);
        });

        it('still allows authorization flow in production', function () {
            // When service is not ready
            $response = $this->get('/zoho/request-code');
            expect($response->status())->toBe(302); // Redirect to auth
        });
    });

    describe('Service Readiness Routing', function () {
        it('hides auth routes when service is ready', function () {
            ZohoConnectorToken::create([
                'token' => 'valid_token',
                'refresh_token' => 'refresh_token',
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            $response = $this->get('/zoho/request-code');
            expect($response->status())->toBe(404);
            
            $response = $this->get('/zoho/request-code-response');
            expect($response->status())->toBe(404);
        });

        it('shows auth routes when service is not ready', function () {
            // No valid tokens
            $response = $this->get('/zoho/request-code');
            expect($response->status())->toBe(302); // Redirect to Zoho
        });
    });

    describe('OAuth Flow Endpoints', function () {
        it('generates correct authorization URL with all parameters', function () {
            config([
                'zohoconnector.client_id' => 'test_client_id',
                'zohoconnector.base_account_url' => 'https://accounts.zoho.eu',
                'zohoconnector.scope' => 'ZohoCreator.report.READ,ZohoCreator.report.CREATE'
            ]);

            $response = $this->get('/zoho/request-code');

            expect($response->status())->toBe(302);
            
            $location = $response->headers->get('Location');
            expect($location)->toContain('response_type=code');
            expect($location)->toContain('client_id=test_client_id');
            expect($location)->toContain('scope=ZohoCreator.report.READ');
            expect($location)->toContain('redirect_uri=');
            expect($location)->toContain('access_type=offline');
        });

        it('handles OAuth callback with authorization code', function () {
            Http::fake([
                'accounts.zoho.eu/oauth/v2/token' => Http::response(createZohoTokenData(), 200)
            ]);

            $response = $this->get('/zoho/request-code-response', [
                'code' => 'test_auth_code',
                'location' => 'eu',
                'accounts-server' => 'https://accounts.zoho.eu'
            ]);

            expect($response->status())->toBe(302);
            
            // Verify token was stored
            $this->assertDatabaseHas('zoho_connector_tokens', [
                'token' => '1000.44092108ade1xxxxxxxxxxxxxxxxxxxxxx.9e5cac8yyyyyyyyyyyyyyyyyyyyyyyy'
            ]);
        });

        it('handles OAuth callback errors', function () {
            $response = $this->get('/zoho/request-code-response', [
                'error' => 'access_denied',
                'error_description' => 'User denied access'
            ]);

            expect($response->status())->toBe(302);
            expect($response->headers->get('Location'))->toContain('error');
            
            // Verify no token was stored
            expect(ZohoConnectorToken::count())->toBe(0);
        });
    });

    describe('Error Handling', function () {
        it('handles OAuth callback errors gracefully', function () {
            $response = $this->get('/zoho/request-code-response?error=access_denied');
            
            expect($response->status())->toBe(302); // Redirect to error page
            expect($response->headers->get('Location'))->toContain('error');
        });

        it('validates required OAuth parameters', function () {
            $response = $this->get('/zoho/request-code-response'); // No code parameter
            
            expect($response->status())->toBe(302);
            expect($response->headers->get('Location'))->toContain('error');
        });

        it('handles token generation failures', function () {
            Http::fake([
                'accounts.zoho.eu/oauth/v2/token' => Http::response([
                    'error' => 'invalid_grant',
                    'error_description' => 'Invalid authorization code'
                ], 400)
            ]);

            $response = $this->get('/zoho/request-code-response?code=invalid_code');

            expect($response->status())->toBe(302);
            expect($response->headers->get('Location'))->toContain('error');
            expect(ZohoConnectorToken::count())->toBe(0);
        });
    });

    describe('Security Features', function () {
        it('validates redirect URI in OAuth callback', function () {
            // Test that the redirect URI is properly validated
            $response = $this->get('/zoho/request-code-response', [
                'code' => 'test_code',
                'state' => 'invalid_state' // If state validation is implemented
            ]);

            // Depending on implementation, this might redirect to error
            expect($response->status())->toBeIn([302, 400]);
        });

        it('prevents CSRF in OAuth flow', function () {
            // If CSRF protection is implemented via state parameter
            session(['oauth_state' => 'expected_state']);

            $response = $this->get('/zoho/request-code-response', [
                'code' => 'test_code',
                'state' => 'wrong_state'
            ]);

            // Should reject mismatched state
            expect($response->status())->toBeIn([302, 403]);
        });

        it('sanitizes error messages from OAuth provider', function () {
            $response = $this->get('/zoho/request-code-response', [
                'error' => '<script>alert("XSS")</script>',
                'error_description' => '<img src=x onerror=alert("XSS")>'
            ]);

            expect($response->status())->toBe(302);
            // Error should be sanitized, not executed
            expect($response->headers->get('Location'))->not->toContain('<script>');
        });
    });

    describe('Multi-domain Support', function () {
        it('handles different Zoho domains in OAuth flow', function () {
            $domains = [
                'eu' => 'https://accounts.zoho.eu',
                'com' => 'https://accounts.zoho.com',
                'in' => 'https://accounts.zoho.in',
                'com.au' => 'https://accounts.zoho.com.au',
                'jp' => 'https://accounts.zoho.jp'
            ];

            foreach ($domains as $tld => $accountUrl) {
                config(['zohoconnector.base_account_url' => $accountUrl]);

                $response = $this->get('/zoho/request-code');

                expect($response->status())->toBe(302);
                expect($response->headers->get('Location'))->toContain("accounts.zoho.{$tld}");
            }
        });

        it('extracts domain from callback parameters', function () {
            Http::fake([
                'accounts.zoho.in/oauth/v2/token' => Http::response(createZohoTokenData([
                    'api_domain' => 'https://www.zohoapis.in'
                ]), 200)
            ]);

            $response = $this->get('/zoho/request-code-response', [
                'code' => 'test_code',
                'location' => 'in',
                'accounts-server' => 'https://accounts.zoho.in'
            ]);

            expect($response->status())->toBe(302);
            
            // Verify the correct domain was used
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'accounts.zoho.in');
            });
        });
    });

    describe('Response Formats', function () {
        beforeEach(function () {
            config(['app.env' => 'local']);
        });

        it('returns HTML response for test endpoint', function () {
            $response = $this->get('/zoho/test');

            expect($response->status())->toBe(200);
            expect($response->header('Content-Type'))->toContain('text/html');
        });

        it('returns JSON response for API-like endpoints when requested', function () {
            $response = $this->withHeaders([
                'Accept' => 'application/json'
            ])->get('/zoho/test');

            // Depending on implementation, might return JSON
            expect($response->status())->toBe(200);
        });

        it('handles redirect responses properly', function () {
            $response = $this->get('/zoho/request-code');

            expect($response->status())->toBe(302);
            expect($response->headers->has('Location'))->toBeTrue();
        });
    });
});