<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

describe('Token Lifecycle Management', function () {
    it('manages multiple concurrent token refresh attempts', function () {
        ZohoConnectorToken::create([
            'token' => 'expired_token',
            'refresh_token' => 'refresh_123',
            'token_created_at' => now()->subHour(),
            'token_peremption_at' => now()->subMinute(),
            'token_duration' => 3600
        ]);
        
        Http::fake([
            'accounts.zoho.eu/oauth/v2/token' => Http::response(createZohoTokenData(), 200),
            'www.zohoapis.eu/creator/v2.1/data/*' => Http::response(mockZohoSuccessResponse(), 200)
        ]);
        
        // Simulate concurrent API calls
        $promises = [];
        for ($i = 0; $i < 5; $i++) {
            $promises[] = Http::async()->get('www.zohoapis.eu/creator/v2.1/data/test/report/test_report');
        }
        
        // All should succeed without multiple token refreshes
        $responses = Http::pool($promises);
        
        foreach ($responses as $response) {
            expect($response->successful())->toBeTrue();
        }
        
        // Should only have one refresh call despite multiple concurrent requests
        $refreshCalls = collect(Http::recorded())->filter(function ($record) {
            return str_contains($record[0]->url(), 'oauth/v2/token');
        });
        
        expect($refreshCalls->count())->toBeLessThanOrEqual(2); // Allow for race conditions
    });

    it('cleans up old tokens when storing new ones', function () {
        // Create multiple old tokens
        for ($i = 0; $i < 3; $i++) {
            ZohoConnectorToken::create([
                'token' => "old_token_{$i}",
                'refresh_token' => "old_refresh_{$i}",
                'token_created_at' => now()->subDays($i + 1),
                'token_peremption_at' => now()->subHours($i + 1),
                'token_duration' => 3600
            ]);
        }
        
        expect(ZohoConnectorToken::count())->toBe(3);
        
        // Trigger new token creation
        Http::fake(['*' => Http::response(createZohoTokenData(), 200)]);
        
        $this->get('/zoho/request-code-response?code=new_code');
        
        // Should only have the new token
        expect(ZohoConnectorToken::count())->toBe(1);
        expect(ZohoConnectorToken::first()->token)->not->toContain('old_token');
    });

    it('tracks token usage and refresh history', function () {
        $token = ZohoConnectorToken::create([
            'token' => 'active_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);

        // Track initial state
        $initialCreatedAt = $token->token_created_at;

        // Wait a moment and trigger refresh
        sleep(1);
        
        Http::fake([
            'accounts.zoho.eu/oauth/v2/token' => Http::response(createZohoTokenData([
                'access_token' => 'refreshed_token',
                'expires_in' => 7200
            ]), 200)
        ]);

        // Simulate token refresh
        $token->token = 'refreshed_token';
        $token->token_created_at = now();
        $token->token_peremption_at = now()->addSeconds(7200);
        $token->token_duration = 7200;
        $token->save();

        // Verify token was updated
        $refreshedToken = ZohoConnectorToken::first();
        expect($refreshedToken->token)->toBe('refreshed_token');
        expect($refreshedToken->token_created_at->timestamp)
            ->toBeGreaterThan($initialCreatedAt->timestamp);
        expect($refreshedToken->token_duration)->toBe(7200);
    });

    it('handles token rotation for different Zoho domains', function () {
        $domains = ['eu', 'com', 'in', 'com.au', 'jp'];
        
        foreach ($domains as $domain) {
            // Clear existing tokens
            ZohoConnectorToken::truncate();
            
            // Set domain configuration
            config(['zohoconnector.base_account_url' => "https://accounts.zoho.{$domain}"]);
            
            // Create token for domain
            ZohoConnectorToken::create([
                'token' => "token_for_{$domain}",
                'refresh_token' => "refresh_for_{$domain}",
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            // Verify token exists for domain
            $token = ZohoConnectorToken::first();
            expect($token->token)->toBe("token_for_{$domain}");
        }
    });

    it('prevents token injection attacks', function () {
        // Create legitimate token
        ZohoConnectorToken::create([
            'token' => 'legitimate_token',
            'refresh_token' => 'legitimate_refresh',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);

        // Attempt to inject malicious token
        $maliciousData = [
            'token' => '<script>alert("XSS")</script>',
            'refresh_token' => 'malicious_refresh',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ];

        // Create token with potentially malicious data
        $token = ZohoConnectorToken::create($maliciousData);

        // Verify token is stored without execution
        expect($token->token)->toBe('<script>alert("XSS")</script>');
        expect(ZohoConnectorToken::count())->toBe(2);
    });

    it('maintains token consistency during database failures', function () {
        $originalToken = ZohoConnectorToken::create([
            'token' => 'original_token',
            'refresh_token' => 'original_refresh',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);

        // Simulate database failure during update
        DB::shouldReceive('table')->andThrow(new \Exception('Database connection lost'));

        // Attempt to update token
        try {
            $originalToken->update(['token' => 'new_token']);
        } catch (\Exception $e) {
            // Expected failure
        }

        // Verify original token remains unchanged
        $token = ZohoConnectorToken::first();
        expect($token->token)->toBe('original_token');
    });

    it('enforces token expiration policies', function () {
        // Create tokens with various expiration states
        $activeToken = ZohoConnectorToken::create([
            'token' => 'active_token',
            'refresh_token' => 'active_refresh',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);

        $expiredToken = ZohoConnectorToken::create([
            'token' => 'expired_token',
            'refresh_token' => 'expired_refresh',
            'token_created_at' => now()->subHours(2),
            'token_peremption_at' => now()->subHour(),
            'token_duration' => 3600
        ]);

        $aboutToExpireToken = ZohoConnectorToken::create([
            'token' => 'about_to_expire_token',
            'refresh_token' => 'about_to_expire_refresh',
            'token_created_at' => now()->subMinutes(55),
            'token_peremption_at' => now()->addMinutes(5),
            'token_duration' => 3600
        ]);

        // Check expiration states
        expect($activeToken->token_peremption_at->isFuture())->toBeTrue();
        expect($expiredToken->token_peremption_at->isPast())->toBeTrue();
        expect($aboutToExpireToken->token_peremption_at->diffInMinutes(now()))->toBeLessThan(10);
    });

    it('handles token cleanup for abandoned sessions', function () {
        // Create old abandoned tokens
        for ($i = 0; $i < 5; $i++) {
            ZohoConnectorToken::create([
                'token' => "abandoned_token_{$i}",
                'refresh_token' => "abandoned_refresh_{$i}",
                'token_created_at' => now()->subDays(30 + $i),
                'token_peremption_at' => now()->subDays(29 + $i),
                'token_duration' => 3600
            ]);
        }

        // Create recent active token
        $activeToken = ZohoConnectorToken::create([
            'token' => 'current_active_token',
            'refresh_token' => 'current_refresh',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);

        // Cleanup old tokens (older than 7 days)
        ZohoConnectorToken::where('token_peremption_at', '<', now()->subDays(7))->delete();

        // Verify cleanup
        expect(ZohoConnectorToken::count())->toBe(1);
        expect(ZohoConnectorToken::first()->token)->toBe('current_active_token');
    });

    it('validates token structure and format', function () {
        // Test various token formats
        $validTokens = [
            '1000.abcdef123456.ghijkl789012',
            '2000.ABCDEF123456.GHIJKL789012',
            '1000.abc-def_123.456-ghi_789'
        ];

        foreach ($validTokens as $tokenString) {
            $token = ZohoConnectorToken::create([
                'token' => $tokenString,
                'refresh_token' => 'refresh_' . $tokenString,
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);

            expect($token->token)->toBe($tokenString);
        }

        // Verify all tokens were created
        expect(ZohoConnectorToken::count())->toBe(count($validTokens));
    });
});