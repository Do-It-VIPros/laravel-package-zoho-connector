<?php

namespace Agencedoit\ZohoConnector\Tests\Helpers;

use Illuminate\Support\Facades\Http;

class ZohoApiMockingHelper
{
    private static bool $initialized = false;

    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        // Setup des mocks par défaut
        self::setupDefaultMocks();
        self::$initialized = true;
    }

    public static function setupDefaultMocks(): void
    {
        Http::fake([
            // Auth endpoints (correct OAuth2 structure)
            'accounts.zoho.*/oauth/v2/token' => Http::response(createZohoTokenData()),
            
            // Data endpoints (v2.1 structure: /creator/v2.1/data/{owner}/{app}/report/{report})
            'www.zohoapis.*/creator/v2.1/data/*/*/report/*' => Http::response([
                'code' => 3000,
                'data' => [createZohoReportData()],
                'info' => [
                    'count' => 1,
                    'more_records' => false
                ]
            ]),
            
            // Form endpoints (v2.1 structure: /creator/v2.1/data/{owner}/{app}/form/{form})
            'www.zohoapis.*/creator/v2.1/data/*/*/form/*' => Http::response([
                'code' => 3000,
                'data' => [createZohoReportData()]
            ]),
            
            // File upload endpoints
            'www.zohoapis.*/creator/v2.1/data/*/*/report/*/*/upload' => Http::response([
                'code' => 3000,
                'filename' => 'test_file.jpg',
                'filepath' => 'unique_test_file.jpg',
                'message' => 'File uploaded successfully !'
            ]),
            
            // Meta endpoints (forms and applications)
            'www.zohoapis.*/creator/v2.1/meta/*/*' => Http::response([
                'code' => 3000,
                'applications' => [],
                'forms' => []
            ]),
        ]);
    }

    public static function mockSuccessfulAuth(string $domain = 'eu'): void
    {
        Http::fake([
            "accounts.zoho.{$domain}/oauth/v2/token" => Http::response(createZohoTokenData([
                'api_domain' => "https://www.zohoapis.{$domain}",
            ]), 200)
        ]);
    }

    public static function mockZohoDataResponse(string $report, array $data): void
    {
        Http::fake([
            "www.zohoapis.*/creator/v2.1/data/*/report/{$report}*" => Http::response(
                mockZohoSuccessResponse($data), 200
            )
        ]);
    }

    public static function mockZohoBulkResponse(string $report, string $bulkId): void
    {
        Http::fake([
            "www.zohoapis.*/creator/v2.1/bulk/*/report/{$report}" => Http::sequence()
                ->push(['result' => createZohoBulkData(['bulk_id' => $bulkId])], 200)
                ->push(['result' => createZohoBulkData([
                    'bulk_id' => $bulkId,
                    'status' => 'Completed',
                    'download_url' => 'https://test.zoho.com/download/' . $bulkId . '.zip'
                ])], 200)
        ]);
    }

    public static function mockZohoError(int $code = 500, string $message = 'API Error'): void
    {
        Http::fake(['*' => Http::response(mockZohoErrorResponse($code, $message), $code)]);
    }

    public static function mockZohoPagination(string $report, array $pages): void
    {
        $responses = [];
        foreach ($pages as $i => $pageData) {
            $cursor = isset($pages[$i + 1]) ? "cursor_page_" . ($i + 1) : null;
            $responses[] = Http::response(mockZohoPaginatedResponse($pageData, $cursor), 200);
        }
        
        Http::fake([
            "www.zohoapis.*/creator/v2.1/data/*/report/{$report}*" => Http::sequence(...$responses)
        ]);
    }

    public static function mockZohoCreate(string $form, array $responseData): void
    {
        Http::fake([
            "www.zohoapis.*/creator/v2.1/data/*/form/{$form}" => Http::response([
                'code' => 3000,
                'data' => $responseData
            ], 200)
        ]);
    }

    public static function mockZohoUpdate(string $report, string $id, array $responseData): void
    {
        Http::fake([
            "www.zohoapis.*/creator/v2.1/data/*/report/{$report}/{$id}" => Http::response([
                'code' => 3000,
                'data' => $responseData
            ], 200)
        ]);
    }

    public static function mockZohoUpload(string $report, string $id, string $field): void
    {
        Http::fake([
            "www.zohoapis.*/creator/v2.1/data/*/report/{$report}/{$id}/upload" => Http::response([
                'code' => 3000,
                'message' => 'File uploaded successfully',
                'data' => [
                    'file_id' => 'file_' . uniqid(),
                    'field_name' => $field
                ]
            ], 200)
        ]);
    }

    public static function mockCustomFunction(string $functionName, array $responseData): void
    {
        Http::fake([
            "www.zohoapis.*/creator/custom/*/{$functionName}*" => Http::response($responseData, 200)
        ]);
    }

    public static function mockMetadata(string $type, array $data): void
    {
        Http::fake([
            "www.zohoapis.*/creator/v2.1/meta/*/{$type}" => Http::response($data, 200)
        ]);
    }

    public static function mockAuthorizationFlow(): void
    {
        Http::fake([
            'accounts.zoho.*/oauth/v2/auth*' => Http::response('', 302, [
                'Location' => 'http://localhost/zoho/request-code-response?code=auth_code_123'
            ]),
            'accounts.zoho.*/oauth/v2/token' => Http::response(createZohoTokenData(), 200)
        ]);
    }

    public static function mockTokenRefresh(): void
    {
        Http::fake([
            'accounts.zoho.*/oauth/v2/token' => Http::response(createZohoTokenData([
                'access_token' => '1000.new_access_token.' . uniqid()
            ]), 200)
        ]);
    }

    public static function mockRateLimitError(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'code' => 2955,
                    'message' => 'You have reached your API call limit for a minute'
                ], 429)
                ->push([
                    'code' => 3000,
                    'data' => [createZohoReportData()],
                    'info' => ['count' => 1, 'more_records' => false]
                ], 200)
        ]);
    }

    public static function mockNetworkError(): void
    {
        Http::fake([
            '*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Network timeout');
            }
        ]);
    }

    public static function mockInvalidToken(): void
    {
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'code' => 1030,
                    'message' => 'Authorization Failure. The access token is either invalid or has expired'
                ], 401)
                ->push(createZohoTokenData(), 200) // Token refresh
                ->push([
                    'code' => 3000,
                    'data' => [createZohoReportData()],
                    'info' => ['count' => 1, 'more_records' => false]
                ], 200)
        ]);
    }

    public static function reset(): void
    {
        Http::fake();
        self::setupDefaultMocks();
    }

    /**
     * Simulate API rate limiting (50 calls per minute)
     */
    public static function simulateRateLimit(int $callCount = 50): void
    {
        $responses = [];
        
        // First 50 calls succeed
        for ($i = 0; $i < $callCount; $i++) {
            $responses[] = Http::response([
                'code' => 3000,
                'data' => [createZohoReportData()],
                'info' => ['count' => 1, 'more_records' => false]
            ], 200);
        }
        
        // 51st call fails with rate limit
        $responses[] = Http::response([
            'code' => 2955,
            'message' => 'You have reached your API call limit for a minute'
        ], 429);
        
        Http::fake([
            '*' => Http::sequence(...$responses)
        ]);
    }

    /**
     * Mock file upload with size and type validation
     */
    public static function mockFileUpload(string $fileType = 'image', int $fileSize = 1024): void
    {
        $maxSize = $fileType === 'image' ? 10485760 : 52428800; // 10MB for images, 50MB for others
        
        if ($fileSize > $maxSize) {
            Http::fake([
                'www.zohoapis.*/creator/v2.1/data/*/*/report/*/*/upload' => Http::response([
                    'code' => 400,
                    'message' => 'File size exceeds maximum allowed limit'
                ], 400)
            ]);
        } else {
            Http::fake([
                'www.zohoapis.*/creator/v2.1/data/*/*/report/*/*/upload' => Http::response([
                    'code' => 3000,
                    'filename' => "test_file.{$fileType}",
                    'filepath' => "unique_test_file_{$fileSize}.{$fileType}",
                    'message' => 'File uploaded successfully !'
                ], 200)
            ]);
        }
    }

    /**
     * Mock a complete bulk operation workflow
     */
    public static function mockBulkWorkflow(string $report, array $csvData): void
    {
        $bulkId = 'bulk_' . uniqid();
        
        Http::fake([
            // Create bulk
            "www.zohoapis.*/creator/v2.1/bulk/*/report/{$report}" => Http::sequence()
                ->push(['result' => createZohoBulkData(['bulk_id' => $bulkId])], 200)
                ->push(['result' => createZohoBulkData([
                    'bulk_id' => $bulkId,
                    'status' => 'Completed',
                    'download_url' => "https://test.zoho.com/download/{$bulkId}.zip"
                ])], 200),
            
            // Download ZIP
            "https://test.zoho.com/download/{$bulkId}.zip" => Http::response(
                self::createMockZipWithCSV($csvData), 200, [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => "attachment; filename=\"{$bulkId}.zip\""
                ]
            )
        ]);
    }

    /**
     * Create a mock ZIP file with CSV content
     */
    private static function createMockZipWithCSV(array $data): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_zip');
        $zip = new \ZipArchive();
        $zip->open($tempFile, \ZipArchive::CREATE);
        
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
}