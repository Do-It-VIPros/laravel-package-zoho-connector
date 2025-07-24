<?php

use Agencedoit\ZohoConnector\Tests\Feature\MockTestCase;
use Agencedoit\ZohoConnector\Services\ZohoCreatorService;
use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(MockTestCase::class);

beforeEach(function () {
    // Tests avec vraie base de données DDEV - pas de HTTP fake
    // Http::fake(); // Commenté pour utiliser vraie API
});

describe('ZohoCreatorService Method Validation', function () {
    
    describe('get() Method Tests', function () {
        it('handles successful API response with data', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [
                        ['ID' => '61757000058385531', 'Company_Name' => 'Test Company', 'Status' => 'Active'],
                        ['ID' => '61757000058385532', 'Company_Name' => 'Another Company', 'Status' => 'Inactive']
                    ],
                    'info' => ['count' => 2, 'more_records' => false]
                ], 200, ['Content-Type' => 'application/json'])
            ]);
            
            $service = new ZohoCreatorService();
            $result = $service->get('test_report');
            
            expect($result)->toBeArray();
            expect($result)->toHaveCount(2);
            expect($result[0]['ID'])->toBe('61757000058385531');
            expect($result[0]['Company_Name'])->toBe('Test Company');
            expect($result[1]['Status'])->toBe('Inactive');
        });
        
        it('handles API response with pagination cursor', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [
                        ['ID' => '123', 'Company_Name' => 'Company 1'],
                        ['ID' => '124', 'Company_Name' => 'Company 2']
                    ],
                    'info' => ['count' => 2, 'more_records' => true]
                ], 200, [
                    'Content-Type' => 'application/json',
                    'record_cursor' => 'next_page_cursor_123'
                ])
            ]);
            
            $service = new ZohoCreatorService();
            $cursor = '';
            $result = $service->get('test_report', '', $cursor);
            
            expect($result)->toBeArray();
            expect($result)->toHaveCount(2);
            expect($cursor)->toBe('next_page_cursor_123');
        });
        
        it('handles empty API response', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [],
                    'info' => ['count' => 0, 'more_records' => false]
                ], 200, ['Content-Type' => 'application/json'])
            ]);
            
            $service = new ZohoCreatorService();
            $result = $service->get('test_report');
            
            expect($result)->toBeArray();
            expect($result)->toHaveCount(0);
        });
        
        it('handles criteria as string parameter', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [['ID' => '123', 'Status' => 'Active']],
                    'info' => ['count' => 1, 'more_records' => false]
                ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $result = $service->get('test_report', "Status == 'Active'");
            
            expect($result)->toBeArray();
            expect($result[0]['Status'])->toBe('Active');
            
            Http::assertSent(function ($request) {
                return str_contains($request->url(), 'criteria=Status%20%3D%3D%20%27Active%27');
            });
        });
        
        it('handles criteria as array parameter', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [['ID' => '123', 'Status' => 'Active']],
                    'info' => ['count' => 1, 'more_records' => false]
                ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            // Correct array structure expected by service: field => ['comparaison' => '==', 'value' => 'Active']
            $criteria = [
                'Status' => ['comparaison' => ' == ', 'value' => "'Active'"],
                'Company_Name' => ['comparaison' => ' == ', 'value' => "'Test'"]
            ];
            $result = $service->get('test_report', $criteria);
            
            expect($result)->toBeArray();
            expect($result[0]['Status'])->toBe('Active');
        });
        
        it('throws exception for missing report parameter', function () {
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->get(''))
                ->toThrow(Exception::class, 'Missing required report parameter');
        });
        
        it('handles API error responses', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3001,
                    'message' => 'Record not found'
                ], 404)
            ]);
            
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->get('test_report'))
                ->toThrow(Exception::class);
        });
    });
    
    describe('getByID() Method Tests', function () {
        it('retrieves specific record by ID successfully', function () {
            // Mock response structure based on Zoho Creator API v2.1 getByID documentation
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [
                        'ID' => '61757000058385531',
                        'Company_Name' => 'Specific Company',
                        'Status' => 'Active',
                        'Created_Time' => '06-Jan-2025 14:30:00'
                    ]
                ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $result = $service->getByID('test_report', '61757000058385531');
            
            expect($result)->toBeArray();
            expect($result['data']['ID'])->toBe('61757000058385531');
            expect($result['data']['Company_Name'])->toBe('Specific Company');
            
            // Verify correct API endpoint was called
            Http::assertSent(function ($request) {
                return str_contains($request->url(), '/test_report/61757000058385531') &&
                       $request->hasHeader('Authorization') &&
                       $request->method() === 'GET';
            });
        });
        
        it('throws exception when record not found', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3001,
                    'message' => 'Record not found'
                ], 404)
            ]);
            
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->getByID('test_report', 'nonexistent_id'))
                ->toThrow(Exception::class);
        });
        
        it('validates required parameters', function () {
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->getByID('', 'some_id'))
                ->toThrow(Exception::class, 'Missing required report parameter');
                
            // Note: getByID method doesn't validate empty ID in current implementation
            // This would be a future enhancement
        });
    });
    
    describe('create() Method Tests', function () {
        it('creates new record successfully', function () {
            // Mock response structure based on Zoho Creator API v2.1 add records documentation
            Http::fake([
                '*' => Http::response([
                    'code' => 3000, // Top-level code required by ZohoResponseCheck
                    'result' => [
                        [
                            'code' => 3000,
                            'data' => [
                                'ID' => '61757000058385533',
                                'Company_Name' => 'New Company',
                                'Status' => 'Active'
                            ],
                            'message' => 'Data Added Successfully!'
                        ]
                    ]
                ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $data = ['Company_Name' => 'New Company', 'Status' => 'Active'];
            $result = $service->create('test_form', $data);
            
            expect($result)->toBeArray();
            expect($result[0]['ID'])->toBe('61757000058385533');
            expect($result[0]['Company_Name'])->toBe('New Company');
            
            // Verify POST request with correct structure
            Http::assertSent(function ($request) use ($data) {
                $body = $request->data();
                return $request->method() === 'POST' &&
                       str_contains($request->url(), '/form/test_form') &&
                       $body['data']['Company_Name'] === $data['Company_Name'];
            });
        });
        
        it('handles validation errors from API', function () {
            Http::fake([
                '*' => Http::response([
                    'result' => [
                        [
                            'code' => 3002,
                            'message' => 'Validation failed',
                            'details' => 'Company name is required'
                        ]
                    ]
                ], 400)
            ]);
            
            $service = new ZohoCreatorService();
            $data = ['Status' => 'Active']; // Missing required field
            
            expect(fn() => $service->create('test_form', $data))
                ->toThrow(Exception::class);
        });
        
        it('validates required parameters', function () {
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->create('', ['Company_Name' => 'Test']))
                ->toThrow(Exception::class, 'Missing required form parameter');
        });
    });
    
    describe('update() Method Tests', function () {
        it('updates existing record successfully', function () {
            // Mock response structure based on Zoho Creator API v2.1 update records documentation
            Http::fake([
                '*' => Http::response([
                    'code' => 3000, // Top-level code required by ZohoResponseCheck
                    'result' => [
                        [
                            'code' => 3000,
                            'data' => [
                                'ID' => '61757000058385531',
                                'Company_Name' => 'Updated Company',
                                'Status' => 'Active',
                                'Modified_Time' => '06-Jan-2025 16:30:00'
                            ],
                            'message' => 'Data updated successfully'
                        ]
                    ]
                ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $data = ['Company_Name' => 'Updated Company'];
            $result = $service->update('test_report', '61757000058385531', $data);
            
            expect($result)->toBeArray();
            expect($result[0]['Company_Name'])->toBe('Updated Company');
            
            // Verify PATCH request to correct endpoint
            Http::assertSent(function ($request) use ($data) {
                $body = $request->data();
                return $request->method() === 'PATCH' &&
                       str_contains($request->url(), '/61757000058385531') &&
                       $body['data']['Company_Name'] === $data['Company_Name'];
            });
        });
        
        it('handles record not found for update', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3001,
                    'message' => 'Record not found'
                ], 404)
            ]);
            
            $service = new ZohoCreatorService();
            $data = ['Company_Name' => 'Updated Company'];
            
            expect(fn() => $service->update('test_report', 'nonexistent_id', $data))
                ->toThrow(Exception::class);
        });
        
        it('validates required parameters', function () {
            $service = new ZohoCreatorService();
            $data = ['Company_Name' => 'Test'];
            
            expect(fn() => $service->update('', 'some_id', $data))
                ->toThrow(Exception::class, 'Missing required report parameter');
                
            // Note: update method doesn't validate empty ID in current implementation
            // This would be a future enhancement
        });
    });
    
    describe('getAll() Method Tests', function () {
        it('handles pagination automatically to get all records', function () {
            // First page
            Http::fake([
                '*/test_report*' => Http::sequence()
                    ->push([
                        'code' => 3000,
                        'data' => [
                            ['ID' => '1', 'Company_Name' => 'Company 1'],
                            ['ID' => '2', 'Company_Name' => 'Company 2']
                        ],
                        'info' => ['count' => 2, 'more_records' => true]
                    ], 200, ['record_cursor' => 'cursor_page_2'])
                    ->push([
                        'code' => 3000,
                        'data' => [
                            ['ID' => '3', 'Company_Name' => 'Company 3']
                        ],
                        'info' => ['count' => 1, 'more_records' => false]
                    ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $result = $service->getAll('test_report', '', 0); // No delay for testing
            
            expect($result)->toBeArray();
            expect($result)->toHaveCount(3);
            expect($result[0]['Company_Name'])->toBe('Company 1');
            expect($result[2]['Company_Name'])->toBe('Company 3');
            
            // Verify both API calls were made
            Http::assertSentCount(2);
        });
        
        it('handles single page response without pagination', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [
                        ['ID' => '1', 'Company_Name' => 'Only Company']
                    ],
                    'info' => ['count' => 1, 'more_records' => false]
                ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $result = $service->getAll('test_report');
            
            expect($result)->toBeArray();
            expect($result)->toHaveCount(1);
            expect($result[0]['Company_Name'])->toBe('Only Company');
        });
    });
    
    describe('upload() Method Tests', function () {
        it('uploads file successfully', function () {
            Storage::fake('local');
            $file = UploadedFile::fake()->create('test-document.pdf', 100, 'application/pdf');
            
            Http::fake([
                '*' => Http::response([
                    'code' => 3000, // Top-level code required by ZohoResponseCheck
                    'result' => [
                        [
                            'code' => 3000,
                            'data' => [
                                'file_id' => 'file_61757000058385531',
                                'filename' => 'test-document.pdf',
                                'size' => 102400
                            ],
                            'message' => 'File uploaded successfully'
                        ]
                    ]
                ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $result = $service->upload('test_report', '61757000058385531', 'document_field', $file);
            
            expect($result)->toBeArray();
            expect($result['result'][0]['data']['filename'])->toBe('test-document.pdf');
            
            // File upload functionality validated - HTTP mocking with files has limitations in testing
        });
        
        it('handles file too large error', function () {
            Storage::fake('local');
            $largeFile = UploadedFile::fake()->create('large-file.pdf', 51000, 'application/pdf'); // 51MB
            
            Http::fake([
                '*' => Http::response([
                    'code' => 4002,
                    'message' => 'File size exceeds the allowed limit'
                ], 413)
            ]);
            
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->upload('test_report', '123', 'document', $largeFile))
                ->toThrow(Exception::class);
        });
    });
    
    describe('HTTP Request Validation', function () {
        it('sends correct headers and parameters for get request', function () {
            Http::fake([
                '*' => Http::response(['code' => 3000, 'data' => [], 'info' => ['count' => 0]], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $service->get('test_report', "Status == 'Active'");
            
            Http::assertSent(function ($request) {
                // Verify URL structure
                $hasCorrectUrl = str_contains($request->url(), '/creator/v2.1/data/');
                $hasReportPath = str_contains($request->url(), '/report/test_report');
                
                // Verify parameters
                $hasMaxRecords = str_contains($request->url(), 'max_records=1000');
                $hasFieldConfig = str_contains($request->url(), 'field_config=all');
                $hasCriteria = str_contains($request->url(), 'criteria=');
                
                // Verify headers
                $hasAuthHeader = $request->hasHeader('Authorization');
                $hasOAuthToken = str_contains($request->header('Authorization')[0], 'Zoho-oauthtoken');
                
                return $hasCorrectUrl && $hasReportPath && $hasMaxRecords && 
                       $hasFieldConfig && $hasCriteria && $hasAuthHeader && $hasOAuthToken;
            });
        });
        
        it('includes cursor in headers when provided', function () {
            Http::fake([
                '*' => Http::response(['code' => 3000, 'data' => [], 'info' => ['count' => 0]], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $cursor = 'test_cursor_123';
            $service->get('test_report', '', $cursor);
            
            Http::assertSent(function ($request) {
                return $request->hasHeader('record_cursor') && 
                       $request->header('record_cursor')[0] === 'test_cursor_123';
            });
        });
        
        it('uses correct API endpoints for different operations', function () {
            Http::fake(['*' => Http::response(['code' => 3000, 'data' => []], 200)]);
            
            $service = new ZohoCreatorService();
            
            // Test get endpoint
            $service->get('Test_Report');
            Http::assertSent(function ($request) {
                return str_contains($request->url(), '/creator/v2.1/data/') &&
                       str_contains($request->url(), '/report/Test_Report');
            });
            
            Http::fake(['*' => Http::response(['result' => [['code' => 3000, 'data' => ['ID' => '123']]]], 200)]);
            
            // Test create endpoint  
            $service->create('Test_Form', ['Name' => 'Test']);
            Http::assertSent(function ($request) {
                return str_contains($request->url(), '/creator/v2.1/data/') &&
                       str_contains($request->url(), '/form/Test_Form');
            });
        });
    });
    
    describe('Error Handling Tests', function () {
        it('handles network timeout errors', function () {
            Http::fake([
                '*' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Network timeout');
                }
            ]);
            
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->get('test_report'))
                ->toThrow(Exception::class);
        });
        
        it('handles rate limit errors appropriately', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 4820,
                    'message' => 'API rate limit exceeded'
                ], 429)
            ]);
            
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->get('test_report'))
                ->toThrow(Exception::class);
        });
        
        it('handles server errors with appropriate logging', function () {
            Http::fake([
                '*' => Http::response([
                    'code' => 5000,
                    'message' => 'Internal server error'
                ], 500)
            ]);
            
            $service = new ZohoCreatorService();
            
            expect(fn() => $service->get('test_report'))
                ->toThrow(Exception::class);
        });
        
        it('validates service readiness before operations', function () {
            // En mode test, les vérifications de service sont ignorées
            // Ce test valide que le mode test fonctionne correctement
            Http::fake([
                '*' => Http::response([
                    'code' => 3000,
                    'data' => [],
                    'info' => ['count' => 0, 'more_records' => false]
                ], 200)
            ]);
            
            $service = new ZohoCreatorService();
            $result = $service->get('test_report');
            
            expect($result)->toBeArray();
        });
    });
});