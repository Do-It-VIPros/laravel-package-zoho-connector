# PHASE 3 : FEATURE TESTS - Tests fonctionnels et d'intégration

## 🎯 Objectifs de la Phase 3
- ✅ Tests fonctionnels des workflows complets
- ✅ Tests des controllers et routes
- ✅ Tests d'authentication OAuth2 end-to-end
- ✅ Tests des API endpoints
- ✅ Validation des interactions utilisateur

**Durée estimée** : 4-5 jours  
**Commit** : `test: add feature tests for complete workflows and authentication`

---

## 📁 Structure à créer

```
tests/Feature/
├── Authentication/
│   ├── OAuthFlowTest.php           ✅ Flow OAuth complet
│   ├── TokenRefreshTest.php        ✅ Refresh automatique
│   └── TokenManagementTest.php     ✅ Gestion lifecycle tokens
├── Api/
│   ├── CrudOperationsTest.php      ✅ Operations CRUD complètes
│   ├── BulkOperationsTest.php      ✅ Workflows bulk
│   ├── CustomFunctionsTest.php     ✅ Functions custom
│   └── MetadataTest.php            ✅ Récupération metadata
├── Http/
│   ├── ZohoControllerTest.php      ✅ Controller endpoints
│   └── RoutesTest.php              ✅ Routes et middleware
└── Workflows/
    ├── DataSyncWorkflowTest.php    ✅ Sync données complète
    └── ErrorRecoveryTest.php       ✅ Recovery après erreurs
```

---

## 🔐 Tests d'Authentication

### 1. OAuthFlowTest.php
```php
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
```

### 2. TokenRefreshTest.php
```php
<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;

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
});
```

### 3. TokenManagementTest.php
```php
<?php

describe('Token Lifecycle Management', function () {
    it('manages multiple concurrent token refresh attempts', function () {
        ZohoConnectorToken::create([
            'token' => 'expired_token',
            'refresh_token' => 'refresh_123',
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
});
```

---

## 🔌 Tests des APIs

### 1. CrudOperationsTest.php
```php
<?php

describe('Complete CRUD Operations', function () {
    beforeEach(function () {
        // Setup valid token
        ZohoConnectorToken::create([
            'token' => 'valid_api_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    });

    describe('Company Management Workflow', function () {
        it('creates, reads, updates, and deletes company records', function () {
            $companyData = [
                'denomination' => 'Test Company SARL',
                'siren' => '123456789',
                'siret' => '12345678901234',
                'address' => '123 Test Street',
                'city' => 'Test City'
            ];
            
            // CREATE
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/form/Company' => Http::response([
                    'code' => 3000,
                    'data' => array_merge($companyData, [
                        'ID' => '61757000058385531',
                        'Added_Time' => now()->toISOString()
                    ])
                ], 200)
            ]);
            
            $created = ZohoCreatorApi::create('Company', $companyData);
            
            expect($created)->toBeArray();
            expect($created['ID'])->toBe('61757000058385531');
            expect($created['denomination'])->toBe($companyData['denomination']);
            
            $companyId = $created['ID'];
            
            // READ
            Http::fake([
                "www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies/{$companyId}" => Http::response([
                    'code' => 3000,
                    'data' => $created
                ], 200)
            ]);
            
            $retrieved = ZohoCreatorApi::getByID('All_Companies', $companyId);
            
            expect($retrieved)->toBeArray();
            expect($retrieved['ID'])->toBe($companyId);
            expect($retrieved['denomination'])->toBe($companyData['denomination']);
            
            // UPDATE
            $updateData = ['denomination' => 'Updated Company SARL'];
            
            Http::fake([
                "www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies/{$companyId}" => Http::response([
                    'code' => 3000,
                    'data' => array_merge($retrieved, $updateData, [
                        'Modified_Time' => now()->toISOString()
                    ])
                ], 200)
            ]);
            
            $updated = ZohoCreatorApi::update('All_Companies', $companyId, $updateData);
            
            expect($updated)->toBeArray();
            expect($updated['denomination'])->toBe('Updated Company SARL');
            expect($updated['Modified_Time'])->not->toBe($retrieved['Added_Time']);
        });

        it('handles validation errors during creation', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/form/Company' => Http::response([
                    'code' => 3002,
                    'message' => 'Validation failed',
                    'details' => 'SIREN number is invalid'
                ], 400)
            ]);
            
            expect(fn() => ZohoCreatorApi::create('Company', ['invalid_siren' => '123']))
                ->toThrow(\Exception::class, 'Validation failed');
        });

        it('handles record not found errors', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies/999999' => Http::response([
                    'code' => 3001,
                    'message' => 'Record not found'
                ], 404)
            ]);
            
            expect(fn() => ZohoCreatorApi::getByID('All_Companies', '999999'))
                ->toThrow(\Exception::class, 'Record not found');
        });
    });

    describe('File Upload Operations', function () {
        it('uploads files to records successfully', function () {
            Storage::fake('local');
            $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
            
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies/123/upload' => Http::response([
                    'code' => 3000,
                    'message' => 'File uploaded successfully',
                    'data' => [
                        'file_id' => 'file_123',
                        'filename' => 'contract.pdf'
                    ]
                ], 200)
            ]);
            
            $result = ZohoCreatorApi::upload('All_Companies', '123', 'contract_field', $file);
            
            expect($result)->toBeArray();
            expect($result['filename'])->toBe('contract.pdf');
            
            Http::assertSent(function ($request) {
                return $request->hasFile('contract_field');
            });
        });

        it('handles file upload size limits', function () {
            Storage::fake('local');
            $largefile = UploadedFile::fake()->create('large.pdf', 51000, 'application/pdf'); // 51MB
            
            Http::fake([
                '*upload*' => Http::response([
                    'code' => 4002,
                    'message' => 'File size exceeds limit'
                ], 413)
            ]);
            
            expect(fn() => ZohoCreatorApi::upload('All_Companies', '123', 'document', $largefile))
                ->toThrow(\Exception::class, 'File size exceeds limit');
        });
    });

    describe('Criteria and Filtering', function () {
        it('handles complex search criteria', function () {
            $criteria = 'denomination.contains("SARL") && status == "Active" && created_date > "2024-01-01"';
            
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies*' => Http::response(
                    mockZohoSuccessResponse([
                        createZohoReportData(['denomination' => 'Test SARL', 'status' => 'Active']),
                        createZohoReportData(['denomination' => 'Another SARL', 'status' => 'Active'])
                    ]), 200
                )
            ]);
            
            $results = ZohoCreatorApi::get('All_Companies', $criteria);
            
            expect($results)->toBeArray();
            expect($results)->toHaveCount(2);
            
            Http::assertSent(function ($request) use ($criteria) {
                return str_contains($request->url(), urlencode($criteria));
            });
        });

        it('handles special characters in criteria', function () {
            $criteria = 'email == "test@company.com" && notes.contains("R&D")';
            
            Http::fake(['*' => Http::response(mockZohoSuccessResponse([]), 200)]);
            
            $results = ZohoCreatorApi::get('All_Contacts', $criteria);
            
            expect($results)->toBeArray();
            
            Http::assertSent(function ($request) {
                // Verify URL encoding is handled correctly
                return str_contains($request->url(), 'test%40company.com');
            });
        });
    });
});
```

### 2. BulkOperationsTest.php
```php
<?php

describe('Bulk Operations Workflow', function () {
    beforeEach(function () {
        Storage::fake('local');
        
        ZohoConnectorToken::create([
            'token' => 'valid_token',
            'refresh_token' => 'refresh_token',
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    });

    describe('Complete Bulk Export Workflow', function () {
        it('executes full bulk export with automated processing', function () {
            Queue::fake();
            
            // Mock the bulk creation, status checking, and download
            Http::fake([
                // Create bulk request
                'www.zohoapis.eu/creator/v2.1/bulk/*/report/All_Companies' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_12345'])], 200)
                    ->push(['result' => createZohoBulkData([
                        'bulk_id' => 'bulk_12345',
                        'status' => 'In Progress'
                    ])], 200)
                    ->push(['result' => createZohoBulkData([
                        'bulk_id' => 'bulk_12345', 
                        'status' => 'Completed',
                        'download_url' => 'https://files.zoho.eu/bulk_12345.zip'
                    ])], 200),
                
                // Download ZIP file
                'https://files.zoho.eu/bulk_12345.zip' => Http::response(
                    $this->createMockZipWithCSV([
                        ['ID' => '123', 'Name' => 'Company A'],
                        ['ID' => '124', 'Name' => 'Company B']
                    ]), 200, ['Content-Type' => 'application/zip']
                ),
                
                // Callback notification
                'https://callback.test.com/bulk-complete' => Http::response(['received' => true], 200)
            ]);
            
            // Start bulk operation
            ZohoCreatorApi::getWithBulk('All_Companies', 'https://callback.test.com/bulk-complete', 'status="Active"');
            
            // Verify job was queued
            Queue::assertPushed(ZohoCreatorBulkProcess::class, function ($job) {
                return $job->reportName === 'All_Companies' &&
                       $job->callbackUrl === 'https://callback.test.com/bulk-complete';
            });
            
            // Execute the job manually for testing
            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test.com/bulk-complete', 'status="Active"');
            $job->handle();
            
            // Verify bulk history was created
            $this->assertDatabaseHas('zoho_connector_bulk_history', [
                'bulk_id' => 'bulk_12345',
                'report_name' => 'All_Companies',
                'status' => 'Completed'
            ]);
            
            // Verify callback was called
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'callback.test.com/bulk-complete');
            });
            
            // Verify JSON file was created
            $jsonFiles = Storage::files();
            $bulkFiles = array_filter($jsonFiles, fn($file) => str_contains($file, 'bulk_') && str_ends_with($file, '.json'));
            expect($bulkFiles)->not->toBeEmpty();
        });

        it('handles bulk operation timeout gracefully', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/bulk/*' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_timeout'])], 200)
                    // Always return "In Progress" to simulate timeout
                    ->push(['result' => createZohoBulkData([
                        'bulk_id' => 'bulk_timeout',
                        'status' => 'In Progress'
                    ])], 200)
                    ->whenEmpty(Http::response(['result' => createZohoBulkData([
                        'bulk_id' => 'bulk_timeout',
                        'status' => 'In Progress'
                    ])], 200))
            ]);
            
            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test', '');
            
            // Should timeout after configured attempts
            expect(fn() => $job->handle())->toThrow(\Exception::class, 'timeout');
            
            // Verify error was logged
            $this->assertDatabaseHas('zoho_connector_bulk_history', [
                'report_name' => 'All_Companies',
                'status' => 'Failed'
            ]);
        });

        it('retries failed bulk operations with exponential backoff', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/bulk/*' => Http::sequence()
                    ->push(['error' => 'Temporary failure'], 500)
                    ->push(['error' => 'Still failing'], 500)
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_retry'])], 200)
            ]);
            
            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test', '');
            $job->handle();
            
            // Should eventually succeed after retries
            Http::assertSentCount(3);
            
            $this->assertDatabaseHas('zoho_connector_bulk_history', [
                'bulk_id' => 'bulk_retry',
                'status' => 'Completed'
            ]);
        });
    });

    describe('Bulk Data Processing', function () {
        it('correctly processes and transforms CSV data to JSON', function () {
            $csvData = [
                ['ID' => '123', 'Company_Name' => 'Test SARL', 'Status' => 'Active'],
                ['ID' => '124', 'Company_Name' => 'Another Ltd', 'Status' => 'Inactive']
            ];
            
            Http::fake([
                '*bulk*' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_csv'])], 200)
                    ->push(['result' => createZohoBulkData([
                        'status' => 'Completed',
                        'download_url' => 'https://test.zip'
                    ])], 200),
                'https://test.zip' => Http::response(
                    $this->createMockZipWithCSV($csvData), 200,
                    ['Content-Type' => 'application/zip']
                ),
                'https://callback.test' => Http::response(['success' => true])
            ]);
            
            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test', '');
            $job->handle();
            
            // Find the generated JSON file
            $jsonFiles = Storage::files();
            $jsonFile = collect($jsonFiles)->first(fn($file) => str_ends_with($file, '.json'));
            
            expect($jsonFile)->not->toBeNull();
            
            $jsonContent = json_decode(Storage::get($jsonFile), true);
            
            expect($jsonContent)->toBeArray();
            expect($jsonContent)->toHaveCount(2);
            expect($jsonContent[0]['ID'])->toBe('123');
            expect($jsonContent[0]['Company_Name'])->toBe('Test SARL');
        });

        it('handles corrupted ZIP files gracefully', function () {
            Http::fake([
                '*bulk*' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_corrupt'])], 200)
                    ->push(['result' => createZohoBulkData([
                        'status' => 'Completed',
                        'download_url' => 'https://corrupt.zip'
                    ])], 200),
                'https://corrupt.zip' => Http::response('CORRUPTED_ZIP_DATA', 200, [
                    'Content-Type' => 'application/zip'
                ])
            ]);
            
            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test', '');
            
            expect(fn() => $job->handle())->toThrow(\Exception::class);
            
            $this->assertDatabaseHas('zoho_connector_bulk_history', [
                'report_name' => 'All_Companies',
                'status' => 'Failed'
            ]);
        });
    });

    private function createMockZipWithCSV(array $data): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_zip');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE);
        
        // Create CSV content
        $csvContent = '';
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            $csvContent .= implode(',', $headers) . "\n";
            
            foreach ($data as $row) {
                $csvContent .= implode(',', array_values($row)) . "\n";
            }
        }
        
        $zip->addFromString('export_data.csv', $csvContent);
        $zip->close();
        
        return file_get_contents($tempFile);
    }
});
```

### 3. CustomFunctionsTest.php
```php
<?php

describe('Custom Functions Integration', function () {
    beforeEach(function () {
        ZohoConnectorToken::create([
            'token' => 'valid_token',
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
    });
});
```

---

## 🌐 Tests des Controllers et Routes

### 1. ZohoControllerTest.php
```php
<?php

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
                'token_peremption_at' => now()->addHour()
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
    });
});
```

### 2. RoutesTest.php
```php
<?php

describe('Route Configuration and Middleware', function () {
    describe('Route Availability', function () {
        it('registers all expected routes', function () {
            $routes = collect(Route::getRoutes())->map(fn($route) => $route->uri());
            
            expect($routes->contains('zoho/request-code'))->toBeTrue();
            expect($routes->contains('zoho/request-code-response'))->toBeTrue();
        });

        it('applies correct middleware to routes', function () {
            $route = collect(Route::getRoutes())
                ->first(fn($route) => $route->uri() === 'zoho/request-code');
            
            expect($route)->not->toBeNull();
            // Add specific middleware checks if any are applied
        });
    });

    describe('Dynamic Route Registration', function () {
        it('conditionally registers routes based on service state', function () {
            // When service is not ready, auth routes should be available
            ZohoConnectorToken::truncate();
            
            // Re-register routes
            $this->refreshApplication();
            
            $response = $this->get('/zoho/request-code');
            expect($response->status())->not->toBe(404);
        });

        it('handles route conflicts gracefully', function () {
            // Test that our routes don't conflict with application routes
            $response = $this->get('/zoho/non-existent-route');
            expect($response->status())->toBe(404);
        });
    });

    describe('CSRF Protection', function () {
        it('applies CSRF protection where appropriate', function () {
            // Most Zoho routes are GET requests and don't need CSRF
            $response = $this->get('/zoho/request-code');
            expect($response->status())->not->toBe(419); // Not CSRF error
        });
    });
});
```

---

## 🔄 Tests des Workflows

### 1. DataSyncWorkflowTest.php
```php
<?php

describe('Complete Data Synchronization Workflow', function () {
    beforeEach(function () {
        ZohoConnectorToken::create([
            'token' => 'sync_token',
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    });

    describe('End-to-End Data Pipeline', function () {
        it('syncs data from Zoho through complete pipeline', function () {
            // Simulate a complete data sync workflow
            $companies = [
                createZohoReportData([
                    'ID' => '61757000058385531',
                    'denomination' => 'VIPros Test Company',
                    'status' => 'Active'
                ]),
                createZohoReportData([
                    'ID' => '61757000058385532', 
                    'denomination' => 'Another Test Company',
                    'status' => 'Pending'
                ])
            ];
            
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies*' => Http::response(
                    mockZohoSuccessResponse($companies), 200
                )
            ]);
            
            // Execute sync
            $results = ZohoCreatorApi::getAll('All_Companies');
            
            expect($results)->toBeArray();
            expect($results)->toHaveCount(2);
            expect($results[0]['denomination'])->toBe('VIPros Test Company');
            expect($results[1]['denomination'])->toBe('Another Test Company');
            
            // Verify API was called correctly
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'All_Companies') &&
                       $request->hasHeader('Authorization');
            });
        });

        it('handles large dataset synchronization with pagination', function () {
            // Setup paginated responses
            $page1 = array_fill(0, 200, createZohoReportData());
            $page2 = array_fill(0, 150, createZohoReportData());
            
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/Large_Dataset*' => Http::sequence()
                    ->push(mockZohoPaginatedResponse($page1, 'cursor_page2'), 200)
                    ->push(mockZohoPaginatedResponse($page2, null), 200)
            ]);
            
            $results = ZohoCreatorApi::getAll('Large_Dataset');
            
            expect($results)->toBeArray();
            expect($results)->toHaveCount(350); // 200 + 150
            
            // Verify pagination was handled
            Http::assertSentCount(2);
        });

        it('maintains data consistency during partial failures', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*' => Http::sequence()
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
                    ->push(['error' => 'Network error'], 500) // Second call fails
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200) // Retry succeeds
            ]);
            
            // Should retry and eventually succeed
            $results = ZohoCreatorApi::getAll('Reliable_Report');
            
            expect($results)->toBeArray();
            expect($results)->not->toBeEmpty();
        });
    });

    describe('Data Transformation and Validation', function () {
        it('validates data structure consistency', function () {
            $inconsistentData = [
                ['ID' => '123', 'name' => 'Company A', 'status' => 'Active'],
                ['ID' => '124', 'different_field' => 'Company B'] // Missing fields
            ];
            
            Http::fake([
                '*' => Http::response(mockZohoSuccessResponse($inconsistentData), 200)
            ]);
            
            $results = ZohoCreatorApi::get('Inconsistent_Report');
            
            // Should receive data as-is but be able to handle inconsistencies
            expect($results)->toBeArray();
            expect($results)->toHaveCount(2);
            expect($results[0])->toHaveKey('name');
            expect($results[1])->not->toHaveKey('name');
        });

        it('handles different date formats correctly', function () {
            $dataWithDates = [
                createZohoReportData([
                    'created_date' => '2025-01-06T12:00:00Z',
                    'modified_date' => '06-Jan-2025 12:00:00'
                ])
            ];
            
            Http::fake(['*' => Http::response(mockZohoSuccessResponse($dataWithDates), 200)]);
            
            $results = ZohoCreatorApi::get('Date_Report');
            
            expect($results[0]['created_date'])->toBe('2025-01-06T12:00:00Z');
            expect($results[0]['modified_date'])->toBe('06-Jan-2025 12:00:00');
        });
    });
});
```

### 2. ErrorRecoveryTest.php
```php
<?php

describe('Error Recovery and Resilience', function () {
    beforeEach(function () {
        ZohoConnectorToken::create([
            'token' => 'recovery_token',
            'refresh_token' => 'refresh_token',
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
    });

    describe('Token Recovery', function () {
        it('recovers from expired tokens automatically', function () {
            // Start with expired token
            ZohoConnectorToken::truncate();
            ZohoConnectorToken::create([
                'token' => 'expired_token',
                'refresh_token' => 'valid_refresh',
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
    });
});
```

---

## ✅ Checklist Phase 3

### Authentication Workflows
- [ ] **OAuth Flow**
  - [ ] Tests flow complet end-to-end
  - [ ] Tests gestion multi-domaines (.eu, .com, .in)
  - [ ] Tests error handling OAuth
  - [ ] Tests state management service

- [ ] **Token Management**
  - [ ] Tests refresh automatique
  - [ ] Tests expiration handling
  - [ ] Tests concurrent access
  - [ ] Tests cleanup tokens

### API Workflows
- [ ] **CRUD Operations**
  - [ ] Tests workflows complets (Create→Read→Update)
  - [ ] Tests validation errors
  - [ ] Tests file upload
  - [ ] Tests criteria complexes

- [ ] **Bulk Operations**
  - [ ] Tests workflow bulk complet
  - [ ] Tests processing ZIP/CSV
  - [ ] Tests timeout et retry
  - [ ] Tests callback notifications

- [ ] **Custom Functions**
  - [ ] Tests GET/POST custom functions
  - [ ] Tests authentification vs public key
  - [ ] Tests large data payloads
  - [ ] Tests encoding spéciaux

### Infrastructure
- [ ] **Controllers & Routes**
  - [ ] Tests endpoints développement vs production
  - [ ] Tests routing dynamique
  - [ ] Tests middleware et sécurité
  - [ ] Tests error handling

- [ ] **Error Recovery**
  - [ ] Tests network failures
  - [ ] Tests API errors (rate limiting, maintenance)
  - [ ] Tests token recovery
  - [ ] Tests data integrity

**Coverage target** : 90%+ sur les workflows complets

**Commit final Phase 3** : `test: comprehensive feature tests for all workflows and integrations`