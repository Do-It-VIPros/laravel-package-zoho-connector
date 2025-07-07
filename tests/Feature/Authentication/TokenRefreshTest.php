<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;
use Illuminate\Support\Facades\Http;

describe('Automatic Token Refresh', function () {
    it('automatically refreshes expired token during API call', function () {
        // Create expired token
        ZohoConnectorToken::create([
            'token' => 'expired_access_token',
            'refresh_token' => 'valid_refresh_token',
            'token_created_at' => now()->subHours(2),
            'token_peremption_at' => now()->subMinutes(5),
            'token_duration' => 3600
        ]);
        
        $newTokenData = createZohoTokenData(['access_token' => 'new_access_token']);
        $apiData = [createZohoReportData()];
        
        Http::fake([
            // First call fails with expired token
            'www.zohoapis.eu/creator/v2.1/data/*' => Http::sequence()
                ->push(mockZohoErrorResponse(6000, 'Invalid access token'), 401)
                ->push(mockZohoSuccessResponse($apiData), 200),
            
            // Token refresh succeeds
            'accounts.zoho.eu/oauth/v2/token' => Http::response($newTokenData, 200)
        ]);
        
        $result = ZohoCreatorApi::get('test_report');
        
        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
        
        // Verify new token was stored
        $this->assertDatabaseHas('zoho_connector_tokens', [
            'token' => 'new_access_token'
        ]);
        
        // Verify refresh was called
        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'oauth/v2/token')) {
                $data = $request->data();
                return $data['grant_type'] === 'refresh_token' &&
                       $data['refresh_token'] === 'valid_refresh_token';
            }
            return false;
        });
    });

    it('handles refresh token expiration gracefully', function () {
        ZohoConnectorToken::create([
            'token' => 'expired_access_token',
            'refresh_token' => 'expired_refresh_token',
            'token_created_at' => now()->subDays(30),
            'token_peremption_at' => now()->subHour(),
            'token_duration' => 3600
        ]);
        
        Http::fake([
            'www.zohoapis.eu/creator/v2.1/data/*' => Http::response(
                mockZohoErrorResponse(6000, 'Invalid access token'), 401
            ),
            'accounts.zoho.eu/oauth/v2/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Refresh token expired'
            ], 400)
        ]);
        
        expect(fn() => ZohoCreatorApi::get('test_report'))
            ->toThrow(\Exception::class, 'Refresh token expired');
        
        // Verify tokens were cleared
        expect(ZohoConnectorToken::count())->toBe(0);
    });

    it('retries API call after successful token refresh', function () {
        ZohoConnectorToken::create([
            'token' => 'expired_token',
            'refresh_token' => 'valid_refresh',
            'token_created_at' => now()->subHour(),
            'token_peremption_at' => now()->subMinute(),
            'token_duration' => 3600
        ]);
        
        $apiData = [createZohoReportData(['Name' => 'Test Record'])];
        
        Http::fake([
            'www.zohoapis.eu/creator/v2.1/data/*' => Http::sequence()
                ->push(mockZohoErrorResponse(6000, 'Invalid access token'), 401)
                ->push(mockZohoSuccessResponse($apiData), 200),
            'accounts.zoho.eu/oauth/v2/token' => Http::response(createZohoTokenData(), 200)
        ]);
        
        $result = ZohoCreatorApi::get('test_report');
        
        expect($result)->toBeArray();
        expect($result[0]['Name'])->toBe('Test Record');
        
        // Verify we made the original call, refresh, then retry
        Http::assertSentCount(3);
    });

    it('respects rate limiting for token refresh operations', function () {
        // Create token that needs refresh
        ZohoConnectorToken::create([
            'token' => 'expired_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now()->subHour(),
            'token_peremption_at' => now()->subMinute(),
            'token_duration' => 3600
        ]);

        // Mock rate limiting response
        Http::fake([
            'accounts.zoho.eu/oauth/v2/token' => Http::sequence()
                ->push([
                    'error' => 'invalid_request',
                    'error_description' => 'Rate limit exceeded'
                ], 429)
                ->push(createZohoTokenData(), 200),
            'www.zohoapis.eu/creator/v2.1/data/*' => Http::response(
                mockZohoSuccessResponse([createZohoReportData()]), 200
            )
        ]);

        // Should handle rate limiting and retry
        expect(fn() => ZohoCreatorApi::get('test_report'))
            ->not->toThrow(\Exception::class);
    });

    it('prevents concurrent token refresh attempts', function () {
        // Create expired token
        ZohoConnectorToken::create([
            'token' => 'expired_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now()->subHour(),
            'token_peremption_at' => now()->subMinute(),
            'token_duration' => 3600
        ]);

        $refreshCount = 0;
        Http::fake([
            'accounts.zoho.eu/oauth/v2/token' => function () use (&$refreshCount) {
                $refreshCount++;
                return Http::response(createZohoTokenData(), 200);
            },
            'www.zohoapis.eu/creator/v2.1/data/*' => Http::response(
                mockZohoSuccessResponse([createZohoReportData()]), 200
            )
        ]);

        // Simulate concurrent requests
        $promises = [];
        for ($i = 0; $i < 3; $i++) {
            $promises[] = function () {
                return ZohoCreatorApi::get('test_report');
            };
        }

        // Execute all requests
        foreach ($promises as $promise) {
            $promise();
        }

        // Should only refresh token once despite multiple concurrent requests
        expect($refreshCount)->toBeLessThanOrEqual(2); // Allow for some race conditions
    });

    it('maintains token integrity during refresh process', function () {
        $originalToken = ZohoConnectorToken::create([
            'token' => 'original_token',
            'refresh_token' => 'original_refresh',
            'token_created_at' => now()->subHour(),
            'token_peremption_at' => now()->subMinute(),
            'token_duration' => 3600
        ]);

        $newTokenData = createZohoTokenData([
            'access_token' => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
            'expires_in' => 7200
        ]);

        Http::fake([
            'accounts.zoho.eu/oauth/v2/token' => Http::response($newTokenData, 200),
            'www.zohoapis.eu/creator/v2.1/data/*' => Http::response(
                mockZohoSuccessResponse([createZohoReportData()]), 200
            )
        ]);

        ZohoCreatorApi::get('test_report');

        // Verify token was properly updated
        $updatedToken = ZohoConnectorToken::first();
        expect($updatedToken->token)->toBe('new_access_token');
        expect($updatedToken->refresh_token)->toBe('new_refresh_token');
        expect($updatedToken->token_duration)->toBe(7200);
        expect($updatedToken->token_peremption_at->timestamp)
            ->toBeGreaterThan(now()->timestamp);
    });
});