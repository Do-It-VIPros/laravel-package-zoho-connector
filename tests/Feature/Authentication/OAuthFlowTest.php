<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Illuminate\Support\Facades\Http;

describe('OAuth Authentication Flow', function () {
    beforeEach(function () {
        // Clean slate for each test
        ZohoConnectorToken::truncate();
    });

    describe('Complete OAuth Flow', function () {
        it('completes full OAuth authorization flow', function () {
            // Step 1: Request authorization URL
            $response = $this->get('/zoho/request-code');
            
            expect($response->status())->toBe(302);
            expect($response->headers->get('Location'))
                ->toContain('accounts.zoho.eu/oauth/v2/auth');
        });

        it('handles authorization callback successfully', function () {
            $tokenData = createZohoTokenData();
            
            Http::fake([
                'accounts.zoho.eu/oauth/v2/token' => Http::response($tokenData, 200)
            ]);
            
            // Simulate callback from Zoho
            $response = $this->get('/zoho/request-code-response', [
                'code' => 'authorization_code_123',
                'location' => 'eu',
                'accounts-server' => 'https://accounts.zoho.eu'
            ]);
            
            expect($response->status())->toBe(302); // Redirect after success
            
            // Verify token was stored
            $this->assertDatabaseHas('zoho_connector_tokens', [
                'token' => $tokenData['access_token']
            ]);
            
            // Verify HTTP call was made correctly
            Http::assertSent(function ($request) {
                $data = $request->data();
                return $data['code'] === 'authorization_code_123' &&
                       $data['grant_type'] === 'authorization_code';
            });
        });

        it('handles OAuth errors gracefully', function () {
            Http::fake([
                'accounts.zoho.eu/oauth/v2/token' => Http::response([
                    'error' => 'invalid_grant',
                    'error_description' => 'Invalid authorization code'
                ], 400)
            ]);
            
            $response = $this->get('/zoho/request-code-response?code=invalid_code');
            
            expect($response->status())->toBe(302); // Redirect to error page
            
            // Verify no token was stored
            expect(ZohoConnectorToken::count())->toBe(0);
        });
    });

    describe('Different Zoho Domains', function () {
        it('handles EU domain correctly', function () {
            config(['zohoconnector.base_account_url' => 'https://accounts.zoho.eu']);
            
            $response = $this->get('/zoho/request-code');
            
            expect($response->headers->get('Location'))
                ->toContain('accounts.zoho.eu');
        });

        it('handles COM domain correctly', function () {
            config(['zohoconnector.base_account_url' => 'https://accounts.zoho.com']);
            
            $response = $this->get('/zoho/request-code');
            
            expect($response->headers->get('Location'))
                ->toContain('accounts.zoho.com');
        });

        it('handles IN domain correctly', function () {
            config(['zohoconnector.base_account_url' => 'https://accounts.zoho.in']);
            
            $response = $this->get('/zoho/request-code');
            
            expect($response->headers->get('Location'))
                ->toContain('accounts.zoho.in');
        });
    });

    describe('Service State Management', function () {
        it('prevents duplicate authorization when service is ready', function () {
            // Create valid token
            ZohoConnectorToken::create([
                'token' => 'valid_token',
                'refresh_token' => 'refresh_token',
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            // Should not show authorization endpoints
            $response = $this->get('/zoho/request-code');
            expect($response->status())->toBe(404); // Route not available
        });

        it('allows re-authorization when token is expired', function () {
            // Create expired token
            ZohoConnectorToken::create([
                'token' => 'expired_token',
                'refresh_token' => 'refresh_token',
                'token_created_at' => now()->subHours(2),
                'token_peremption_at' => now()->subHour(),
                'token_duration' => 3600
            ]);
            
            $response = $this->get('/zoho/request-code');
            expect($response->status())->toBe(302); // Redirect to auth
        });
    });
});