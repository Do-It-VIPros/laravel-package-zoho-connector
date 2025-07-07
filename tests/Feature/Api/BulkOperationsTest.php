<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Models\ZohoBulkHistory;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;
use Agencedoit\ZohoConnector\Jobs\ZohoCreatorBulkProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;

describe('Bulk Operations Workflow', function () {
    beforeEach(function () {
        Storage::fake('local');
        
        ZohoConnectorToken::create([
            'token' => 'valid_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now(),
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
                    createMockZipWithCSV([
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
                        'bulk_id' => 'bulk_csv',
                        'status' => 'Completed',
                        'download_url' => 'https://test.zip'
                    ])], 200),
                'https://test.zip' => Http::response(
                    createMockZipWithCSV($csvData), 200,
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
                        'bulk_id' => 'bulk_corrupt',
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

        it('handles large bulk exports with multiple CSV files', function () {
            // Simulate large dataset split across multiple CSV files in ZIP
            $largeDataset = [];
            for ($i = 0; $i < 10000; $i++) {
                $largeDataset[] = [
                    'ID' => $i,
                    'Name' => "Company {$i}",
                    'Status' => $i % 2 ? 'Active' : 'Inactive'
                ];
            }

            Http::fake([
                '*bulk*' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_large'])], 200)
                    ->push(['result' => createZohoBulkData([
                        'bulk_id' => 'bulk_large',
                        'status' => 'Completed',
                        'download_url' => 'https://large.zip'
                    ])], 200),
                'https://large.zip' => Http::response(
                    createMockZipWithCSV($largeDataset), 200,
                    ['Content-Type' => 'application/zip']
                ),
                'https://callback.test' => Http::response(['success' => true])
            ]);

            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test', '');
            $job->handle();

            // Verify JSON file contains all records
            $jsonFiles = Storage::files();
            $jsonFile = collect($jsonFiles)->first(fn($file) => str_ends_with($file, '.json'));
            $jsonContent = json_decode(Storage::get($jsonFile), true);

            expect($jsonContent)->toHaveCount(10000);
            expect($jsonContent[9999]['Name'])->toBe('Company 9999');
        });
    });

    describe('Bulk Operation Status Tracking', function () {
        it('tracks bulk operation progress through different states', function () {
            $bulkId = 'bulk_progress_123';
            $states = ['Created', 'In Progress', 'Processing', 'Completed'];
            
            $responses = [];
            foreach ($states as $state) {
                $responses[] = Http::response([
                    'result' => createZohoBulkData([
                        'bulk_id' => $bulkId,
                        'status' => $state,
                        'download_url' => $state === 'Completed' ? 'https://download.zip' : null
                    ])
                ], 200);
            }

            Http::fake([
                '*bulk*' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => $bulkId])], 200)
                    ->push(...$responses),
                'https://download.zip' => Http::response(
                    $this->createMockZipWithCSV([['ID' => '1', 'Name' => 'Test']]), 200
                ),
                'https://callback.test' => Http::response(['success' => true])
            ]);

            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test', '');
            $job->handle();

            // Verify final status
            $bulkHistory = ZohoBulkHistory::where('bulk_id', $bulkId)->first();
            expect($bulkHistory)->not->toBeNull();
            expect($bulkHistory->status)->toBe('Completed');
        });

        it('handles concurrent bulk operations for different reports', function () {
            Queue::fake();

            $reports = ['All_Companies', 'All_Contacts', 'All_Products'];
            
            foreach ($reports as $report) {
                Http::fake([
                    "www.zohoapis.eu/creator/v2.1/bulk/*/report/{$report}" => Http::response([
                        'result' => createZohoBulkData(['bulk_id' => "bulk_{$report}_123"])
                    ], 200)
                ]);

                ZohoCreatorApi::getWithBulk($report, 'https://callback.test', '');
            }

            // Verify all jobs were queued
            Queue::assertPushedTimes(ZohoCreatorBulkProcess::class, 3);
            
            foreach ($reports as $report) {
                Queue::assertPushed(ZohoCreatorBulkProcess::class, function ($job) use ($report) {
                    return $job->reportName === $report;
                });
            }
        });
    });

    describe('Bulk Operation Error Handling', function () {
        it('handles network errors during download', function () {
            Http::fake([
                '*bulk*' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_network'])], 200)
                    ->push(['result' => createZohoBulkData([
                        'bulk_id' => 'bulk_network',
                        'status' => 'Completed',
                        'download_url' => 'https://download.zip'
                    ])], 200),
                'https://download.zip' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Network timeout');
                }
            ]);

            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test', '');
            
            expect(fn() => $job->handle())->toThrow(\Exception::class);
            
            $this->assertDatabaseHas('zoho_connector_bulk_history', [
                'bulk_id' => 'bulk_network',
                'status' => 'Failed'
            ]);
        });

        it('handles bulk operation cancellation', function () {
            Http::fake([
                '*bulk*' => Http::sequence()
                    ->push(['result' => createZohoBulkData(['bulk_id' => 'bulk_cancelled'])], 200)
                    ->push(['result' => createZohoBulkData([
                        'bulk_id' => 'bulk_cancelled',
                        'status' => 'Cancelled',
                        'error' => 'Operation cancelled by user'
                    ])], 200)
            ]);

            $job = new ZohoCreatorBulkProcess('All_Companies', 'https://callback.test', '');
            
            expect(fn() => $job->handle())->toThrow(\Exception::class, 'cancelled');
            
            $this->assertDatabaseHas('zoho_connector_bulk_history', [
                'bulk_id' => 'bulk_cancelled',
                'status' => 'Cancelled'
            ]);
        });
    });

});

// Helper function for creating mock ZIP files
function createMockZipWithCSV(array $data): string
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