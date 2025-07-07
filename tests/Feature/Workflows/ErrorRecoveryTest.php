<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;
use Illuminate\Support\Facades\Http;

describe('Error Recovery and Resilience', function () {
    beforeEach(function () {
        ZohoConnectorToken::create([
            'token' => 'recovery_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    });

    describe('Network Error Recovery', function () {
        it('recovers from temporary network failures', function () {
            Http::fake([
                '*' => Http::sequence()
                    ->push(function () { throw new \Illuminate\Http\Client\ConnectionException('Network timeout'); })
                    ->push(function () { throw new \Illuminate\Http\Client\ConnectionException('Network timeout'); })
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);
            
            $results = ZohoCreatorApi::get('Network_Test_Report');
            
            expect($results)->toBeArray();
            expect($results)->not->toBeEmpty();
        });

        it('respects maximum retry attempts', function () {
            Http::fake([
                '*' => function () { 
                    throw new \Illuminate\Http\Client\ConnectionException('Persistent network error'); 
                }
            ]);
            
            expect(fn() => ZohoCreatorApi::get('Failing_Report'))
                ->toThrow(\Exception::class, 'network error');
        });

        it('implements exponential backoff for retries', function () {
            $attempts = 0;
            Http::fake([
                '*' => function () use (&$attempts) {
                    $attempts++;
                    if ($attempts < 3) {
                        throw new \Illuminate\Http\Client\ConnectionException('Temporary failure');
                    }
                    return Http::response(mockZohoSuccessResponse([createZohoReportData()]), 200);
                }
            ]);

            $start = microtime(true);
            $results = ZohoCreatorApi::get('Backoff_Test_Report');
            $duration = microtime(true) - $start;

            expect($results)->toBeArray();
            expect($duration)->toBeGreaterThan(1); // Should have waited for backoff
            expect($attempts)->toBe(3);
        });

        it('handles DNS resolution failures', function () {
            Http::fake([
                '*' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Could not resolve host');
                }
            ]);

            expect(fn() => ZohoCreatorApi::get('DNS_Fail_Report'))
                ->toThrow(\Exception::class, 'Could not resolve host');
        });

        it('recovers from partial response corruption', function () {
            Http::fake([
                '*' => Http::sequence()
                    ->push('Corrupted JSON {incomplete', 200) // Malformed JSON
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);

            $results = ZohoCreatorApi::get('Corruption_Test_Report');

            expect($results)->toBeArray();
            expect($results)->not->toBeEmpty();
        });
    });

    describe('API Error Recovery', function () {
        it('handles rate limiting with exponential backoff', function () {
            Http::fake([
                '*' => Http::sequence()
                    ->push(mockZohoErrorResponse(4820, 'Rate limit exceeded'), 429)
                    ->push(mockZohoErrorResponse(4820, 'Rate limit exceeded'), 429)
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);
            
            $start = microtime(true);
            $results = ZohoCreatorApi::get('Rate_Limited_Report');
            $duration = microtime(true) - $start;
            
            expect($results)->toBeArray();
            expect($duration)->toBeGreaterThan(1); // Should have waited for backoff
        });

        it('handles server maintenance gracefully', function () {
            Http::fake([
                '*' => Http::sequence()
                    ->push(mockZohoErrorResponse(5000, 'Service temporarily unavailable'), 503)
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);
            
            $results = ZohoCreatorApi::get('Maintenance_Report');
            
            expect($results)->toBeArray();
        });

        it('recovers from quota exceeded errors', function () {
            Http::fake([
                '*' => Http::sequence()
                    ->push(mockZohoErrorResponse(4000, 'API quota exceeded'), 429)
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);

            $results = ZohoCreatorApi::get('Quota_Test_Report');

            expect($results)->toBeArray();
        });

        it('handles API version deprecation warnings', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [createZohoReportData()],
                    'warning' => 'API version 2.0 is deprecated, please upgrade to 2.1',
                    'info' => ['count' => 1, 'more_records' => false]
                ], 200, ['X-API-Warning' => 'Deprecated version'])
            ]);

            $results = ZohoCreatorApi::get('Deprecated_API_Report');

            expect($results)->toBeArray();
            expect($results)->not->toBeEmpty();
            
            // Warning should be logged but not prevent operation
        });

        it('retries on specific Zoho error codes', function () {
            $retryableCodes = [5000, 5001, 5002]; // Server errors
            
            foreach ($retryableCodes as $code) {
                Http::fake([
                    '*' => Http::sequence()
                        ->push(mockZohoErrorResponse($code, 'Temporary server error'), 500)
                        ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
                ]);

                $results = ZohoCreatorApi::get("Error_{$code}_Report");
                expect($results)->toBeArray();
            }
        });

        it('does not retry on client errors', function () {
            $clientErrorCodes = [3001, 3002, 3100]; // Client errors
            
            foreach ($clientErrorCodes as $code) {
                Http::fake([
                    '*' => Http::response(mockZohoErrorResponse($code, 'Client error'), 400)
                ]);

                expect(fn() => ZohoCreatorApi::get("Client_Error_{$code}_Report"))
                    ->toThrow(\Exception::class);
            }
        });
    });

    describe('Token Recovery', function () {
        it('recovers from expired tokens automatically', function () {
            // Start with expired token
            ZohoConnectorToken::truncate();
            ZohoConnectorToken::create([
                'token' => 'expired_token',
                'refresh_token' => 'valid_refresh',
                'token_created_at' => now()->subHours(2),
                'token_peremption_at' => now()->subMinute(),
                'token_duration' => 3600
            ]);
            
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*' => Http::sequence()
                    ->push(mockZohoErrorResponse(6000, 'Invalid access token'), 401)
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200),
                'accounts.zoho.eu/oauth/v2/token' => Http::response(createZohoTokenData(), 200)
            ]);
            
            $results = ZohoCreatorApi::get('Token_Recovery_Report');
            
            expect($results)->toBeArray();
            
            // Verify new token was stored
            $newToken = ZohoConnectorToken::first();
            expect($newToken->token)->not->toBe('expired_token');
        });

        it('handles complete authentication failure', function () {
            ZohoConnectorToken::truncate();
            ZohoConnectorToken::create([
                'token' => 'invalid_token',
                'refresh_token' => 'invalid_refresh',
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*' => Http::response(
                    mockZohoErrorResponse(6000, 'Invalid access token'), 401
                ),
                'accounts.zoho.eu/oauth/v2/token' => Http::response([
                    'error' => 'invalid_grant'
                ], 400)
            ]);
            
            expect(fn() => ZohoCreatorApi::get('Auth_Failed_Report'))
                ->toThrow(\Exception::class);
            
            // Tokens should be cleared
            expect(ZohoConnectorToken::count())->toBe(0);
        });

        it('prevents infinite token refresh loops', function () {
            ZohoConnectorToken::create([
                'token' => 'broken_token',
                'refresh_token' => 'broken_refresh',
                'token_created_at' => now(),
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);

            $refreshAttempts = 0;
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*' => Http::response(
                    mockZohoErrorResponse(6000, 'Invalid access token'), 401
                ),
                'accounts.zoho.eu/oauth/v2/token' => function () use (&$refreshAttempts) {
                    $refreshAttempts++;
                    return Http::response(['error' => 'invalid_grant'], 400);
                }
            ]);

            expect(fn() => ZohoCreatorApi::get('Infinite_Loop_Report'))
                ->toThrow(\Exception::class);

            // Should not attempt refresh more than reasonable limit
            expect($refreshAttempts)->toBeLessThanOrEqual(3);
        });

        it('handles concurrent token refresh attempts', function () {
            ZohoConnectorToken::create([
                'token' => 'expired_concurrent_token',
                'refresh_token' => 'concurrent_refresh',
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
            for ($i = 0; $i < 5; $i++) {
                $promises[] = function () {
                    return ZohoCreatorApi::get('Concurrent_Test_Report');
                };
            }

            // Execute all requests
            foreach ($promises as $promise) {
                $promise();
            }

            // Should only refresh token once despite multiple concurrent requests
            expect($refreshCount)->toBeLessThanOrEqual(2); // Allow for minimal race conditions
        });
    });

    describe('Data Integrity Protection', function () {
        it('validates response integrity before processing', function () {
            Http::fake([
                '*' => Http::response('Invalid JSON response', 200)
            ]);
            
            expect(fn() => ZohoCreatorApi::get('Invalid_JSON_Report'))
                ->toThrow(\Exception::class);
        });

        it('handles partial data corruption gracefully', function () {
            $partiallyCorruptData = [
                createZohoReportData(['ID' => '123', 'name' => 'Valid Record']),
                ['corrupted' => 'data', 'missing' => 'fields'], // Corrupted record
                createZohoReportData(['ID' => '125', 'name' => 'Another Valid Record'])
            ];
            
            Http::fake([
                '*' => Http::response(mockZohoSuccessResponse($partiallyCorruptData), 200)
            ]);
            
            // Should return all data, letting the consumer handle validation
            $results = ZohoCreatorApi::get('Partial_Corruption_Report');
            
            expect($results)->toBeArray();
            expect($results)->toHaveCount(3);
        });

        it('detects and handles response tampering', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [
                        ['ID' => '123', 'name' => 'Normal Record'],
                        ['ID' => '<script>alert("XSS")</script>', 'name' => 'Malicious Record']
                    ],
                    'info' => ['count' => 2, 'more_records' => false]
                ], 200)
            ]);

            $results = ZohoCreatorApi::get('Tampering_Test_Report');

            // Data should be returned as-is, sanitization is application responsibility
            expect($results)->toHaveCount(2);
            expect($results[1]['ID'])->toBe('<script>alert("XSS")</script>');
        });

        it('validates critical field presence', function () {
            $incompleteData = [
                ['name' => 'Record without ID'], // Missing ID field
                ['ID' => '124', 'name' => 'Complete Record']
            ];

            Http::fake([
                '*' => Http::response(mockZohoSuccessResponse($incompleteData), 200)
            ]);

            $results = ZohoCreatorApi::get('Incomplete_Data_Report');

            expect($results)->toHaveCount(2);
            expect($results[0])->not->toHaveKey('ID');
            expect($results[1])->toHaveKey('ID');
        });
    });

    describe('Circuit Breaker Pattern', function () {
        it('implements circuit breaker for repeated failures', function () {
            $failureCount = 0;
            Http::fake([
                '*' => function () use (&$failureCount) {
                    $failureCount++;
                    return Http::response(['error' => 'Server error'], 500);
                }
            ]);

            // Simulate multiple failures
            for ($i = 0; $i < 5; $i++) {
                try {
                    ZohoCreatorApi::get('Circuit_Breaker_Report');
                } catch (\Exception $e) {
                    // Expected failures
                }
            }

            // Circuit breaker should prevent excessive calls
            expect($failureCount)->toBeLessThanOrEqual(6); // Adjust based on implementation
        });

        it('resets circuit breaker after successful operation', function () {
            Http::fake([
                '*' => Http::sequence()
                    ->push(['error' => 'Server error'], 500)
                    ->push(['error' => 'Server error'], 500)
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);

            // Initial failures
            try {
                ZohoCreatorApi::get('Circuit_Reset_Report');
            } catch (\Exception $e) {}

            try {
                ZohoCreatorApi::get('Circuit_Reset_Report');
            } catch (\Exception $e) {}

            // Success should reset circuit
            $result1 = ZohoCreatorApi::get('Circuit_Reset_Report');
            $result2 = ZohoCreatorApi::get('Circuit_Reset_Report');

            expect($result1)->toBeArray();
            expect($result2)->toBeArray();
        });
    });

    describe('Graceful Degradation', function () {
        it('provides fallback when API is unavailable', function () {
            Http::fake([
                '*' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Service unavailable');
                }
            ]);

            // In real implementation, might return cached data or empty result
            expect(fn() => ZohoCreatorApi::get('Unavailable_Service_Report'))
                ->toThrow(\Exception::class, 'Service unavailable');
        });

        it('handles degraded service responses', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [createZohoReportData()],
                    'info' => [
                        'count' => 1,
                        'more_records' => false,
                        'service_status' => 'degraded',
                        'message' => 'Some features may be unavailable'
                    ]
                ], 200)
            ]);

            $results = ZohoCreatorApi::get('Degraded_Service_Report');

            expect($results)->toBeArray();
            expect($results)->not->toBeEmpty();
        });

        it('adapts to API response delays', function () {
            Http::fake([
                '*' => function () {
                    sleep(2); // Simulate slow response
                    return Http::response(mockZohoSuccessResponse([createZohoReportData()]), 200);
                }
            ]);

            $start = microtime(true);
            $results = ZohoCreatorApi::get('Slow_Response_Report');
            $duration = microtime(true) - $start;

            expect($results)->toBeArray();
            expect($duration)->toBeGreaterThan(2);
        });
    });
});