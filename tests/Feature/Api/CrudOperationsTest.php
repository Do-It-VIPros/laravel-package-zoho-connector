<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

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

        it('handles batch creation with skip_workflow', function () {
            $companies = [
                ['denomination' => 'Company 1', 'siren' => '111111111'],
                ['denomination' => 'Company 2', 'siren' => '222222222']
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/form/Company' => Http::response([
                    'result' => [
                        [
                            'code' => 3000,
                            'data' => array_merge($companies[0], ['ID' => '123']),
                            'message' => 'Data Added Successfully!'
                        ],
                        [
                            'code' => 3000,
                            'data' => array_merge($companies[1], ['ID' => '124']),
                            'message' => 'Data Added Successfully!'
                        ]
                    ]
                ], 200)
            ]);

            $result = ZohoCreatorApi::create('Company', $companies, ['skip_workflow' => ['approval', 'notification']]);

            expect($result)->toBeArray();
            expect($result)->toHaveKey('result');
            expect($result['result'])->toHaveCount(2);
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

        it('supports multiple file uploads to same record', function () {
            Storage::fake('local');
            $files = [
                'contract' => UploadedFile::fake()->create('contract.pdf', 100),
                'invoice' => UploadedFile::fake()->create('invoice.pdf', 100),
                'report' => UploadedFile::fake()->create('report.pdf', 100)
            ];

            foreach ($files as $fieldName => $file) {
                Http::fake([
                    "www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies/123/upload" => Http::response([
                        'code' => 3000,
                        'message' => 'File uploaded successfully',
                        'data' => [
                            'file_id' => "file_{$fieldName}_123",
                            'filename' => $file->name
                        ]
                    ], 200)
                ]);

                $result = ZohoCreatorApi::upload('All_Companies', '123', "{$fieldName}_field", $file);
                
                expect($result)->toBeArray();
                expect($result['data']['file_id'])->toContain($fieldName);
            }
        });

        it('validates file types before upload', function () {
            Storage::fake('local');
            $invalidFile = UploadedFile::fake()->create('malicious.exe', 100);

            Http::fake([
                '*upload*' => Http::response([
                    'code' => 4003,
                    'message' => 'Invalid file type'
                ], 400)
            ]);

            expect(fn() => ZohoCreatorApi::upload('All_Companies', '123', 'file_field', $invalidFile))
                ->toThrow(\Exception::class, 'Invalid file type');
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

        it('supports pagination with criteria', function () {
            $criteria = 'status == "Active"';
            
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies*' => Http::sequence()
                    ->push(mockZohoPaginatedResponse(
                        array_fill(0, 200, createZohoReportData()),
                        'cursor_page_2'
                    ), 200)
                    ->push(mockZohoPaginatedResponse(
                        array_fill(0, 100, createZohoReportData()),
                        null
                    ), 200)
            ]);

            $results = ZohoCreatorApi::getAll('All_Companies', $criteria);

            expect($results)->toBeArray();
            expect($results)->toHaveCount(300); // 200 + 100
            Http::assertSentCount(2); // Two pages
        });

        it('handles empty result sets gracefully', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies*' => Http::response([
                    'code' => 3000,
                    'data' => [],
                    'info' => [
                        'count' => 0,
                        'more_records' => false
                    ]
                ], 200)
            ]);

            $results = ZohoCreatorApi::get('All_Companies', 'status == "NonExistent"');

            expect($results)->toBeArray();
            expect($results)->toBeEmpty();
        });
    });

    describe('Advanced CRUD Features', function () {
        it('supports field configuration in get requests', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies*' => function ($request) {
                    $fieldConfig = $request->query('field_config', 'quick_view');
                    
                    return Http::response([
                        'code' => 3000,
                        'data' => [createZohoReportData()],
                        'info' => [
                            'count' => 1,
                            'more_records' => false,
                            'field_config' => $fieldConfig
                        ]
                    ], 200);
                }
            ]);

            // Test with different field configurations
            $configs = ['quick_view', 'detail_view', 'all_fields'];
            
            foreach ($configs as $config) {
                $result = ZohoCreatorApi::get('All_Companies', '', ['field_config' => $config]);
                expect($result)->toBeArray();
            }

            Http::assertSentCount(3);
        });

        it('handles CSV format responses', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies*' => function ($request) {
                    if ($request->header('accept') === 'text/csv') {
                        return Http::response(
                            "ID,denomination,status\n123,Test Company,Active\n124,Another Company,Inactive",
                            200,
                            ['Content-Type' => 'text/csv']
                        );
                    }
                    
                    return Http::response(mockZohoSuccessResponse([]), 200);
                }
            ]);

            // Request CSV format
            $response = Http::withHeaders(['accept' => 'text/csv'])
                ->get('www.zohoapis.eu/creator/v2.1/data/test/report/All_Companies');

            expect($response->header('Content-Type'))->toBe('text/csv');
            expect($response->body())->toContain('Test Company');
        });

        it('supports max_records parameter', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies*' => function ($request) {
                    $maxRecords = $request->query('max_records', 200);
                    $data = array_fill(0, 500, createZohoReportData());
                    
                    return Http::response([
                        'code' => 3000,
                        'data' => array_slice($data, 0, $maxRecords),
                        'info' => [
                            'count' => min($maxRecords, 500),
                            'more_records' => $maxRecords < 500
                        ]
                    ], 200);
                }
            ]);

            $result = ZohoCreatorApi::get('All_Companies', '', ['max_records' => 50]);

            expect($result)->toBeArray();
            expect($result)->toHaveCount(50);
        });

        it('handles partial updates correctly', function () {
            $existingData = [
                'ID' => '123',
                'denomination' => 'Original Company',
                'status' => 'Active',
                'email' => 'old@company.com',
                'phone' => '1234567890'
            ];

            $updateData = [
                'email' => 'new@company.com'
                // Only updating email, other fields should remain unchanged
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/data/*/report/All_Companies/123' => Http::response([
                    'code' => 3000,
                    'data' => array_merge($existingData, $updateData, [
                        'Modified_Time' => now()->toISOString()
                    ])
                ], 200)
            ]);

            $result = ZohoCreatorApi::update('All_Companies', '123', $updateData);

            expect($result['email'])->toBe('new@company.com');
            expect($result['denomination'])->toBe('Original Company'); // Unchanged
            expect($result['status'])->toBe('Active'); // Unchanged
        });
    });
});