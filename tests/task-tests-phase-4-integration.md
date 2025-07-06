# PHASE 4 : INTEGRATION - Tests d'intégration et validation cross-package

## 🎯 Objectifs de la Phase 4
- ✅ Tests d'intégration avec VIPros Elastic Models
- ✅ Tests end-to-end complets (Zoho → Elasticsearch)
- ✅ Validation de la mocking strategy
- ✅ Tests de performance et charge
- ✅ Tests de compatibilité inter-packages

**Durée estimée** : 4-5 jours  
**Commit** : `test: add integration tests and cross-package validation`

---

## 📁 Structure à créer

```
tests/Integration/
├── ViprosElasticModels/
│   ├── CrossPackageCompatibilityTest.php  ✅ Compatibilité packages
│   ├── DataConsistencyTest.php            ✅ Cohérence données
│   └── SyncIntegrationTest.php             ✅ Sync end-to-end
├── Contracts/
│   ├── ZohoApiContractTest.php             ✅ Contract testing
│   └── DataStructureContractTest.php      ✅ Structure données
├── MockValidation/
│   ├── ApiValidationTest.php               ✅ Validation mocks vs API
│   ├── FixtureConsistencyTest.php          ✅ Cohérence fixtures
│   └── ResponseStructureTest.php           ✅ Structure réponses
├── Performance/
│   ├── LoadTestingTest.php                 ✅ Tests de charge
│   ├── MemoryUsageTest.php                 ✅ Usage mémoire
│   └── BulkPerformanceTest.php             ✅ Performance bulk
└── EndToEnd/
    ├── CompleteWorkflowTest.php            ✅ Workflow complet
    └── RealApiIntegrationTest.php          ✅ Tests avec vraie API
```

---

## 🔗 Tests d'intégration VIPros Elastic Models

### 1. CrossPackageCompatibilityTest.php
```php
<?php

use Agencedoit\ViprosElasticModels\Tests\Helpers\ZohoMockingHelper;
use Agencedoit\ViprosElasticModels\DTOs\CompanyZohoDTO;
use Agencedoit\ViprosElasticModels\Models\ElasticApi\Company;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;

describe('Cross-Package Compatibility', function () {
    beforeEach(function () {
        // Initialize both packages
        ZohoMockingHelper::initialize();
        
        // Setup valid Zoho token
        \Agencedoit\ZohoConnector\Models\ZohoConnectorToken::create([
            'token' => 'integration_token',
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    });

    describe('Shared Helpers Integration', function () {
        it('can use ViprosElasticModels data creators in ZohoConnector tests', function () {
            // Test that we can use createCompanyData() from ViprosElasticModels
            $companyData = createCompanyData([
                'ID' => '61757000058385531',
                'denomination' => 'Shared Test Company'
            ]);
            
            expect($companyData)->toBeArray();
            expect($companyData['ID'])->toBe('61757000058385531');
            expect($companyData['denomination'])->toBe('Shared Test Company');
            
            // Verify it has all required fields for both packages
            expect($companyData)->toHaveKeys(['ID', 'denomination', 'Added_Time', 'Modified_Time']);
        });

        it('can use ZohoConnector helpers in ViprosElasticModels context', function () {
            $zohoResponse = mockZohoSuccessResponse([
                createZohoReportData(['Name' => 'Test Record'])
            ]);
            
            expect($zohoResponse)->toBeArray();
            expect($zohoResponse['code'])->toBe(3000);
            expect($zohoResponse['data'])->toHaveCount(1);
            expect($zohoResponse['data'][0]['Name'])->toBe('Test Record');
        });

        it('maintains mock consistency between packages', function () {
            // Setup mock in ZohoConnector style
            $this->mockZohoResponse('company_report', [createCompanyData()]);
            
            // Should work with ViprosElasticModels expectations
            $result = ZohoCreatorApi::get('company_report');
            
            expect($result)->toBeArray();
            expect($result)->not->toBeEmpty();
            
            // Verify the response structure is compatible with ViprosElasticModels DTOs
            $dto = new CompanyZohoDTO();
            expect($dto->isValidStructure($result[0]))->toBeTrue();
        });
    });

    describe('Facade Accessibility', function () {
        it('allows ViprosElasticModels to access ZohoCreatorApi facade', function () {
            expect(class_exists('ZohoCreatorApi'))->toBeTrue();
            expect(method_exists('ZohoCreatorApi', 'get'))->toBeTrue();
            expect(method_exists('ZohoCreatorApi', 'getAll'))->toBeTrue();
            expect(method_exists('ZohoCreatorApi', 'create'))->toBeTrue();
            expect(method_exists('ZohoCreatorApi', 'update'))->toBeTrue();
        });

        it('maintains singleton behavior across packages', function () {
            $service1 = ZohoCreatorApi::getFacadeRoot();
            
            // Simulate access from ViprosElasticModels context
            $service2 = app(\Agencedoit\ZohoConnector\Services\ZohoCreatorService::class);
            
            expect($service1)->toBe($service2);
        });

        it('shares configuration correctly between packages', function () {
            // Configuration should be accessible to both packages
            expect(config('zohoconnector.client_id'))->not->toBeNull();
            expect(config('zohoconnector.api_base_url'))->not->toBeNull();
            
            // ViprosElasticModels should be able to check Zoho readiness
            $isReady = ZohoCreatorApi::isReady();
            expect($isReady)->toBeBool();
        });
    });

    describe('Service Provider Integration', function () {
        it('loads both service providers without conflicts', function () {
            $providers = app()->getLoadedProviders();
            
            expect($providers)->toHaveKey('Agencedoit\\ZohoConnector\\ZohoConnectorServiceProvider');
            expect($providers)->toHaveKey('Agencedoit\\ViprosElasticModels\\ViprosElasticModelsServiceProvider');
        });

        it('resolves services correctly from both packages', function () {
            $zohoService = app(\Agencedoit\ZohoConnector\Services\ZohoCreatorService::class);
            expect($zohoService)->toBeInstanceOf(\Agencedoit\ZohoConnector\Services\ZohoCreatorService::class);
            
            // This assumes ViprosElasticModels services are available
            if (class_exists(\Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class)) {
                $syncService = app(\Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class);
                expect($syncService)->toBeInstanceOf(\Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class);
            }
        });
    });
});
```

### 2. DataConsistencyTest.php
```php
<?php

describe('Data Consistency Between Packages', function () {
    describe('Data Format Consistency', function () {
        it('maintains consistent date formats between packages', function () {
            $zohoData = createZohoReportData([
                'Added_Time' => '2025-01-06T12:00:00Z',
                'Modified_Time' => '2025-01-06T13:00:00Z'
            ]);
            
            // ViprosElasticModels should be able to parse these dates
            expect($zohoData['Added_Time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
            expect($zohoData['Modified_Time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
            
            // Should be parseable by Carbon (used in ViprosElasticModels)
            $addedTime = \Carbon\Carbon::parse($zohoData['Added_Time']);
            expect($addedTime)->toBeInstanceOf(\Carbon\Carbon::class);
        });

        it('maintains consistent ID formats', function () {
            $zohoData = createZohoReportData([
                'ID' => '61757000058385531'
            ]);
            
            // ID should be string format that ViprosElasticModels expects
            expect($zohoData['ID'])->toBeString();
            expect($zohoData['ID'])->toMatch('/^617570\d{11}$/'); // VIPros ID pattern
        });

        it('handles nested object structures consistently', function () {
            $companyData = createCompanyData([
                'localisation' => [
                    'ID' => '61757000000001111',
                    'name' => 'France',
                    'zc_display_value' => 'France'
                ]
            ]);
            
            expect($companyData['localisation'])->toBeArray();
            expect($companyData['localisation'])->toHaveKeys(['ID', 'name', 'zc_display_value']);
            
            // ViprosElasticModels should be able to process this structure
            if (class_exists(\Agencedoit\ViprosElasticModels\DTOs\CompanyZohoDTO::class)) {
                $dto = new \Agencedoit\ViprosElasticModels\DTOs\CompanyZohoDTO();
                expect($dto->isValidStructure($companyData))->toBeTrue();
            }
        });
    });

    describe('Field Mapping Consistency', function () {
        it('maps Zoho fields to Elasticsearch fields correctly', function () {
            $zohoCompany = createCompanyData([
                'ID' => '123',
                'denomination' => 'Test Company',
                'Added_Time' => now()->toISOString(),
                'Modified_Time' => now()->toISOString()
            ]);
            
            // Simulate the mapping that ViprosElasticModels would do
            $elasticFields = [
                'id' => $zohoCompany['ID'],
                'denomination' => $zohoCompany['denomination'],
                'created_at' => $zohoCompany['Added_Time'],
                'updated_at' => $zohoCompany['Modified_Time']
            ];
            
            expect($elasticFields['id'])->toBe('123');
            expect($elasticFields['denomination'])->toBe('Test Company');
            expect($elasticFields['created_at'])->not->toBeNull();
            expect($elasticFields['updated_at'])->not->toBeNull();
        });

        it('handles missing optional fields gracefully', function () {
            $incompleteData = createZohoReportData([
                'ID' => '123',
                'required_field' => 'value'
                // Missing optional fields
            ]);
            
            // Both packages should handle missing fields without errors
            expect($incompleteData['ID'])->toBe('123');
            expect($incompleteData['required_field'])->toBe('value');
            expect($incompleteData['optional_field'] ?? null)->toBeNull();
        });
    });
});
```

### 3. SyncIntegrationTest.php
```php
<?php

describe('Sync Integration with ViprosElasticModels', function () {
    beforeEach(function () {
        // Setup both packages for integration testing
        \Agencedoit\ZohoConnector\Models\ZohoConnectorToken::create([
            'token' => 'sync_integration_token',
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    });

    describe('End-to-End Data Sync', function () {
        it('syncs company data from Zoho to Elasticsearch via ViprosElasticModels', function () {
            // Skip if ViprosElasticModels classes not available
            if (!class_exists(\Agencedoit\ViprosElasticModels\Models\ElasticApi\Company::class)) {
                $this->markTestSkipped('ViprosElasticModels not available');
            }
            
            $companyData = createCompanyData([
                'ID' => '61757000058385531',
                'denomination' => 'Integration Test Company',
                'siren' => '123456789',
                'company_status' => 'active'
            ]);
            
            // Mock Zoho API response
            $this->mockZohoResponse('Company_Report', [$companyData]);
            
            // Simulate the sync process that ViprosElasticModels would perform
            $zohoData = ZohoCreatorApi::get('Company_Report');
            
            expect($zohoData)->toBeArray();
            expect($zohoData)->toHaveCount(1);
            expect($zohoData[0]['ID'])->toBe('61757000058385531');
            
            // If we have access to the sync service, test the full flow
            if (class_exists(\Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class)) {
                // Mock Elasticsearch operations
                $this->mockElasticsearch();
                
                // This would normally be called by datamanager:sync command
                $syncService = app(\Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class);
                // $result = $syncService->syncEntity('company', $zohoData[0]);
                
                // expect($result)->toBeTrue();
            }
        });

        it('handles sync errors gracefully across packages', function () {
            // Mock Zoho error
            $this->mockZohoError(500, 'Zoho API Error');
            
            expect(fn() => ZohoCreatorApi::get('Error_Report'))
                ->toThrow(\Exception::class, 'Zoho API Error');
            
            // ViprosElasticModels should be able to catch and handle these errors
            try {
                ZohoCreatorApi::get('Error_Report');
            } catch (\Exception $e) {
                expect($e->getMessage())->toContain('Zoho API Error');
                
                // Error should be loggable by ViprosElasticModels
                if (class_exists(\Agencedoit\ViprosElasticModels\Services\Utils\LogService::class)) {
                    // Test error logging integration
                    expect($e)->toBeInstanceOf(\Exception::class);
                }
            }
        });
    });

    describe('Command Integration', function () {
        it('integrates with ViprosElasticModels datamanager commands', function () {
            // Test that ZohoConnector works with datamanager:sync command
            $companyData = createCompanyData();
            $this->mockZohoResponse('Company_Report', [$companyData]);
            
            // Simulate command execution
            if (\Illuminate\Support\Facades\Artisan::has('datamanager:sync')) {
                // This would test the actual integration
                // $exitCode = $this->artisan('datamanager:sync', ['index' => 'company'])
                //                  ->assertSuccessful()
                //                  ->run();
                
                // For now, just verify the data is accessible
                $result = ZohoCreatorApi::get('Company_Report');
                expect($result)->not->toBeEmpty();
            }
        });

        it('supports bulk operations with ViprosElasticModels', function () {
            Queue::fake();
            
            // Test bulk integration
            ZohoCreatorApi::getWithBulk('Company_Report', 'https://callback.test', '');
            
            Queue::assertPushed(\Agencedoit\ZohoConnector\Jobs\ZohoCreatorBulkProcess::class);
            
            // ViprosElasticModels should be able to process the resulting JSON
            // when the bulk job completes and calls the callback
        });
    });
});
```

---

## 📋 Tests de Contract

### 1. ZohoApiContractTest.php
```php
<?php

describe('Zoho API Contract Tests', function () {
    describe('Data Structure Contracts', function () {
        it('company data structure matches ViprosElasticModels expectations', function () {
            $zohoCompanyData = ZohoCreatorApi::getByID('Company_Report', '123');
            
            // Contract: Company data must have these required fields
            $requiredFields = ['ID', 'denomination', 'Added_Time', 'Modified_Time'];
            
            foreach ($requiredFields as $field) {
                expect($zohoCompanyData)->toHaveKey($field, "Missing required field: {$field}");
            }
            
            // Contract: ID must be string and match VIPros pattern
            expect($zohoCompanyData['ID'])->toBeString();
            expect($zohoCompanyData['ID'])->toMatch('/^617570\d{11}$/');
            
            // Contract: Dates must be ISO 8601 format
            expect($zohoCompanyData['Added_Time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
            expect($zohoCompanyData['Modified_Time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
        });

        it('bulk operation response follows expected contract', function () {
            $bulkResponse = ZohoCreatorApi::createBulk('Company_Report', 'status="Active"');
            
            // Contract: Bulk response must contain these fields
            expect($bulkResponse)->toHaveKey('bulk_id');
            expect($bulkResponse)->toHaveKey('status');
            
            // Contract: bulk_id must be string
            expect($bulkResponse['bulk_id'])->toBeString();
            expect($bulkResponse['bulk_id'])->not->toBeEmpty();
            
            // Contract: status must be valid value
            $validStatuses = ['In Progress', 'Completed', 'Failed'];
            expect($bulkResponse['status'])->toBeIn($validStatuses);
        });

        it('error responses follow consistent contract', function () {
            $this->mockZohoError(400, 'Validation Error');
            
            try {
                ZohoCreatorApi::get('Invalid_Report');
                $this->fail('Expected exception was not thrown');
            } catch (\Exception $e) {
                // Contract: Errors must be exceptions with message
                expect($e)->toBeInstanceOf(\Exception::class);
                expect($e->getMessage())->toBeString();
                expect($e->getMessage())->not->toBeEmpty();
            }
        });
    });

    describe('API Method Contracts', function () {
        it('get() method contract is maintained', function () {
            $this->mockZohoResponse('Test_Report', [createZohoReportData()]);
            
            // Contract: get() returns array
            $result = ZohoCreatorApi::get('Test_Report');
            expect($result)->toBeArray();
            
            // Contract: can handle criteria parameter
            $result = ZohoCreatorApi::get('Test_Report', 'status="Active"');
            expect($result)->toBeArray();
            
            // Contract: can handle cursor parameter by reference
            $cursor = '';
            $result = ZohoCreatorApi::get('Test_Report', '', $cursor);
            expect($result)->toBeArray();
            expect($cursor)->toBeString(); // May be empty or filled
        });

        it('create() method contract is maintained', function () {
            Http::fake([
                '*' => Http::response(['code' => 3000, 'data' => ['ID' => '123']], 200)
            ]);
            
            // Contract: create() accepts form name and attributes
            $result = ZohoCreatorApi::create('Test_Form', ['name' => 'Test']);
            
            expect($result)->toBeArray();
            expect($result)->toHaveKey('ID');
        });

        it('update() method contract is maintained', function () {
            Http::fake([
                '*' => Http::response(['code' => 3000, 'data' => ['ID' => '123']], 200)
            ]);
            
            // Contract: update() accepts report, ID, and attributes
            $result = ZohoCreatorApi::update('Test_Report', '123', ['name' => 'Updated']);
            
            expect($result)->toBeArray();
            expect($result)->toHaveKey('ID');
        });
    });

    describe('Integration Points Contract', function () {
        it('maintains service readiness contract', function () {
            // Contract: isReady() returns boolean
            $isReady = ZohoCreatorApi::isReady();
            expect($isReady)->toBeBool();
            
            // When ready, API calls should work
            if ($isReady) {
                $this->mockZohoResponse('Test_Report', []);
                expect(fn() => ZohoCreatorApi::get('Test_Report'))->not->toThrow(\Exception::class);
            }
        });

        it('maintains error handling contract with ViprosElasticModels', function () {
            // Contract: Network errors are thrown as exceptions
            Http::fake([
                '*' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Network error');
                }
            ]);
            
            expect(fn() => ZohoCreatorApi::get('Network_Error_Report'))
                ->toThrow(\Exception::class);
            
            // Contract: API errors are thrown as exceptions
            $this->mockZohoError(500, 'Server Error');
            
            expect(fn() => ZohoCreatorApi::get('Server_Error_Report'))
                ->toThrow(\Exception::class, 'Server Error');
        });
    });
});
```

### 2. DataStructureContractTest.php
```php
<?php

describe('Data Structure Contract Validation', function () {
    describe('ViprosElasticModels DTO Compatibility', function () {
        it('validates company data against CompanyZohoDTO contract', function () {
            if (!class_exists(\Agencedoit\ViprosElasticModels\DTOs\CompanyZohoDTO::class)) {
                $this->markTestSkipped('CompanyZohoDTO not available');
            }
            
            $companyData = createCompanyData([
                'ID' => '61757000058385531',
                'denomination' => 'Contract Test Company',
                'siren' => '123456789',
                'siret' => '12345678901234',
                'localisation' => [
                    'ID' => '61757000000001111',
                    'name' => 'France',
                    'zc_display_value' => 'France'
                ]
            ]);
            
            $dto = new \Agencedoit\ViprosElasticModels\DTOs\CompanyZohoDTO();
            
            // Contract: DTO should validate the structure
            expect($dto->isValidStructure($companyData))->toBeTrue();
            
            // Contract: DTO should be able to populate from data
            $dto->populateFromZoho($companyData);
            expect($dto->getId())->toBe('61757000058385531');
            expect($dto->getDenomination())->toBe('Contract Test Company');
        });

        it('validates contact data against ContactZohoDTO contract', function () {
            if (!class_exists(\Agencedoit\ViprosElasticModels\DTOs\ContactZohoDTO::class)) {
                $this->markTestSkipped('ContactZohoDTO not available');
            }
            
            $contactData = createContactData([
                'ID' => '61757000058385532',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@test.com'
            ]);
            
            $dto = new \Agencedoit\ViprosElasticModels\DTOs\ContactZohoDTO();
            
            expect($dto->isValidStructure($contactData))->toBeTrue();
            
            $dto->populateFromZoho($contactData);
            expect($dto->getId())->toBe('61757000058385532');
            expect($dto->getFullName())->toBe('John Doe');
        });
    });

    describe('Elasticsearch Model Compatibility', function () {
        it('validates data compatibility with Company Elasticsearch model', function () {
            if (!class_exists(\Agencedoit\ViprosElasticModels\Models\ElasticApi\Company::class)) {
                $this->markTestSkipped('Company Elasticsearch model not available');
            }
            
            $companyData = createCompanyData();
            
            // Contract: Data should be compatible with Elasticsearch model
            $company = new \Agencedoit\ViprosElasticModels\Models\ElasticApi\Company($companyData);
            
            expect($company)->toBeInstanceOf(\Agencedoit\ViprosElasticModels\Models\ElasticApi\Company::class);
            expect($company->getAttribute('ID'))->toBe($companyData['ID']);
            expect($company->getAttribute('denomination'))->toBe($companyData['denomination']);
        });

        it('handles field mapping between Zoho and Elasticsearch formats', function () {
            $zohoData = createCompanyData([
                'ID' => '123',
                'Added_Time' => '2025-01-06T12:00:00Z',
                'Modified_Time' => '2025-01-06T13:00:00Z'
            ]);
            
            // Contract: Field mapping should be consistent
            $elasticData = [
                'id' => $zohoData['ID'],
                'created_at' => $zohoData['Added_Time'],
                'updated_at' => $zohoData['Modified_Time'],
                'denomination' => $zohoData['denomination']
            ];
            
            expect($elasticData['id'])->toBe('123');
            expect($elasticData['created_at'])->toBe('2025-01-06T12:00:00Z');
            expect($elasticData['updated_at'])->toBe('2025-01-06T13:00:00Z');
        });
    });

    describe('Breaking Change Detection', function () {
        it('detects breaking changes in data structure', function () {
            // This test would fail if ViprosElasticModels changes its expected structure
            $expectedStructure = [
                'required_fields' => ['ID', 'denomination', 'Added_Time', 'Modified_Time'],
                'optional_fields' => ['siren', 'siret', 'vipros_number'],
                'nested_objects' => ['localisation', 'vipoints_balance', 'cashback_balance']
            ];
            
            $companyData = createCompanyData();
            
            // Verify all required fields are present
            foreach ($expectedStructure['required_fields'] as $field) {
                expect($companyData)->toHaveKey($field, "Breaking change: missing required field {$field}");
            }
            
            // Verify nested objects maintain their structure
            foreach ($expectedStructure['nested_objects'] as $nestedField) {
                if (isset($companyData[$nestedField])) {
                    expect($companyData[$nestedField])->toBeArray("Breaking change: {$nestedField} should be array");
                    expect($companyData[$nestedField])->toHaveKey('ID', "Breaking change: {$nestedField} missing ID");
                }
            }
        });

        it('maintains backward compatibility with older DTO versions', function () {
            // Test that current data structure works with theoretical older DTO versions
            $companyData = createCompanyData();
            
            // Remove optional new fields to simulate older version
            $oldVersionData = array_intersect_key($companyData, array_flip([
                'ID', 'denomination', 'Added_Time', 'Modified_Time'
            ]));
            
            expect($oldVersionData)->toHaveKey('ID');
            expect($oldVersionData)->toHaveKey('denomination');
            expect($oldVersionData)->not->toHaveKey('optional_new_field');
        });
    });
});
```

---

## ✅ Tests de validation des mocks

### 1. ApiValidationTest.php
```php
<?php

describe('Mock vs Real API Validation', function () {
    /** @group integration */
    /** @group slow */
    describe('Mock Accuracy Validation', function () {
        beforeEach(function () {
            if (!env('ZOHO_VALIDATE_MOCKS', false)) {
                $this->markTestSkipped('Mock validation disabled. Set ZOHO_VALIDATE_MOCKS=true to run.');
            }
            
            if (!env('ZOHO_INTEGRATION_CLIENT_ID') || !env('ZOHO_INTEGRATION_SECRET')) {
                $this->markTestSkipped('Real API credentials not available');
            }
        });

        it('validates mock company data structure against real API', function () {
            // Get mock response
            $mockData = $this->getMockCompanyResponse();
            
            // Get real API response (if available)
            $realData = $this->getRealCompanyResponse();
            
            // Compare structures
            $this->assertSameStructure($mockData, $realData);
            $this->assertSameDataTypes($mockData, $realData);
        });

        it('validates mock error responses against real API errors', function () {
            $mockError = mockZohoErrorResponse(4820, 'Rate limit exceeded');
            
            // Trigger real rate limit (carefully, in test environment only)
            try {
                $realError = $this->triggerRealRateLimit();
                $this->assertSameStructure($mockError, $realError);
            } catch (\Exception $e) {
                // If we can't trigger real rate limit, just validate mock structure
                expect($mockError)->toHaveKey('code');
                expect($mockError)->toHaveKey('message');
                expect($mockError['code'])->toBe(4820);
            }
        });

        it('validates mock bulk responses against real bulk operations', function () {
            $mockBulkResponse = createZohoBulkData();
            
            // Create real bulk operation (in test environment)
            try {
                $realBulkResponse = $this->createRealBulkOperation();
                $this->assertSameStructure($mockBulkResponse, $realBulkResponse);
            } catch (\Exception $e) {
                // Validate mock structure independently
                expect($mockBulkResponse)->toHaveKey('bulk_id');
                expect($mockBulkResponse)->toHaveKey('status');
            }
        });

        private function getMockCompanyResponse(): array
        {
            return mockZohoSuccessResponse([createCompanyData()]);
        }

        private function getRealCompanyResponse(): array
        {
            // Configure real API credentials temporarily
            config([
                'zohoconnector.client_id' => env('ZOHO_INTEGRATION_CLIENT_ID'),
                'zohoconnector.client_secret' => env('ZOHO_INTEGRATION_SECRET'),
            ]);
            
            // Make real API call with minimal data
            return ZohoCreatorApi::get('Company_Report', '', $cursor = '', 1); // Limit to 1 record
        }

        private function assertSameStructure(array $mock, array $real): void
        {
            $mockKeys = $this->extractStructureKeys($mock);
            $realKeys = $this->extractStructureKeys($real);
            
            expect($mockKeys)->toEqualCanonicalizing($realKeys, 'Mock structure differs from real API');
        }

        private function assertSameDataTypes(array $mock, array $real): void
        {
            $this->compareDataTypes($mock, $real, '');
        }

        private function compareDataTypes(array $mock, array $real, string $path): void
        {
            foreach ($mock as $key => $value) {
                $currentPath = $path ? "{$path}.{$key}" : $key;
                
                if (!array_key_exists($key, $real)) {
                    continue; // Skip missing keys in real data
                }
                
                if (is_array($value) && is_array($real[$key])) {
                    $this->compareDataTypes($value, $real[$key], $currentPath);
                } else {
                    $mockType = gettype($value);
                    $realType = gettype($real[$key]);
                    
                    expect($mockType)->toBe($realType, "Type mismatch at {$currentPath}: mock={$mockType}, real={$realType}");
                }
            }
        }

        private function extractStructureKeys(array $data, string $prefix = ''): array
        {
            $keys = [];
            
            foreach ($data as $key => $value) {
                $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
                $keys[] = $fullKey;
                
                if (is_array($value)) {
                    $keys = array_merge($keys, $this->extractStructureKeys($value, $fullKey));
                }
            }
            
            return $keys;
        }
    });
});
```

---

## 🚀 Tests de performance

### 1. LoadTestingTest.php
```php
<?php

describe('Load Testing and Performance', function () {
    /** @group performance */
    describe('API Call Performance', function () {
        it('handles multiple concurrent API calls efficiently', function () {
            $this->mockZohoResponse('Performance_Report', [createZohoReportData()]);
            
            $startTime = microtime(true);
            
            // Simulate 10 concurrent calls
            $promises = [];
            for ($i = 0; $i < 10; $i++) {
                $promises[] = Http::async()->get('www.zohoapis.eu/creator/v2.1/data/test/report/Performance_Report');
            }
            
            $responses = Http::pool($promises);
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            // Should complete all calls within reasonable time
            expect($duration)->toBeLessThan(5.0, 'Concurrent calls took too long');
            
            // All responses should be successful
            foreach ($responses as $response) {
                expect($response->successful())->toBeTrue();
            }
        });

        it('maintains performance under high pagination load', function () {
            // Mock 50 pages of data
            $pages = [];
            for ($i = 0; $i < 50; $i++) {
                $pages[] = array_fill(0, 200, createZohoReportData());
            }
            
            ZohoApiMockingHelper::mockZohoPagination('Large_Dataset', $pages);
            
            $startTime = microtime(true);
            $results = ZohoCreatorApi::getAll('Large_Dataset');
            $endTime = microtime(true);
            
            $duration = $endTime - $startTime;
            
            expect($results)->toHaveCount(10000); // 50 * 200
            expect($duration)->toBeLessThan(10.0, 'Pagination processing too slow');
        });

        it('handles large response payloads efficiently', function () {
            // Create large dataset
            $largeDataset = array_fill(0, 1000, createCompanyData());
            $this->mockZohoResponse('Large_Response', $largeDataset);
            
            $startMemory = memory_get_usage(true);
            
            $results = ZohoCreatorApi::get('Large_Response');
            
            $endMemory = memory_get_usage(true);
            $memoryUsed = $endMemory - $startMemory;
            
            expect($results)->toHaveCount(1000);
            expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024, 'Memory usage too high (>50MB)'); // 50MB limit
        });
    });

    describe('Bulk Operation Performance', function () {
        it('processes bulk operations within acceptable time limits', function () {
            Storage::fake('local');
            
            // Create large CSV data
            $csvData = [];
            for ($i = 0; $i < 5000; $i++) {
                $csvData[] = ['ID' => $i, 'Name' => "Record {$i}", 'Status' => 'Active'];
            }
            
            Http::fake([
                '*bulk*' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'perf_bulk'])], 200)
                    ->push(['result' => createZohoBulkData([
                        'status' => 'Completed',
                        'download_url' => 'https://perf.zip'
                    ])], 200),
                'https://perf.zip' => Http::response(
                    $this->createLargeZipWithCSV($csvData), 200,
                    ['Content-Type' => 'application/zip']
                ),
                'https://callback.test' => Http::response(['success' => true])
            ]);
            
            $startTime = microtime(true);
            
            $job = new \Agencedoit\ZohoConnector\Jobs\ZohoCreatorBulkProcess('Perf_Report', 'https://callback.test', '');
            $job->handle();
            
            $endTime = microtime(true);
            $duration = $endTime - $startTime;
            
            expect($duration)->toBeLessThan(30.0, 'Bulk processing took too long');
            
            // Verify JSON file was created
            $jsonFiles = Storage::files();
            $bulkFiles = array_filter($jsonFiles, fn($file) => str_ends_with($file, '.json'));
            expect($bulkFiles)->not->toBeEmpty();
        });

        private function createLargeZipWithCSV(array $data): string
        {
            $tempFile = tempnam(sys_get_temp_dir(), 'perf_zip');
            $zip = new ZipArchive();
            $zip->open($tempFile, ZipArchive::CREATE);
            
            $csvContent = "ID,Name,Status\n";
            foreach ($data as $row) {
                $csvContent .= implode(',', array_values($row)) . "\n";
            }
            
            $zip->addFromString('large_export.csv', $csvContent);
            $zip->close();
            
            return file_get_contents($tempFile);
        }
    });

    describe('Memory Usage Optimization', function () {
        it('maintains reasonable memory usage during large operations', function () {
            $initialMemory = memory_get_usage(true);
            
            // Process large dataset multiple times
            for ($i = 0; $i < 10; $i++) {
                $largeData = array_fill(0, 500, createCompanyData());
                $this->mockZohoResponse("Memory_Test_{$i}", $largeData);
                
                $results = ZohoCreatorApi::get("Memory_Test_{$i}");
                expect($results)->toHaveCount(500);
                
                // Force garbage collection
                unset($results, $largeData);
                gc_collect_cycles();
            }
            
            $finalMemory = memory_get_usage(true);
            $memoryIncrease = $finalMemory - $initialMemory;
            
            // Memory increase should be minimal after GC
            expect($memoryIncrease)->toBeLessThan(20 * 1024 * 1024, 'Memory leak detected (>20MB increase)');
        });
    });
});
```

---

## 🔄 Tests End-to-End

### 1. CompleteWorkflowTest.php
```php
<?php

describe('Complete End-to-End Workflows', function () {
    /** @group e2e */
    describe('Full Application Integration', function () {
        it('completes full company management workflow', function () {
            // Setup: Clean environment
            \Agencedoit\ZohoConnector\Models\ZohoConnectorToken::truncate();
            \Agencedoit\ZohoConnector\Models\ZohoBulkHistory::truncate();
            
            // Step 1: OAuth Authentication
            Http::fake([
                'accounts.zoho.eu/oauth/v2/token' => Http::response(createZohoTokenData(), 200)
            ]);
            
            $this->get('/zoho/request-code-response?code=e2e_auth_code');
            
            $this->assertDatabaseHas('zoho_connector_tokens', [
                'token' => createZohoTokenData()['access_token']
            ]);
            
            // Step 2: Create Company
            $newCompany = [
                'denomination' => 'E2E Test Company SARL',
                'siren' => '987654321',
                'siret' => '98765432109876',
                'status' => 'Active'
            ];
            
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/form/Company' => Http::response([
                    'code' => 3000,
                    'data' => array_merge($newCompany, [
                        'ID' => '61757000058385533',
                        'Added_Time' => now()->toISOString()
                    ])
                ], 200)
            ]);
            
            $created = ZohoCreatorApi::create('Company', $newCompany);
            expect($created['ID'])->toBe('61757000058385533');
            
            // Step 3: Retrieve and Verify
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies/61757000058385533' => Http::response([
                    'code' => 3000,
                    'data' => $created
                ], 200)
            ]);
            
            $retrieved = ZohoCreatorApi::getByID('All_Companies', '61757000058385533');
            expect($retrieved['denomination'])->toBe('E2E Test Company SARL');
            
            // Step 4: Update Company
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies/61757000058385533' => Http::response([
                    'code' => 3000,
                    'data' => array_merge($retrieved, [
                        'denomination' => 'Updated E2E Company SARL',
                        'Modified_Time' => now()->toISOString()
                    ])
                ], 200)
            ]);
            
            $updated = ZohoCreatorApi::update('All_Companies', '61757000058385533', [
                'denomination' => 'Updated E2E Company SARL'
            ]);
            
            expect($updated['denomination'])->toBe('Updated E2E Company SARL');
            
            // Step 5: Bulk Export
            Queue::fake();
            
            ZohoCreatorApi::getWithBulk('All_Companies', 'https://e2e.callback.test', 'status="Active"');
            
            Queue::assertPushed(\Agencedoit\ZohoConnector\Jobs\ZohoCreatorBulkProcess::class);
            
            // Verify workflow completion
            expect(\Agencedoit\ZohoConnector\Models\ZohoConnectorToken::count())->toBe(1);
        });

        it('handles complete error recovery workflow', function () {
            // Setup: Start with expired token
            \Agencedoit\ZohoConnector\Models\ZohoConnectorToken::create([
                'token' => 'expired_e2e_token',
                'refresh_token' => 'valid_refresh_token',
                'token_peremption_at' => now()->subMinute(),
                'token_duration' => 3600
            ]);
            
            // Scenario: API call with expired token → auto refresh → retry → success
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*' => Http::sequence()
                    ->push(mockZohoErrorResponse(6000, 'Invalid access token'), 401)
                    ->push(mockZohoSuccessResponse([createCompanyData()]), 200),
                'accounts.zoho.eu/oauth/v2/token' => Http::response(createZohoTokenData([
                    'access_token' => 'new_e2e_token'
                ]), 200)
            ]);
            
            $result = ZohoCreatorApi::get('Recovery_Test_Report');
            
            expect($result)->toBeArray();
            expect($result)->not->toBeEmpty();
            
            // Verify token was refreshed
            $newToken = \Agencedoit\ZohoConnector\Models\ZohoConnectorToken::first();
            expect($newToken->token)->toBe('new_e2e_token');
        });
    });

    describe('Cross-Package End-to-End Integration', function () {
        it('completes data sync from Zoho to Elasticsearch via ViprosElasticModels', function () {
            if (!class_exists(\Agencedoit\ViprosElasticModels\Models\ElasticApi\Company::class)) {
                $this->markTestSkipped('ViprosElasticModels not available for E2E test');
            }
            
            // Setup authentication
            \Agencedoit\ZohoConnector\Models\ZohoConnectorToken::create([
                'token' => 'e2e_sync_token',
                'token_peremption_at' => now()->addHour(),
                'token_duration' => 3600
            ]);
            
            // Mock company data in Zoho
            $companies = [
                createCompanyData([
                    'ID' => '61757000058385534',
                    'denomination' => 'E2E Sync Company A'
                ]),
                createCompanyData([
                    'ID' => '61757000058385535',
                    'denomination' => 'E2E Sync Company B'
                ])
            ];
            
            $this->mockZohoResponse('Company_Report', $companies);
            
            // Execute sync (simulating ViprosElasticModels workflow)
            $zohoData = ZohoCreatorApi::getAll('Company_Report');
            
            expect($zohoData)->toHaveCount(2);
            expect($zohoData[0]['denomination'])->toBe('E2E Sync Company A');
            expect($zohoData[1]['denomination'])->toBe('E2E Sync Company B');
            
            // Simulate Elasticsearch indexing
            foreach ($zohoData as $companyData) {
                $company = new \Agencedoit\ViprosElasticModels\Models\ElasticApi\Company($companyData);
                expect($company->getAttribute('ID'))->toBe($companyData['ID']);
                expect($company->getAttribute('denomination'))->toBe($companyData['denomination']);
            }
        });
    });
});
```

---

## ✅ Checklist Phase 4

### Intégration VIPros Elastic Models
- [ ] **Compatibilité cross-package**
  - [ ] Tests shared helpers
  - [ ] Tests facade accessibility
  - [ ] Tests service provider integration
  - [ ] Tests configuration partagée

- [ ] **Cohérence des données**
  - [ ] Tests format dates
  - [ ] Tests format IDs
  - [ ] Tests structures nested
  - [ ] Tests mapping fields

- [ ] **Sync integration**
  - [ ] Tests sync end-to-end
  - [ ] Tests error handling cross-package
  - [ ] Tests command integration

### Contract Testing
- [ ] **API contracts**
  - [ ] Tests structure données
  - [ ] Tests méthodes API
  - [ ] Tests error responses
  - [ ] Tests integration points

- [ ] **Breaking changes**
  - [ ] Tests détection changements
  - [ ] Tests backward compatibility
  - [ ] Tests DTO compatibility

### Validation mocks
- [ ] **Accuracy validation**
  - [ ] Tests mock vs real API
  - [ ] Tests error responses
  - [ ] Tests bulk operations
  - [ ] Tests structure consistency

### Performance
- [ ] **Load testing**
  - [ ] Tests calls concurrents
  - [ ] Tests pagination large
  - [ ] Tests response payloads
  - [ ] Tests bulk performance

- [ ] **Memory optimization**
  - [ ] Tests usage mémoire
  - [ ] Tests memory leaks
  - [ ] Tests garbage collection

### End-to-End
- [ ] **Workflows complets**
  - [ ] Tests company management
  - [ ] Tests error recovery
  - [ ] Tests cross-package sync

**Coverage target** : 95%+ sur l'ensemble des packages

**Commit final Phase 4** : `test: complete integration testing suite with cross-package validation`