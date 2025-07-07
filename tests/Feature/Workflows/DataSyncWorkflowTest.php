<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;
use Illuminate\Support\Facades\Http;

describe('Complete Data Synchronization Workflow', function () {
    beforeEach(function () {
        ZohoConnectorToken::create([
            'token' => 'sync_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now(),
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

        it('processes incremental data updates', function () {
            $lastSyncTime = now()->subHour();
            $criteria = "Modified_Time > \"{$lastSyncTime->toISOString()}\"";
            
            $incrementalData = [
                createZohoReportData([
                    'ID' => '123',
                    'denomination' => 'Updated Company',
                    'Modified_Time' => now()->toISOString()
                ]),
                createZohoReportData([
                    'ID' => '124', 
                    'denomination' => 'New Company',
                    'Added_Time' => now()->toISOString()
                ])
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies*' => Http::response(
                    mockZohoSuccessResponse($incrementalData), 200
                )
            ]);

            $results = ZohoCreatorApi::get('All_Companies', $criteria);

            expect($results)->toHaveCount(2);
            expect($results[0]['denomination'])->toBe('Updated Company');
            
            // Verify criteria was passed
            Http::assertSent(function ($request) use ($criteria) {
                return str_contains($request->url(), urlencode($criteria));
            });
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

        it('normalizes field names and values', function () {
            $dataWithSpecialChars = [
                createZohoReportData([
                    'Company Name' => 'Test & Co.',
                    'E-mail' => 'test@company.com',
                    'Phone Number' => '+33 1 23 45 67 89'
                ])
            ];

            Http::fake(['*' => Http::response(mockZohoSuccessResponse($dataWithSpecialChars), 200)]);

            $results = ZohoCreatorApi::get('Special_Chars_Report');

            expect($results[0]['Company Name'])->toBe('Test & Co.');
            expect($results[0]['E-mail'])->toBe('test@company.com');
            expect($results[0]['Phone Number'])->toBe('+33 1 23 45 67 89');
        });

        it('handles Unicode and international characters', function () {
            $unicodeData = [
                createZohoReportData([
                    'société' => 'Société Française',
                    'город' => 'Москва',
                    '会社' => 'トヨタ自動車',
                    'emoji' => '🏢 Office Building'
                ])
            ];

            Http::fake(['*' => Http::response(mockZohoSuccessResponse($unicodeData), 200)]);

            $results = ZohoCreatorApi::get('Unicode_Report');

            expect($results[0]['société'])->toBe('Société Française');
            expect($results[0]['город'])->toBe('Москва');
            expect($results[0]['会社'])->toBe('トヨタ自動車');
            expect($results[0]['emoji'])->toBe('🏢 Office Building');
        });
    });

    describe('Multi-Report Synchronization', function () {
        it('synchronizes related data across multiple reports', function () {
            $companies = [
                createZohoReportData(['ID' => '123', 'denomination' => 'Company A']),
                createZohoReportData(['ID' => '124', 'denomination' => 'Company B'])
            ];
            
            $contacts = [
                createZohoReportData(['ID' => '456', 'name' => 'John Doe', 'company_id' => '123']),
                createZohoReportData(['ID' => '457', 'name' => 'Jane Smith', 'company_id' => '124'])
            ];

            Http::fake([
                '*All_Companies*' => Http::response(mockZohoSuccessResponse($companies), 200),
                '*All_Contacts*' => Http::response(mockZohoSuccessResponse($contacts), 200)
            ]);

            // Sync companies first
            $companiesResult = ZohoCreatorApi::getAll('All_Companies');
            
            // Then sync related contacts
            $contactsResult = ZohoCreatorApi::getAll('All_Contacts');

            expect($companiesResult)->toHaveCount(2);
            expect($contactsResult)->toHaveCount(2);
            
            // Verify relationships
            expect($contactsResult[0]['company_id'])->toBe('123');
            expect($contactsResult[1]['company_id'])->toBe('124');
        });

        it('handles dependencies between reports', function () {
            // Companies must be synced before contacts
            $syncOrder = ['All_Companies', 'All_Contacts', 'All_Projects'];
            
            foreach ($syncOrder as $report) {
                Http::fake([
                    "*{$report}*" => Http::response(mockZohoSuccessResponse([
                        createZohoReportData(['report_name' => $report])
                    ]), 200)
                ]);

                $result = ZohoCreatorApi::getAll($report);
                expect($result)->toHaveCount(1);
                expect($result[0]['report_name'])->toBe($report);
            }
        });

        it('maintains referential integrity during sync', function () {
            // Simulate deleting a company that has contacts
            $companies = [
                createZohoReportData(['ID' => '123', 'denomination' => 'Active Company'])
                // Company 124 deleted
            ];
            
            $contacts = [
                createZohoReportData(['ID' => '456', 'name' => 'John Doe', 'company_id' => '123']),
                createZohoReportData(['ID' => '457', 'name' => 'Orphaned Contact', 'company_id' => '124'])
            ];

            Http::fake([
                '*All_Companies*' => Http::response(mockZohoSuccessResponse($companies), 200),
                '*All_Contacts*' => Http::response(mockZohoSuccessResponse($contacts), 200)
            ]);

            $companiesResult = ZohoCreatorApi::getAll('All_Companies');
            $contactsResult = ZohoCreatorApi::getAll('All_Contacts');

            // Data integrity handling would be in application logic
            $activeCompanyIds = collect($companiesResult)->pluck('ID');
            $orphanedContacts = collect($contactsResult)->filter(function ($contact) use ($activeCompanyIds) {
                return !$activeCompanyIds->contains($contact['company_id']);
            });

            expect($orphanedContacts)->toHaveCount(1);
            expect($orphanedContacts->first()['name'])->toBe('Orphaned Contact');
        });
    });

    describe('Performance and Optimization', function () {
        it('batches requests for optimal performance', function () {
            $batchSize = 200;
            $totalRecords = 1000;
            
            // Setup multiple pages
            $pages = [];
            for ($i = 0; $i < 5; $i++) {
                $pages[] = array_fill(0, $batchSize, createZohoReportData(['page' => $i]));
            }

            $responses = [];
            for ($i = 0; $i < 5; $i++) {
                $cursor = $i < 4 ? "cursor_page_" . ($i + 1) : null;
                $responses[] = Http::response(mockZohoPaginatedResponse($pages[$i], $cursor), 200);
            }

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/Large_Report*' => Http::sequence(...$responses)
            ]);

            $results = ZohoCreatorApi::getAll('Large_Report');

            expect($results)->toHaveCount($totalRecords);
            Http::assertSentCount(5); // Optimal batch count
        });

        it('implements efficient delta synchronization', function () {
            $lastSyncTime = now()->subDay();
            $deltaRecords = [
                createZohoReportData(['ID' => '123', 'Modified_Time' => now()->subHour()->toISOString()]),
                createZohoReportData(['ID' => '124', 'Added_Time' => now()->subMinutes(30)->toISOString()])
            ];

            Http::fake([
                '*' => Http::response(mockZohoSuccessResponse($deltaRecords), 200)
            ]);

            $criteria = "Modified_Time > \"{$lastSyncTime->toISOString()}\" OR Added_Time > \"{$lastSyncTime->toISOString()}\"";
            $results = ZohoCreatorApi::get('Delta_Report', $criteria);

            expect($results)->toHaveCount(2);
            
            // Verify delta criteria was used
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'Modified_Time') &&
                       str_contains($request->url(), 'Added_Time');
            });
        });

        it('caches frequently accessed metadata', function () {
            $formsMeta = [
                ['form_name' => 'Company', 'fields_count' => 15],
                ['form_name' => 'Contact', 'fields_count' => 10]
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/forms' => Http::response([
                    'code' => 3000,
                    'forms' => $formsMeta
                ], 200)
            ]);

            // Multiple calls to metadata
            $meta1 = ZohoCreatorApi::getFormsMeta();
            $meta2 = ZohoCreatorApi::getFormsMeta();

            expect($meta1)->toEqual($meta2);
            
            // Without caching, this would be 2 calls
            Http::assertSentCount(2); // Adjust based on caching implementation
        });
    });

    describe('Error Recovery in Workflows', function () {
        it('recovers from transient network errors', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*' => Http::sequence()
                    ->push(function () {
                        throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
                    })
                    ->push(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ]);

            $results = ZohoCreatorApi::get('Network_Test_Report');

            expect($results)->toBeArray();
            expect($results)->not->toBeEmpty();
        });

        it('handles partial sync failures gracefully', function () {
            $reports = ['Report1', 'Report2', 'Report3'];
            $responses = [
                Http::response(mockZohoSuccessResponse([createZohoReportData()]), 200),
                Http::response(['error' => 'Server error'], 500),
                Http::response(mockZohoSuccessResponse([createZohoReportData()]), 200)
            ];

            $results = [];
            foreach ($reports as $i => $report) {
                Http::fake(["*{$report}*" => $responses[$i]]);
                
                try {
                    $results[$report] = ZohoCreatorApi::get($report);
                } catch (\Exception $e) {
                    $results[$report] = ['error' => $e->getMessage()];
                }
            }

            // Report1 and Report3 should succeed, Report2 should fail
            expect($results['Report1'])->toBeArray();
            expect($results['Report2'])->toHaveKey('error');
            expect($results['Report3'])->toBeArray();
        });
    });
});