# PHASE 2 : UNIT TESTS - Tests unitaires core

## 🎯 Objectifs de la Phase 2
- ✅ Tests unitaires des services core (ZohoCreatorService, ZohoTokenManagement)
- ✅ Tests des modèles (ZohoConnectorToken, ZohoBulkHistory)
- ✅ Tests des facades et helpers
- ✅ Coverage > 85% sur les composants core

**Durée estimée** : 5-6 jours  
**Commit** : `test: add comprehensive unit tests for core services`

---

## 📁 Structure à créer

```
tests/Unit/
├── Services/
│   ├── ZohoCreatorServiceTest.php    ✅ Service principal
│   ├── ZohoTokenManagementTest.php   ✅ Gestion tokens
│   └── ZohoServiceCheckerTest.php    ✅ Trait validation
├── Models/
│   ├── ZohoConnectorTokenTest.php    ✅ Model token
│   └── ZohoBulkHistoryTest.php       ✅ Model bulk history
├── Jobs/
│   └── ZohoCreatorBulkProcessTest.php ✅ Job traitement bulk
├── Facades/
│   └── ZohoCreatorFacadeTest.php     ✅ Facade principale
└── Helpers/
    └── ResponseValidationTest.php    ✅ Validation réponses
```

---

## 🧪 Tests des Services

### 1. ZohoCreatorServiceTest.php
```php
<?php

use Agencedoit\ZohoConnector\Services\ZohoCreatorService;
use Agencedoit\ZohoConnector\Tests\Helpers\ZohoApiMockingHelper;
use Illuminate\Support\Facades\Http;

describe('ZohoCreatorService', function () {
    beforeEach(function () {
        $this->service = new ZohoCreatorService();
        ZohoApiMockingHelper::reset();
    });

    describe('CRUD Operations', function () {
        describe('get() method', function () {
            it('retrieves records successfully', function () {
                $testData = [createZohoReportData(['Name' => 'Test Record'])];
                $this->mockZohoResponse('test_report', $testData);
                
                $result = $this->service->get('test_report');
                
                expect($result)->toBeArray();
                expect($result)->toHaveCount(1);
                expect($result[0]['Name'])->toBe('Test Record');
            });

            it('handles pagination correctly', function () {
                Http::fake([
                    '*test_report*' => Http::response(mockZohoPaginatedResponse(
                        [createZohoReportData()], 
                        'cursor_123'
                    ))
                ]);
                
                $cursor = '';
                $result = $this->service->get('test_report', '', $cursor);
                
                expect($result)->toBeArray();
                expect($cursor)->toBe('cursor_123');
            });

            it('handles empty results', function () {
                $this->mockZohoResponse('empty_report', []);
                
                $result = $this->service->get('empty_report');
                
                expect($result)->toBeArray();
                expect($result)->toHaveCount(0);
            });

            it('throws exception on API error', function () {
                $this->mockZohoError(500, 'Internal Server Error');
                
                expect(fn() => $this->service->get('error_report'))
                    ->toThrow(\Exception::class);
            });
        });

        describe('getAll() method', function () {
            it('retrieves all records with pagination', function () {
                // Mock multiple pages
                $pages = [
                    [createZohoReportData(['Name' => 'Record 1'])],
                    [createZohoReportData(['Name' => 'Record 2'])],
                ];
                
                ZohoApiMockingHelper::mockZohoPagination('paginated_report', $pages);
                
                $result = $this->service->getAll('paginated_report');
                
                expect($result)->toBeArray();
                expect($result)->toHaveCount(2);
                expect($result[0]['Name'])->toBe('Record 1');
                expect($result[1]['Name'])->toBe('Record 2');
            });

            it('handles single page correctly', function () {
                $testData = [createZohoReportData(['Name' => 'Single Record'])];
                $this->mockZohoResponse('single_report', $testData);
                
                $result = $this->service->getAll('single_report');
                
                expect($result)->toBeArray();
                expect($result)->toHaveCount(1);
            });
        });

        describe('getByID() method', function () {
            it('retrieves specific record by ID', function () {
                $testData = createZohoReportData(['ID' => '123', 'Name' => 'Specific Record']);
                
                Http::fake([
                    '*test_report/123*' => Http::response(mockZohoSuccessResponse([$testData]))
                ]);
                
                $result = $this->service->getByID('test_report', '123');
                
                expect($result)->toBeArray();
                expect($result['ID'])->toBe('123');
                expect($result['Name'])->toBe('Specific Record');
            });

            it('throws exception when record not found', function () {
                Http::fake([
                    '*test_report/999*' => Http::response(mockZohoErrorResponse(3001, 'Record not found'), 404)
                ]);
                
                expect(fn() => $this->service->getByID('test_report', '999'))
                    ->toThrow(\Exception::class);
            });
        });

        describe('create() method', function () {
            it('creates record successfully', function () {
                $responseData = ['ID' => '123', 'Added_Time' => now()->toISOString()];
                
                Http::fake([
                    '*test_form*' => Http::response(['code' => 3000, 'data' => $responseData])
                ]);
                
                $attributes = ['Name' => 'New Record', 'Status' => 'Active'];
                $result = $this->service->create('test_form', $attributes);
                
                expect($result)->toBeArray();
                expect($result['ID'])->toBe('123');
                
                Http::assertSent(function ($request) use ($attributes) {
                    $body = json_decode($request->body(), true);
                    return $body['data']['Name'] === $attributes['Name'];
                });
            });

            it('handles validation errors', function () {
                Http::fake([
                    '*test_form*' => Http::response(mockZohoErrorResponse(3002, 'Validation failed'), 400)
                ]);
                
                expect(fn() => $this->service->create('test_form', ['invalid' => 'data']))
                    ->toThrow(\Exception::class);
            });
        });

        describe('update() method', function () {
            it('updates record successfully', function () {
                $responseData = ['ID' => '123', 'Modified_Time' => now()->toISOString()];
                
                Http::fake([
                    '*test_report/123*' => Http::response(['code' => 3000, 'data' => $responseData])
                ]);
                
                $attributes = ['Name' => 'Updated Record'];
                $result = $this->service->update('test_report', '123', $attributes);
                
                expect($result)->toBeArray();
                expect($result['ID'])->toBe('123');
            });
        });

        describe('upload() method', function () {
            it('uploads file successfully', function () {
                Storage::fake('local');
                $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
                
                Http::fake([
                    '*test_report/123/upload*' => Http::response(['code' => 3000, 'message' => 'File uploaded'])
                ]);
                
                $result = $this->service->upload('test_report', '123', 'document_field', $file);
                
                expect($result)->toBeArray();
                Http::assertSent(function ($request) {
                    return $request->hasFile('document_field');
                });
            });
        });
    });

    describe('Bulk Operations', function () {
        describe('createBulk() method', function () {
            it('creates bulk request successfully', function () {
                $bulkData = createZohoBulkData(['bulk_id' => 'bulk_123']);
                
                Http::fake([
                    '*bulk*test_report*' => Http::response(['result' => $bulkData])
                ]);
                
                $result = $this->service->createBulk('test_report', 'status="Active"');
                
                expect($result)->toBeArray();
                expect($result['bulk_id'])->toBe('bulk_123');
            });
        });

        describe('readBulk() method', function () {
            it('reads bulk status successfully', function () {
                $bulkData = createZohoBulkData([
                    'bulk_id' => 'bulk_123',
                    'status' => 'In Progress'
                ]);
                
                Http::fake([
                    '*bulk*test_report*bulk_123*' => Http::response(['result' => $bulkData])
                ]);
                
                $result = $this->service->readBulk('test_report', 'bulk_123');
                
                expect($result)->toBeArray();
                expect($result['status'])->toBe('In Progress');
            });
        });

        describe('downloadBulk() method', function () {
            it('downloads bulk file successfully', function () {
                Storage::fake('local');
                
                // Mock ZIP file response
                Http::fake([
                    '*download*' => Http::response('ZIP_CONTENT', 200, [
                        'Content-Type' => 'application/zip',
                        'Content-Disposition' => 'attachment; filename="bulk_data.zip"'
                    ])
                ]);
                
                $downloadUrl = 'https://test.zoho.com/download/bulk_123.zip';
                $result = $this->service->downloadBulk('test_report', 'bulk_123', $downloadUrl);
                
                expect($result)->toBeString();
                expect(Storage::disk('local')->exists($result))->toBeTrue();
            });
        });

        describe('getWithBulk() method', function () {
            it('queues bulk processing job', function () {
                Queue::fake();
                
                $this->service->getWithBulk('test_report', 'https://callback.test', 'criteria');
                
                Queue::assertPushed(\Agencedoit\ZohoConnector\Jobs\ZohoCreatorBulkProcess::class);
            });
        });
    });

    describe('Custom Functions', function () {
        describe('customFunctionGet() method', function () {
            it('calls custom GET function successfully', function () {
                Http::fake([
                    '*custom*test_function*' => Http::response(['result' => 'success'])
                ]);
                
                $result = $this->service->customFunctionGet('test_function', ['param' => 'value']);
                
                expect($result)->toBeArray();
                expect($result['result'])->toBe('success');
            });

            it('calls custom function with public key', function () {
                Http::fake(['*' => Http::response(['result' => 'success'])]);
                
                $result = $this->service->customFunctionGet('test_function', [], 'public_key_123');
                
                Http::assertSent(function ($request) {
                    return str_contains($request->url(), 'public_key_123');
                });
            });
        });

        describe('customFunctionPost() method', function () {
            it('calls custom POST function successfully', function () {
                Http::fake([
                    '*custom*test_function*' => Http::response(['result' => 'created'])
                ]);
                
                $data = ['name' => 'Test', 'value' => 123];
                $result = $this->service->customFunctionPost('test_function', $data);
                
                expect($result)->toBeArray();
                expect($result['result'])->toBe('created');
                
                Http::assertSent(function ($request) use ($data) {
                    $body = json_decode($request->body(), true);
                    return $body['name'] === $data['name'];
                });
            });
        });
    });

    describe('Metadata Operations', function () {
        describe('getFormsMeta() method', function () {
            it('retrieves forms metadata successfully', function () {
                Http::fake([
                    '*meta*forms*' => Http::response([
                        'forms' => [
                            ['form_name' => 'test_form', 'display_name' => 'Test Form']
                        ]
                    ])
                ]);
                
                $result = $this->service->getFormsMeta();
                
                expect($result)->toBeArray();
                expect($result['forms'])->toHaveCount(1);
                expect($result['forms'][0]['form_name'])->toBe('test_form');
            });
        });

        describe('getFieldsMeta() method', function () {
            it('retrieves fields metadata successfully', function () {
                Http::fake([
                    '*meta*test_form*fields*' => Http::response([
                        'fields' => [
                            ['field_name' => 'Name', 'data_type' => 'text']
                        ]
                    ])
                ]);
                
                $result = $this->service->getFieldsMeta('test_form');
                
                expect($result)->toBeArray();
                expect($result['fields'])->toHaveCount(1);
                expect($result['fields'][0]['field_name'])->toBe('Name');
            });
        });
    });

    describe('Error Handling', function () {
        it('handles rate limiting with retry', function () {
            Http::fake([
                '*' => Http::sequence()
                    ->push(mockZohoErrorResponse(4820, 'Rate limit exceeded'), 429)
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);
            
            $result = $this->service->get('test_report');
            
            expect($result)->toBeArray();
            Http::assertSentCount(2); // Initial request + retry
        });

        it('handles invalid token with refresh attempt', function () {
            Http::fake([
                '*' => Http::sequence()
                    ->push(mockZohoErrorResponse(6000, 'Invalid access token'), 401)
                    ->push(createZohoTokenData(), 200) // Token refresh
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);
            
            $result = $this->service->get('test_report');
            
            expect($result)->toBeArray();
        });

        it('respects timeout configuration', function () {
            config(['zohoconnector.request_timeout' => 1]);
            
            Http::fake([
                '*' => function () {
                    sleep(2); // Simulate slow response
                    return Http::response([]);
                }
            ]);
            
            expect(fn() => $this->service->get('slow_report'))
                ->toThrow(\Exception::class);
        });
    });
});
```

### 2. ZohoTokenManagementTest.php
```php
<?php

use Agencedoit\ZohoConnector\Helpers\ZohoTokenManagement;
use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Illuminate\Support\Facades\Http;

describe('ZohoTokenManagement', function () {
    beforeEach(function () {
        $this->tokenManager = new class extends ZohoTokenManagement {
            // Expose protected methods for testing
            public function testRequestToken(string $code): array {
                return $this->requestToken($code);
            }
            
            public function testRefreshToken(): array {
                return $this->refreshToken();
            }
            
            public function testIsTokenExpired(): bool {
                return $this->isTokenExpired();
            }
        };
    });

    describe('Token Request Flow', function () {
        it('requests initial token successfully', function () {
            $tokenData = createZohoTokenData();
            
            Http::fake([
                'accounts.zoho.*/oauth/v2/token' => Http::response($tokenData)
            ]);
            
            $result = $this->tokenManager->testRequestToken('auth_code_123');
            
            expect($result)->toBeArray();
            expect($result['access_token'])->toBe($tokenData['access_token']);
            expect($result['refresh_token'])->toBe($tokenData['refresh_token']);
            
            Http::assertSent(function ($request) {
                $body = $request->data();
                return $body['code'] === 'auth_code_123' &&
                       $body['grant_type'] === 'authorization_code';
            });
        });

        it('stores token in database after request', function () {
            $tokenData = createZohoTokenData();
            Http::fake(['*' => Http::response($tokenData)]);
            
            $this->tokenManager->testRequestToken('auth_code_123');
            
            $this->assertDatabaseHas('zoho_connector_tokens', [
                'token' => $tokenData['access_token']
            ]);
        });

        it('throws exception on invalid authorization code', function () {
            Http::fake([
                '*' => Http::response(['error' => 'invalid_code'], 400)
            ]);
            
            expect(fn() => $this->tokenManager->testRequestToken('invalid_code'))
                ->toThrow(\Exception::class);
        });
    });

    describe('Token Refresh Flow', function () {
        it('refreshes token successfully', function () {
            // Create existing token
            ZohoConnectorToken::create([
                'token' => 'old_access_token',
                'refresh_token' => 'refresh_token_123',
                'token_created_at' => now()->subHour(),
                'token_peremption_at' => now()->subMinute(),
                'token_duration' => 3600
            ]);
            
            $newTokenData = createZohoTokenData(['access_token' => 'new_access_token']);
            Http::fake(['*' => Http::response($newTokenData)]);
            
            $result = $this->tokenManager->testRefreshToken();
            
            expect($result)->toBeArray();
            expect($result['access_token'])->toBe('new_access_token');
            
            Http::assertSent(function ($request) {
                $body = $request->data();
                return $body['refresh_token'] === 'refresh_token_123' &&
                       $body['grant_type'] === 'refresh_token';
            });
        });

        it('updates database with new token', function () {
            ZohoConnectorToken::create([
                'token' => 'old_token',
                'refresh_token' => 'refresh_123',
                'token_created_at' => now()->subHour(),
                'token_peremption_at' => now()->subMinute(),
                'token_duration' => 3600
            ]);
            
            Http::fake(['*' => Http::response(createZohoTokenData(['access_token' => 'new_token']))]);
            
            $this->tokenManager->testRefreshToken();
            
            $this->assertDatabaseHas('zoho_connector_tokens', [
                'token' => 'new_token'
            ]);
            
            $this->assertDatabaseMissing('zoho_connector_tokens', [
                'token' => 'old_token'
            ]);
        });
    });

    describe('Token Validation', function () {
        it('detects expired tokens correctly', function () {
            ZohoConnectorToken::create([
                'token' => 'expired_token',
                'token_peremption_at' => now()->subMinute(),
                'token_duration' => 3600
            ]);
            
            expect($this->tokenManager->testIsTokenExpired())->toBeTrue();
        });

        it('detects valid tokens correctly', function () {
            ZohoConnectorToken::create([
                'token' => 'valid_token',
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            expect($this->tokenManager->testIsTokenExpired())->toBeFalse();
        });

        it('handles missing tokens gracefully', function () {
            // No tokens in database
            expect($this->tokenManager->testIsTokenExpired())->toBeTrue();
        });
    });

    describe('Token Cleanup', function () {
        it('removes old tokens when creating new ones', function () {
            // Create multiple old tokens
            ZohoConnectorToken::create(['token' => 'token1', 'token_created_at' => now()->subDays(2)]);
            ZohoConnectorToken::create(['token' => 'token2', 'token_created_at' => now()->subDays(1)]);
            
            Http::fake(['*' => Http::response(createZohoTokenData())]);
            
            $this->tokenManager->testRequestToken('new_code');
            
            // Should only have the new token
            expect(ZohoConnectorToken::count())->toBe(1);
            expect(ZohoConnectorToken::first()->token)->not->toBeIn(['token1', 'token2']);
        });
    });
});
```

### 3. ZohoServiceCheckerTest.php
```php
<?php

use Agencedoit\ZohoConnector\Traits\ZohoServiceChecker;
use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;

describe('ZohoServiceChecker Trait', function () {
    beforeEach(function () {
        $this->service = new class {
            use ZohoServiceChecker;
            
            public function testZohoServiceCheck(): void {
                $this->ZohoServiceCheck();
            }
            
            public function testZohoResponseCheck($response): void {
                $this->ZohoResponseCheck($response);
            }
        };
    });

    describe('ZohoServiceCheck() method', function () {
        it('passes when valid token exists', function () {
            ZohoConnectorToken::create([
                'token' => 'valid_token',
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            expect(fn() => $this->service->testZohoServiceCheck())
                ->not->toThrow(\Exception::class);
        });

        it('throws exception when no token exists', function () {
            expect(fn() => $this->service->testZohoServiceCheck())
                ->toThrow(\Exception::class, 'Zoho service not ready');
        });

        it('throws exception when token is expired', function () {
            ZohoConnectorToken::create([
                'token' => 'expired_token',
                'token_peremption_at' => now()->subMinute(),
                'token_duration' => 3600
            ]);
            
            expect(fn() => $this->service->testZohoServiceCheck())
                ->toThrow(\Exception::class);
        });
    });

    describe('ZohoResponseCheck() method', function () {
        it('passes for successful response', function () {
            $response = Http::response(mockZohoSuccessResponse(), 200);
            
            expect(fn() => $this->service->testZohoResponseCheck($response))
                ->not->toThrow(\Exception::class);
        });

        it('throws exception for error response', function () {
            $response = Http::response(mockZohoErrorResponse(500, 'Server Error'), 500);
            
            expect(fn() => $this->service->testZohoResponseCheck($response))
                ->toThrow(\Exception::class, 'Server Error');
        });

        it('throws exception for invalid JSON', function () {
            $response = Http::response('Invalid JSON', 200);
            
            expect(fn() => $this->service->testZohoResponseCheck($response))
                ->toThrow(\Exception::class);
        });
    });
});
```

---

## 🗃️ Tests des Models

### 1. ZohoConnectorTokenTest.php
```php
<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;

describe('ZohoConnectorToken Model', function () {
    it('can be created with valid data', function () {
        $tokenData = [
            'token' => 'test_access_token',
            'refresh_token' => 'test_refresh_token',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ];
        
        $token = ZohoConnectorToken::create($tokenData);
        
        expect($token)->toBeInstanceOf(ZohoConnectorToken::class);
        expect($token->token)->toBe($tokenData['token']);
        expect($token->refresh_token)->toBe($tokenData['refresh_token']);
    });

    it('uses configured table name', function () {
        config(['zohoconnector.tokens_table_name' => 'custom_tokens']);
        
        $token = new ZohoConnectorToken();
        
        expect($token->getTable())->toBe('custom_tokens');
    });

    it('has correct fillable attributes', function () {
        $token = new ZohoConnectorToken();
        
        expect($token->getFillable())->toContain('token');
        expect($token->getFillable())->toContain('refresh_token');
        expect($token->getFillable())->toContain('token_created_at');
        expect($token->getFillable())->toContain('token_peremption_at');
        expect($token->getFillable())->toContain('token_duration');
    });

    it('can check if token is expired', function () {
        $expiredToken = ZohoConnectorToken::create([
            'token' => 'expired',
            'token_peremption_at' => now()->subMinute()
        ]);
        
        $validToken = ZohoConnectorToken::create([
            'token' => 'valid',
            'token_peremption_at' => now()->addHour()
        ]);
        
        expect($expiredToken->token_peremption_at->isPast())->toBeTrue();
        expect($validToken->token_peremption_at->isFuture())->toBeTrue();
    });
});
```

### 2. ZohoBulkHistoryTest.php
```php
<?php

use Agencedoit\ZohoConnector\Models\ZohoBulkHistory;

describe('ZohoBulkHistory Model', function () {
    it('can be created with bulk operation data', function () {
        $bulkData = [
            'bulk_id' => 'bulk_123',
            'report_name' => 'test_report',
            'status' => 'Completed',
            'callback_url' => 'https://test.com/callback',
            'criteria' => 'status="Active"',
            'download_url' => 'https://download.com/file.zip',
            'json_location' => '/storage/bulk_123.json'
        ];
        
        $bulk = ZohoBulkHistory::create($bulkData);
        
        expect($bulk)->toBeInstanceOf(ZohoBulkHistory::class);
        expect($bulk->bulk_id)->toBe($bulkData['bulk_id']);
        expect($bulk->status)->toBe($bulkData['status']);
    });

    it('uses configured table name', function () {
        config(['zohoconnector.bulks_table_name' => 'custom_bulk_history']);
        
        $bulk = new ZohoBulkHistory();
        
        expect($bulk->getTable())->toBe('custom_bulk_history');
    });

    it('can find by bulk_id', function () {
        ZohoBulkHistory::create([
            'bulk_id' => 'unique_bulk_123',
            'report_name' => 'test_report',
            'status' => 'In Progress'
        ]);
        
        $found = ZohoBulkHistory::where('bulk_id', 'unique_bulk_123')->first();
        
        expect($found)->not->toBeNull();
        expect($found->bulk_id)->toBe('unique_bulk_123');
    });
});
```

---

## ⚙️ Tests des Jobs

### ZohoCreatorBulkProcessTest.php
```php
<?php

use Agencedoit\ZohoConnector\Jobs\ZohoCreatorBulkProcess;
use Agencedoit\ZohoConnector\Models\ZohoBulkHistory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

describe('ZohoCreatorBulkProcess Job', function () {
    beforeEach(function () {
        Storage::fake('local');
        Queue::fake();
    });

    it('processes bulk operation successfully', function () {
        // Mock bulk creation and completion
        Http::fake([
            '*bulk*test_report*' => Http::sequence()
                ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_123'])], 200)
                ->push(['result' => createZohoBulkData([
                    'bulk_id' => 'bulk_123',
                    'status' => 'Completed',
                    'download_url' => 'https://test.zip'
                ])], 200),
            'https://test.zip' => Http::response('ZIP_CONTENT', 200, [
                'Content-Type' => 'application/zip'
            ]),
            'https://callback.test' => Http::response(['success' => true])
        ]);
        
        $job = new ZohoCreatorBulkProcess('test_report', 'https://callback.test', '');
        $job->handle();
        
        // Verify bulk history was created
        $this->assertDatabaseHas('zoho_connector_bulk_history', [
            'report_name' => 'test_report',
            'status' => 'Completed'
        ]);
        
        // Verify callback was called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'callback.test');
        });
    });

    it('handles bulk processing errors gracefully', function () {
        Http::fake([
            '*bulk*' => Http::response(['error' => 'Bulk failed'], 500)
        ]);
        
        $job = new ZohoCreatorBulkProcess('error_report', 'https://callback.test', '');
        
        expect(fn() => $job->handle())->toThrow(\Exception::class);
        
        // Verify error was logged in bulk history
        $this->assertDatabaseHas('zoho_connector_bulk_history', [
            'report_name' => 'error_report',
            'status' => 'Failed'
        ]);
    });

    it('extracts and processes ZIP files correctly', function () {
        // Create mock ZIP content
        $zipContent = $this->createMockZipWithCSV();
        
        Http::fake([
            '*bulk*' => Http::sequence()
                ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_123'])], 200)
                ->push(['result' => createZohoBulkData([
                    'status' => 'Completed',
                    'download_url' => 'https://test.zip'
                ])], 200),
            'https://test.zip' => Http::response($zipContent, 200, [
                'Content-Type' => 'application/zip'
            ]),
            'https://callback.test' => Http::response(['success' => true])
        ]);
        
        $job = new ZohoCreatorBulkProcess('test_report', 'https://callback.test', '');
        $job->handle();
        
        // Verify JSON file was created
        $files = Storage::disk('local')->files();
        $jsonFiles = array_filter($files, fn($file) => str_ends_with($file, '.json'));
        expect($jsonFiles)->not->toBeEmpty();
    });

    private function createMockZipWithCSV(): string
    {
        // Create a simple ZIP file with CSV content for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test_zip');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE);
        $zip->addFromString('data.csv', "ID,Name,Status\n123,Test Record,Active\n124,Another Record,Inactive");
        $zip->close();
        
        return file_get_contents($tempFile);
    }
});
```

---

## 🎭 Tests des Facades

### ZohoCreatorFacadeTest.php
```php
<?php

use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade;
use Agencedoit\ZohoConnector\Services\ZohoCreatorService;

describe('ZohoCreatorFacade', function () {
    it('resolves to ZohoCreatorService', function () {
        $service = ZohoCreatorFacade::getFacadeRoot();
        
        expect($service)->toBeInstanceOf(ZohoCreatorService::class);
    });

    it('calls methods on the underlying service', function () {
        $testData = [createZohoReportData()];
        $this->mockZohoResponse('test_report', $testData);
        
        $result = ZohoCreatorFacade::get('test_report');
        
        expect($result)->toBeArray();
        expect($result)->toHaveCount(1);
    });

    it('maintains singleton behavior', function () {
        $service1 = ZohoCreatorFacade::getFacadeRoot();
        $service2 = ZohoCreatorFacade::getFacadeRoot();
        
        expect($service1)->toBe($service2);
    });

    it('can check service readiness', function () {
        $ready = ZohoCreatorFacade::isReady();
        
        expect($ready)->toBeBool();
    });
});
```

---

## ✅ Checklist Phase 2

### Services Core
- [ ] **ZohoCreatorService**
  - [ ] Tests CRUD (get, getAll, getByID, create, update, upload)
  - [ ] Tests bulk operations (createBulk, readBulk, downloadBulk, getWithBulk)
  - [ ] Tests custom functions (customFunctionGet, customFunctionPost)
  - [ ] Tests metadata (getFormsMeta, getFieldsMeta, getReportsMeta)
  - [ ] Tests error handling et retry logic

- [ ] **ZohoTokenManagement**
  - [ ] Tests token request flow
  - [ ] Tests token refresh flow
  - [ ] Tests token validation
  - [ ] Tests token cleanup

- [ ] **ZohoServiceChecker**
  - [ ] Tests ZohoServiceCheck()
  - [ ] Tests ZohoResponseCheck()
  - [ ] Tests error conditions

### Models
- [ ] **ZohoConnectorToken**
  - [ ] Tests création/lecture
  - [ ] Tests configuration table
  - [ ] Tests fillable attributes

- [ ] **ZohoBulkHistory**
  - [ ] Tests opérations bulk
  - [ ] Tests recherche par bulk_id
  - [ ] Tests statuts

### Jobs & Facades
- [ ] **ZohoCreatorBulkProcess**
  - [ ] Tests processing complet
  - [ ] Tests error handling
  - [ ] Tests file operations

- [ ] **ZohoCreatorFacade**
  - [ ] Tests résolution service
  - [ ] Tests singleton behavior
  - [ ] Tests méthodes proxy

**Coverage target** : 85%+ sur tous les composants core

**Commit final Phase 2** : `test: comprehensive unit tests for all core services and models`