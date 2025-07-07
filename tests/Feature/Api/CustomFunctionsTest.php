<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;
use Illuminate\Support\Facades\Http;

describe('Custom Functions Integration', function () {
    beforeEach(function () {
        ZohoConnectorToken::create([
            'token' => 'valid_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    });

    describe('Custom GET Functions', function () {
        it('calls custom function with authentication token', function () {
            Http::fake([
                'www.zohoapis.eu/creator/custom/*/test_function*' => Http::response([
                    'result' => 'success',
                    'data' => ['processed' => true]
                ], 200)
            ]);
            
            $result = ZohoCreatorApi::customFunctionGet('test_function', ['param1' => 'value1']);
            
            expect($result)->toBeArray();
            expect($result['result'])->toBe('success');
            
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'test_function') &&
                       str_contains($request->url(), 'param1=value1') &&
                       $request->hasHeader('Authorization');
            });
        });

        it('calls custom function with public key instead of token', function () {
            Http::fake([
                'www.zohoapis.eu/creator/custom/*/public_function*' => Http::response([
                    'result' => 'public_success'
                ], 200)
            ]);
            
            $result = ZohoCreatorApi::customFunctionGet('public_function', [], 'public_key_123');
            
            expect($result)->toBeArray();
            expect($result['result'])->toBe('public_success');
            
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'public_key_123') &&
                       !$request->hasHeader('Authorization');
            });
        });

        it('handles multiple parameters in GET request', function () {
            $params = [
                'filter' => 'active',
                'limit' => 100,
                'sort' => 'name_asc',
                'include_deleted' => 'false'
            ];

            Http::fake([
                'www.zohoapis.eu/creator/custom/*/filter_function*' => Http::response([
                    'result' => 'filtered',
                    'count' => 50
                ], 200)
            ]);

            $result = ZohoCreatorApi::customFunctionGet('filter_function', $params);

            expect($result['result'])->toBe('filtered');

            Http::assertSent(function ($request) use ($params) {
                $url = $request->url();
                foreach ($params as $key => $value) {
                    if (!str_contains($url, "{$key}={$value}")) {
                        return false;
                    }
                }
                return true;
            });
        });

        it('handles special characters in parameter values', function () {
            $params = [
                'company' => 'Test & Co.',
                'email' => 'test+alias@company.com',
                'tag' => '#important'
            ];

            Http::fake([
                'www.zohoapis.eu/creator/custom/*/search_function*' => Http::response([
                    'result' => 'found'
                ], 200)
            ]);

            $result = ZohoCreatorApi::customFunctionGet('search_function', $params);

            expect($result['result'])->toBe('found');

            Http::assertSent(function ($request) {
                $url = $request->url();
                // Check URL encoding
                return str_contains($url, 'Test%20%26%20Co.') &&
                       str_contains($url, 'test%2Balias%40company.com');
            });
        });
    });

    describe('Custom POST Functions', function () {
        it('sends POST data correctly to custom function', function () {
            Http::fake([
                'www.zohoapis.eu/creator/custom/*/process_data*' => Http::response([
                    'result' => 'processed',
                    'received_count' => 2
                ], 200)
            ]);
            
            $postData = [
                'companies' => [
                    ['name' => 'Company A', 'status' => 'Active'],
                    ['name' => 'Company B', 'status' => 'Pending']
                ],
                'operation' => 'bulk_update'
            ];
            
            $result = ZohoCreatorApi::customFunctionPost('process_data', $postData);
            
            expect($result)->toBeArray();
            expect($result['received_count'])->toBe(2);
            
            Http::assertSent(function ($request) use ($postData) {
                $body = json_decode($request->body(), true);
                return $body['operation'] === 'bulk_update' &&
                       count($body['companies']) === 2;
            });
        });

        it('handles custom function errors appropriately', function () {
            Http::fake([
                'www.zohoapis.eu/creator/custom/*/failing_function*' => Http::response([
                    'error' => 'Custom function error',
                    'details' => 'Invalid input parameters'
                ], 400)
            ]);
            
            expect(fn() => ZohoCreatorApi::customFunctionPost('failing_function', ['invalid' => 'data']))
                ->toThrow(\Exception::class, 'Custom function error');
        });

        it('sends authorization header with POST requests', function () {
            Http::fake([
                'www.zohoapis.eu/creator/custom/*/secure_function*' => Http::response([
                    'result' => 'authorized'
                ], 200)
            ]);

            ZohoCreatorApi::customFunctionPost('secure_function', ['data' => 'test']);

            Http::assertSent(function ($request) {
                return $request->hasHeader('Authorization') &&
                       str_contains($request->header('Authorization')[0], 'Zoho-oauthtoken');
            });
        });

        it('handles empty POST data gracefully', function () {
            Http::fake([
                'www.zohoapis.eu/creator/custom/*/empty_function*' => Http::response([
                    'result' => 'empty_handled'
                ], 200)
            ]);

            $result = ZohoCreatorApi::customFunctionPost('empty_function', []);

            expect($result['result'])->toBe('empty_handled');

            Http::assertSent(function ($request) {
                return $request->body() === '[]' || $request->body() === '{}';
            });
        });
    });

    describe('Custom Functions with Complex Data', function () {
        it('handles large data payloads in POST functions', function () {
            $largeData = [];
            for ($i = 0; $i < 1000; $i++) {
                $largeData[] = ['id' => $i, 'name' => "Record {$i}"];
            }
            
            Http::fake([
                '*bulk_process*' => Http::response([
                    'result' => 'success',
                    'processed_count' => 1000
                ], 200)
            ]);
            
            $result = ZohoCreatorApi::customFunctionPost('bulk_process', ['records' => $largeData]);
            
            expect($result['processed_count'])->toBe(1000);
            
            Http::assertSent(function ($request) {
                $body = json_decode($request->body(), true);
                return count($body['records']) === 1000;
            });
        });

        it('handles special characters and encoding in function parameters', function () {
            Http::fake(['*' => Http::response(['result' => 'encoded_success'], 200)]);
            
            $specialData = [
                'company_name' => 'Société Française & Co',
                'email' => 'contact@société.fr',
                'notes' => 'Special chars: àéêöü, symbols: €$£¥'
            ];
            
            $result = ZohoCreatorApi::customFunctionPost('process_special', $specialData);
            
            expect($result['result'])->toBe('encoded_success');
            
            Http::assertSent(function ($request) use ($specialData) {
                $body = json_decode($request->body(), true);
                return $body['company_name'] === $specialData['company_name'] &&
                       str_contains($body['notes'], '€$£¥');
            });
        });

        it('handles nested data structures', function () {
            $nestedData = [
                'company' => [
                    'name' => 'Test Corp',
                    'address' => [
                        'street' => '123 Main St',
                        'city' => 'Test City',
                        'coordinates' => [
                            'lat' => 48.8566,
                            'lng' => 2.3522
                        ]
                    ],
                    'contacts' => [
                        ['name' => 'John Doe', 'role' => 'CEO'],
                        ['name' => 'Jane Smith', 'role' => 'CTO']
                    ]
                ]
            ];

            Http::fake([
                '*nested_function*' => Http::response([
                    'result' => 'nested_processed',
                    'depth' => 3
                ], 200)
            ]);

            $result = ZohoCreatorApi::customFunctionPost('nested_function', $nestedData);

            expect($result['result'])->toBe('nested_processed');

            Http::assertSent(function ($request) use ($nestedData) {
                $body = json_decode($request->body(), true);
                return $body['company']['address']['coordinates']['lat'] === 48.8566 &&
                       count($body['company']['contacts']) === 2;
            });
        });

        it('handles binary data encoding', function () {
            $binaryData = [
                'file_content' => base64_encode('This is binary content'),
                'file_name' => 'document.pdf',
                'mime_type' => 'application/pdf'
            ];

            Http::fake([
                '*binary_function*' => Http::response([
                    'result' => 'binary_received',
                    'size' => strlen($binaryData['file_content'])
                ], 200)
            ]);

            $result = ZohoCreatorApi::customFunctionPost('binary_function', $binaryData);

            expect($result['result'])->toBe('binary_received');

            Http::assertSent(function ($request) use ($binaryData) {
                $body = json_decode($request->body(), true);
                return $body['file_content'] === $binaryData['file_content'];
            });
        });
    });

    describe('Custom Function Error Scenarios', function () {
        it('handles timeout errors', function () {
            Http::fake([
                '*timeout_function*' => function () {
                    sleep(31); // Simulate timeout
                    return Http::response(['result' => 'too_late'], 200);
                }
            ]);

            expect(fn() => ZohoCreatorApi::customFunctionGet('timeout_function'))
                ->toThrow(\Exception::class);
        });

        it('handles malformed JSON responses', function () {
            Http::fake([
                '*malformed_function*' => Http::response('Invalid JSON {broken', 200, [
                    'Content-Type' => 'application/json'
                ])
            ]);

            expect(fn() => ZohoCreatorApi::customFunctionGet('malformed_function'))
                ->toThrow(\Exception::class);
        });

        it('handles rate limiting on custom functions', function () {
            Http::fake([
                '*rate_limited_function*' => Http::sequence()
                    ->push(['error' => 'Rate limit exceeded'], 429)
                    ->push(['result' => 'success'], 200)
            ]);

            // First call should fail
            expect(fn() => ZohoCreatorApi::customFunctionGet('rate_limited_function'))
                ->toThrow(\Exception::class);

            // Second call should succeed after retry
            $result = ZohoCreatorApi::customFunctionGet('rate_limited_function');
            expect($result['result'])->toBe('success');
        });

        it('validates function name format', function () {
            Http::fake(['*' => Http::response(['result' => 'ok'], 200)]);

            // Valid function names
            $validNames = ['myFunction', 'my_function', 'myFunction123', 'my-function'];
            
            foreach ($validNames as $name) {
                $result = ZohoCreatorApi::customFunctionGet($name);
                expect($result['result'])->toBe('ok');
            }

            Http::assertSentCount(count($validNames));
        });
    });

    describe('Custom Function Authentication Modes', function () {
        it('switches between token and public key authentication', function () {
            Http::fake(['*' => Http::response(['result' => 'ok'], 200)]);

            // With token (default)
            ZohoCreatorApi::customFunctionGet('token_function');

            Http::assertSent(function ($request) {
                return $request->hasHeader('Authorization') &&
                       !str_contains($request->url(), 'publicKey');
            });

            // With public key
            ZohoCreatorApi::customFunctionGet('public_function', [], 'my_public_key');

            Http::assertSent(function ($request) {
                return !$request->hasHeader('Authorization') &&
                       str_contains($request->url(), 'publicKey=my_public_key');
            });
        });

        it('handles missing authentication gracefully', function () {
            // Remove token
            ZohoConnectorToken::truncate();

            Http::fake([
                '*' => Http::response(['error' => 'Unauthorized'], 401)
            ]);

            // Should fail without token or public key
            expect(fn() => ZohoCreatorApi::customFunctionGet('protected_function'))
                ->toThrow(\Exception::class);
        });
    });
});