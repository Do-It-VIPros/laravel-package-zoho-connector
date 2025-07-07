<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;

describe('Simple API Tests (No Database)', function () {
    
    describe('Basic API Operations', function () {
        it('can get records with mocked response', function () {
            // Mock d'une réponse Zoho API
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/Companies*' => Http::response([
                    'code' => 3000,
                    'data' => [
                        [
                            'ID' => '123456789',
                            'Company_Name' => 'Test Company',
                            'Status' => 'Active'
                        ]
                    ],
                    'info' => [
                        'count' => 1,
                        'more_records' => false
                    ]
                ], 200)
            ]);

            // Mock d'un token en cache
            Cache::put('zoho_token', [
                'access_token' => 'mock_access_token',
                'refresh_token' => 'mock_refresh_token',
                'expires_in' => 3600,
                'created_at' => now(),
                'expires_at' => now()->addHour()
            ], 3600);

            // Test de l'API
            $result = ZohoCreatorApi::get('Companies');

            expect($result)->toBeArray();
            expect($result)->toHaveCount(1);
            expect($result[0]['Company_Name'])->toBe('Test Company');
            
            // Vérifier que l'API a été appelée
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'Companies') &&
                       $request->hasHeader('Authorization');
            });
        });

        it('handles API errors gracefully', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3001,
                    'message' => 'Record not found'
                ], 404)
            ]);

            Cache::put('zoho_token', [
                'access_token' => 'mock_token',
                'expires_at' => now()->addHour()
            ], 3600);

            expect(fn() => ZohoCreatorApi::get('NonExistent'))
                ->toThrow(\Exception::class, 'Record not found');
        });
    });

    describe('Real API Tests (Optional)', function () {
        it('can test with real Zoho API if configured', function () {
            // Skip si pas de credentials réels
            if (!env('ZOHO_REAL_API_TESTS', false)) {
                $this->markTestSkipped('Real API tests disabled. Set ZOHO_REAL_API_TESTS=true to enable.');
            }

            // Test avec vraie API (nécessite des credentials valides)
            $result = ZohoCreatorApi::get('your_real_report_name');
            
            expect($result)->toBeArray();
        });
    });
});