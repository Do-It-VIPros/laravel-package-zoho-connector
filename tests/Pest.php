<?php

use Agencedoit\ZohoConnector\Tests\TestCase;
use Agencedoit\ZohoConnector\Tests\Helpers\ZohoApiMockingHelper;
use Agencedoit\ZohoConnector\Tests\Helpers\SharedTestHelper;

// Configuration de base pour tous les tests
// Les tests Mock ont leur propre configuration dans tests/Feature/Mock/Pest.php

/**
 * Fonctions globales pour la création de données de test
 * Compatibles avec VIPros Elastic Models
 */

function createZohoReportData(array $overrides = []): array
{
    return array_merge([
        'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
        'Added_Time' => now()->toISOString(),
        'Modified_Time' => now()->toISOString(),
    ], $overrides);
}

function createZohoTokenData(array $overrides = []): array
{
    return array_merge([
        'access_token' => '1000.test_access_token_' . fake()->uuid(),
        'refresh_token' => '1000.test_refresh_token_' . fake()->uuid(),
        'expires_in' => 3600,
        'api_domain' => 'https://www.zohoapis.eu',
        'token_type' => 'Bearer',
        'scope' => 'ZohoCreator.report.READ'
    ], $overrides);
}

function createZohoBulkData(array $overrides = []): array
{
    return array_merge([
        'bulk_id' => fake()->uuid(),
        'status' => 'In Progress',
        'download_url' => null,
        'created_time' => now()->toISOString(),
    ], $overrides);
}

function createCompanyData(array $overrides = []): array
{
    return array_merge([
        'ID' => '61757000058385531',
        'denomination' => 'Test Company Ltd',
        'localisation' => [
            'ID' => '61757000000001111',
            'name' => 'Paris',
            'zc_display_value' => 'Paris',
        ],
        'is_test' => true,
        'vipros_number' => 'VP' . fake()->numerify('######'),
        'vipoints_balance' => [
            'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
            'balance' => (string) fake()->randomFloat(2, 0, 10000),
            'zc_display_value' => (string) fake()->randomFloat(2, 0, 10000),
        ],
        'glady_id' => 'GLADY' . fake()->numerify('###'),
        'company_status' => fake()->randomElement(['active', 'inactive', 'pending']),
        'cashback_balance' => [
            'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
            'balance' => fake()->randomFloat(2, 0, 1000),
            'available_balance' => fake()->randomFloat(2, 0, 800),
            'zc_display_value' => (string) fake()->randomFloat(2, 0, 1000),
        ],
        'siren' => fake()->numerify('#########'),
        'siret' => fake()->numerify('##############'),
        'Added_Time' => now()->toISOString(),
        'Modified_Time' => now()->toISOString(),
    ], $overrides);
}

function createContactData(array $overrides = []): array
{
    return array_merge([
        'ID' => '61757000058385550',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@test.com',
        'mobile' => '+33123456789',
        'company' => [
            'ID' => '61757000058385531',
            'denomination' => 'Test Company Ltd',
            'zc_display_value' => 'Test Company Ltd',
        ],
        'is_test' => true,
        'Added_Time' => now()->toISOString(),
        'Modified_Time' => now()->toISOString(),
    ], $overrides);
}

function createProductData(array $overrides = []): array
{
    return array_merge([
        'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
        'name' => fake()->words(3, true),
        'brand' => [
            'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
            'name' => fake()->company(),
            'zc_display_value' => fake()->company(),
        ],
        'localisation' => [
            'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
            'name' => fake()->country(),
            'zc_display_value' => fake()->country(),
        ],
        'is_test' => false,
        'slug' => fake()->slug(),
        'uri' => '/products/' . fake()->slug(),
        'product_category' => [
            'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
            'name' => fake()->words(2, true),
            'zc_display_value' => fake()->words(2, true),
        ],
        'reference' => 'REF-' . fake()->numerify('###'),
        'references' => 'REF-' . fake()->numerify('###') . ',REF-' . fake()->numerify('###'),
        'ean' => fake()->ean13(),
        'description' => fake()->paragraph(),
        'has_cashback' => fake()->boolean(),
        'had_cashback' => fake()->boolean(),
        'images' => [
            [
                'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
                'image_file' => fake()->slug() . '.jpg',
                'image_url' => fake()->imageUrl(),
                'main_image' => true,
                'image_seo' => fake()->words(3, true),
                'zc_display_value' => fake()->slug() . '.jpg',
            ]
        ],
        'meta_title' => fake()->sentence(),
        'meta_description' => fake()->paragraph(),
        'caracs' => [
            [
                'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
                'feature' => 'Color',
                'value' => fake()->colorName(),
                'zc_display_value' => 'Color: ' . fake()->colorName(),
            ]
        ],
        'is_innovative' => fake()->boolean(),
        'innovative_url' => fake()->url(),
        'innovative_argument_1' => fake()->sentence(),
        'innovative_argument_2' => fake()->sentence(),
        'innovative_argument_3' => fake()->sentence(),
        'Added_Time' => now()->toISOString(),
        'Modified_Time' => now()->toISOString(),
    ], $overrides);
}

function createBrandData(array $overrides = []): array
{
    return array_merge([
        'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
        'name' => fake()->company(),
        'Added_Time' => now()->toISOString(),
        'Modified_Time' => now()->toISOString(),
    ], $overrides);
}

function createCashbackData(array $overrides = []): array
{
    return array_merge([
        'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
        'brands' => [
            [
                'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
                'name' => fake()->company(),
                'zc_display_value' => fake()->company(),
            ]
        ],
        'name' => fake()->words(2, true) . ' Promotion',
        'cashback_type' => fake()->randomElement(['percentage', 'fixed', 'tiered']),
        'start_date' => now()->toISOString(),
        'end_date' => now()->addMonths(3)->toISOString(),
        'localisation' => [
            'ID' => (string) fake()->numberBetween(61757000000000000, 61757999999999999),
            'name' => fake()->country(),
            'zc_display_value' => fake()->country(),
        ],
        'Added_Time' => now()->toISOString(),
        'Modified_Time' => now()->toISOString(),
    ], $overrides);
}

function mockZohoSuccessResponse(array $data = []): array
{
    return [
        'code' => 3000,
        'data' => $data ?: [createZohoReportData()],
        'info' => [
            'count' => count($data ?: [createZohoReportData()]),
            'more_records' => false,
        ]
    ];
}

function mockZohoErrorResponse(int $code = 400, string $message = 'Error'): array
{
    return [
        'code' => $code,
        'message' => $message,
        'details' => 'Test error response for code ' . $code,
    ];
}

function mockZohoPaginatedResponse(array $data = [], string $cursor = null): array
{
    return [
        'code' => 3000,
        'data' => $data,
        'info' => [
            'count' => count($data),
            'more_records' => !is_null($cursor),
            'cursor' => $cursor,
        ],
    ];
}

function elasticsearchMockResponse(array $overrides = []): array
{
    return array_merge([
        'took' => 1,
        'timed_out' => false,
        '_shards' => [
            'total' => 1,
            'successful' => 1,
            'skipped' => 0,
            'failed' => 0,
        ],
        'hits' => [
            'total' => [
                'value' => 1,
                'relation' => 'eq',
            ],
            'max_score' => 1.0,
            'hits' => [
                [
                    'id' => fake()->randomNumber(),
                    '_index' => 'test_index',
                    '_type' => '_doc',
                    '_score' => 1.0,
                    '_source' => [
                        'name' => fake()->name(),
                        'created_at' => now()->toISOString(),
                    ],
                ],
            ],
        ],
    ], $overrides);
}

// Additional helper functions for test validation
function loadFixture(string $name): array
{
    $fixtureManager = new \Agencedoit\ZohoConnector\Tests\Helpers\FixtureVersionManager();
    return $fixtureManager->loadFixture($name);
}

function mockZohoResponse(string $report, array $data): void
{
    // This is a placeholder for the actual mocking functionality
    // The actual implementation would use ZohoApiMockingHelper
}

// V2.1 specific helper functions
function createZohoV21GetRequest(array $params = []): array
{
    return array_merge([
        'field_config' => 'quick_view',
        'max_records' => 200,
        'criteria' => null,
        'fields' => null
    ], $params);
}

function createZohoV21CreateRequest(array $data = [], array $options = []): array
{
    return array_merge([
        'data' => $data ?: [createCompanyData()],
        'skip_workflow' => $options['skip_workflow'] ?? [],
        'result' => [
            'fields' => $options['result_fields'] ?? [],
            'message' => true,
            'tasks' => true
        ]
    ], $options);
}

function mockZohoCSVResponse(array $data = []): string
{
    $csv = "ID,Added_Time,Modified_Time,denomination\n";
    foreach ($data ?: [createCompanyData()] as $record) {
        $csv .= "{$record['ID']},{$record['Added_Time']},{$record['Modified_Time']},{$record['denomination']}\n";
    }
    return $csv;
}

function zohoMockResponse(array $overrides = []): array
{
    return array_merge([
        'code' => 3000,
        'data' => [
            [
                'ID' => fake()->randomNumber(),
                'Name' => fake()->name(),
                'Email' => fake()->email(),
                'Added_Time' => now()->toISOString(),
                'Modified_Time' => now()->toISOString(),
            ],
        ],
        'message' => 'Data fetched successfully',
    ], $overrides);
}