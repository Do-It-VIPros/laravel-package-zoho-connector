<?php

namespace Agencedoit\ZohoConnector\Tests\Helpers;

use Illuminate\Support\Facades\Http;

class SharedTestHelper
{
    /**
     * Utilise les helpers de ViprosElasticModels tout en les adaptant pour ZohoConnector
     */
    public static function createZohoCompatibleCompanyData(array $overrides = []): array
    {
        // Réutilise la fonction globale du package ViprosElasticModels si disponible
        if (function_exists('createCompanyData')) {
            $baseData = createCompanyData($overrides);
        } else {
            // Fallback si pas disponible
            $baseData = [
                'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
                'denomination' => fake()->company(),
                'Added_Time' => now()->toISOString(),
                'Modified_Time' => now()->toISOString(),
            ];
        }
        
        // Adaptation spécifique pour les tests ZohoConnector
        return array_merge($baseData, [
            'zoho_created_time' => now()->toISOString(),
            'zoho_modified_time' => now()->toISOString(),
        ], $overrides);
    }

    public static function mockElasticModelsServices(): void
    {
        // Mock des services ViprosElasticModels pour les tests d'intégration
        if (class_exists(\Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class)) {
            app()->bind(
                \Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class,
                function () {
                    return \Mockery::mock(\Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class);
                }
            );
        }
    }

    public static function createTestDataSet(string $entity, int $count = 5): array
    {
        $data = [];
        
        for ($i = 0; $i < $count; $i++) {
            switch ($entity) {
                case 'company':
                    $data[] = createCompanyData(['ID' => (string)(61757000000000000 + $i)]);
                    break;
                case 'contact':
                    $data[] = createContactData(['ID' => (string)(61757000000000000 + $i)]);
                    break;
                case 'product':
                    $data[] = createProductData(['ID' => (string)(61757000000000000 + $i)]);
                    break;
                case 'brand':
                    $data[] = createBrandData(['ID' => (string)(61757000000000000 + $i)]);
                    break;
                default:
                    $data[] = createZohoReportData(['ID' => (string)(61757000000000000 + $i)]);
            }
        }
        
        return $data;
    }

    public static function assertZohoResponse(array $response): void
    {
        expect($response)->toHaveKey('code');
        expect($response)->toHaveKey('data');
        expect($response['code'])->toBe(3000);
        expect($response['data'])->toBeArray();
    }

    public static function assertZohoError(array $response, int $expectedCode = null): void
    {
        expect($response)->toHaveKey('code');
        expect($response)->toHaveKey('message');
        
        if ($expectedCode) {
            expect($response['code'])->toBe($expectedCode);
        } else {
            expect($response['code'])->not->toBe(3000);
        }
    }

    public static function assertZohoDataStructure(array $data): void
    {
        expect($data)->toHaveKey('ID');
        expect($data)->toHaveKey('Added_Time');
        expect($data)->toHaveKey('Modified_Time');
        expect($data['ID'])->toBeString();
        expect($data['Added_Time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
        expect($data['Modified_Time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
    }

    public static function mockHttpCallbacks(): void
    {
        Http::fake([
            '*/callback*' => Http::response(['success' => true, 'received' => true], 200)
        ]);
    }

    public static function createBulkTestData(string $entity, int $recordCount = 100): array
    {
        return self::createTestDataSet($entity, $recordCount);
    }

    public static function simulateApiDelay(int $milliseconds = 100): void
    {
        Http::fake([
            '*' => function () use ($milliseconds) {
                usleep($milliseconds * 1000);
                return Http::response(mockZohoSuccessResponse(), 200);
            }
        ]);
    }

    public static function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'current_formatted' => self::formatBytes(memory_get_usage(true)),
            'peak_formatted' => self::formatBytes(memory_get_peak_usage(true))
        ];
    }

    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public static function cleanupTestFiles(): void
    {
        // Cleanup temporary files created during tests
        $tempPath = storage_path('testing');
        if (is_dir($tempPath)) {
            $files = glob($tempPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    public static function ensureTestDirectories(): void
    {
        $dirs = [
            storage_path('testing'),
            storage_path('testing/zohoconnector'),
            storage_path('logs')
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}