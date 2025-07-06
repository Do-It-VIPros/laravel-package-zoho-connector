# PHASE 1 : FOUNDATION - Infrastructure de tests

## 🎯 Objectifs de la Phase 1
- ✅ Configuration de base des tests (TestCase, Pest)
- ✅ Setup des helpers de mocking
- ✅ Infrastructure d'environnement
- ✅ Fixtures de base

**Durée estimée** : 3-4 jours  
**Commit** : `feat: setup test infrastructure and foundation`

---

## 📁 Structure à créer

```
tests/
├── TestCase.php                     ✅ Base de tests
├── Pest.php                         ✅ Configuration Pest
├── Helpers/
│   ├── ZohoApiMockingHelper.php     ✅ Helper principal mocking
│   ├── CredentialsHelper.php        ✅ Gestion credentials
│   ├── FixtureVersionManager.php    ✅ Versionning fixtures
│   └── SharedTestHelper.php         ✅ Utilities partagées
├── Fixtures/
│   ├── zoho_responses/
│   │   ├── success_responses.json   ✅ Réponses de succès
│   │   ├── error_responses.json     ✅ Réponses d'erreur
│   │   └── auth/
│   │       └── oauth_flow.json      ✅ Flow OAuth
│   └── test_data/
│       ├── companies.json           ✅ Données de test
│       └── contacts.json            ✅ Données de test
└── .env.testing                     ✅ Config environnement test
```

---

## 🔧 Environment Configuration

### 1. Fichier .env.testing
```bash
# Configuration test du package Zoho Connector
APP_ENV=testing

# Zoho Configuration
ZOHO_ENABLED=false
ZOHO_TEST_MODE=true
ZOHO_MOCK_RESPONSES=true
ZOHO_VALIDATE_MOCKS=false

# Credentials de test (non-sensibles)
ZOHO_CLIENT_ID=test_client_id
ZOHO_CLIENT_SECRET=test_secret
ZOHO_USER=test_user
ZOHO_APP_NAME=test_app
ZOHO_ACCOUNT_DOMAIN=eu

# Base de données test
DB_CONNECTION=testing
DB_DATABASE=:memory:

# Désactiver services externes
ES_ENABLED_FOR_TESTS=false
QUEUE_CONNECTION=sync
CACHE_DRIVER=array
```

### 2. TestCase.php - Base de tests
```php
<?php

namespace Agencedoit\ZohoConnector\Tests;

use Agencedoit\ZohoConnector\ZohoConnectorServiceProvider;
use Agencedoit\ZohoConnector\Tests\Helpers\ZohoApiMockingHelper;
use Agencedoit\ZohoConnector\Tests\Helpers\CredentialsHelper;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ZohoConnectorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configuration de base
        $this->setupDatabaseConfig($app);
        $this->setupZohoConfig($app);
        $this->setupLoggingConfig($app);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset des facades entre les tests
        Http::fake();
        Queue::fake();
        Storage::fake();
        
        // Initialisation des mocks Zoho
        ZohoApiMockingHelper::initialize();
        
        // Migration conditionnelle pour les tests Feature
        if ($this->isFeatureTest()) {
            $this->runMigrations();
        }
    }
    
    protected function tearDown(): void
    {
        $this->cleanupBetweenTests();
        parent::tearDown();
    }

    protected function setupDatabaseConfig($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => 'zoho_test_',
            'foreign_key_constraints' => true,
        ]);
    }

    protected function setupZohoConfig($app): void
    {
        // Configuration Zoho pour tests
        $app['config']->set('zohoconnector.test_mode', true);
        $app['config']->set('zohoconnector.mock_responses', true);
        
        // Credentials de test
        $credentials = CredentialsHelper::getTestCredentials();
        $app['config']->set('zohoconnector.client_id', $credentials['client_id']);
        $app['config']->set('zohoconnector.client_secret', $credentials['client_secret']);
    }

    protected function setupLoggingConfig($app): void
    {
        $app['config']->set('logging.default', 'testing');
        $app['config']->set('logging.channels.testing', [
            'driver' => 'single',
            'path' => storage_path('logs/testing.log'),
            'level' => 'debug',
        ]);
    }

    protected function runMigrations(): void
    {
        $this->artisan('migrate:fresh', ['--database' => 'testing']);
        $this->loadLaravelMigrations(['--database' => 'testing']);
    }

    protected function isFeatureTest(): bool
    {
        return str_contains(static::class, 'Feature');
    }

    protected function cleanupBetweenTests(): void
    {
        // Reset HTTP mocks
        Http::fake();
        
        // Reset Queue
        Queue::fake();
        
        // Reset Storage
        Storage::fake();
        
        // Clear any cached data
        if (method_exists($this->app['cache'], 'flush')) {
            $this->app['cache']->flush();
        }
    }

    protected function mockZohoResponse(string $report, array $data): void
    {
        ZohoApiMockingHelper::mockZohoDataResponse($report, $data);
    }

    protected function mockZohoError(int $code = 500, string $message = 'API Error'): void
    {
        Http::fake(['*' => Http::response(['error' => $message], $code)]);
    }
}
```

### 3. Pest.php - Configuration globale
```php
<?php

use Agencedoit\ZohoConnector\Tests\TestCase;
use Agencedoit\ZohoConnector\Tests\Helpers\ZohoApiMockingHelper;
use Agencedoit\ZohoConnector\Tests\Helpers\SharedTestHelper;

// Configuration de base pour tous les tests
uses(TestCase::class)->in('Feature', 'Unit');

// Initialisation des helpers globaux
ZohoApiMockingHelper::initialize();

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
        'access_token' => 'test_access_token_' . fake()->uuid(),
        'refresh_token' => 'test_refresh_token_' . fake()->uuid(),
        'expires_in' => 3600,
        'api_domain' => 'https://www.zohoapis.eu',
        'token_type' => 'Bearer',
    ], $overrides);
}

function createZohoBulkData(array $overrides = []): array
{
    return array_merge([
        'bulk_id' => fake()->uuid(),
        'status' => 'Completed',
        'download_url' => 'https://test.zoho.com/bulk/download/' . fake()->uuid() . '.zip',
        'created_time' => now()->toISOString(),
    ], $overrides);
}

function mockZohoSuccessResponse(array $data = []): array
{
    return array_merge([
        'code' => 3000,
        'data' => $data,
        'info' => [
            'count' => count($data),
            'more_records' => false,
        ],
        'message' => 'Data fetched successfully',
    ], $data ? [] : ['data' => [createZohoReportData()]]);
}

function mockZohoErrorResponse(int $code = 400, string $message = 'Error'): array
{
    return [
        'code' => $code,
        'message' => $message,
        'details' => 'Test error response',
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
```

---

## 🎭 Helpers de Mocking

### 1. ZohoApiMockingHelper.php
```php
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
            // Auth endpoints
            'accounts.zoho.*/oauth/v2/token' => Http::response(createZohoTokenData()),
            
            // Default data endpoints
            'www.zohoapis.*/creator/v2.1/data/*' => Http::response(mockZohoSuccessResponse()),
            
            // Default bulk endpoints
            'www.zohoapis.*/creator/v2.1/bulk/*' => Http::response([
                'result' => createZohoBulkData()
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
            "www.zohoapis.*/creator/v2.1/data/*/report/{$report}" => Http::response(
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
                    'status' => 'Completed'
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
            "www.zohoapis.*/creator/v2.1/data/*/report/{$report}" => Http::sequence(...$responses)
        ]);
    }

    public static function reset(): void
    {
        Http::fake();
        self::setupDefaultMocks();
    }
}
```

### 2. CredentialsHelper.php
```php
<?php

namespace Agencedoit\ZohoConnector\Tests\Helpers;

class CredentialsHelper
{
    public static function getTestCredentials(): array
    {
        if (env('ZOHO_TEST_MODE', true)) {
            return [
                'client_id' => 'mock_client_id_' . fake()->uuid(),
                'client_secret' => 'mock_secret_' . fake()->uuid(),
                'access_token' => 'mock_access_token_' . fake()->uuid(),
                'refresh_token' => 'mock_refresh_token_' . fake()->uuid(),
            ];
        }
        
        // Pour tests d'intégration avec vraie API (CI uniquement)
        return [
            'client_id' => env('ZOHO_TEST_CLIENT_ID'),
            'client_secret' => env('ZOHO_TEST_CLIENT_SECRET'),
            'access_token' => env('ZOHO_TEST_ACCESS_TOKEN'),
            'refresh_token' => env('ZOHO_TEST_REFRESH_TOKEN'),
        ];
    }

    public static function isIntegrationMode(): bool
    {
        return env('ZOHO_INTEGRATION_MODE', false) && !env('ZOHO_TEST_MODE', true);
    }

    public static function validateCredentials(array $credentials): bool
    {
        $required = ['client_id', 'client_secret'];
        
        foreach ($required as $key) {
            if (empty($credentials[$key])) {
                return false;
            }
        }
        
        return true;
    }
}
```

### 3. FixtureVersionManager.php
```php
<?php

namespace Agencedoit\ZohoConnector\Tests\Helpers;

class FixtureVersionManager
{
    private const FIXTURE_VERSION = '1.0';
    private const FIXTURES_PATH = __DIR__ . '/../Fixtures';

    public static function loadFixture(string $name, string $version = null): array
    {
        $version = $version ?? self::FIXTURE_VERSION;
        $path = self::FIXTURES_PATH . "/zoho_responses/v{$version}/{$name}.json";
        
        if (!file_exists($path)) {
            // Fallback to default version
            $path = self::FIXTURES_PATH . "/zoho_responses/{$name}.json";
            
            if (!file_exists($path)) {
                throw new \Exception("Fixture {$name} not found");
            }
        }
        
        $data = json_decode(file_get_contents($path), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in fixture {$name}: " . json_last_error_msg());
        }
        
        return $data;
    }

    public static function saveVersionedFixture(string $name, array $data, string $version = null): void
    {
        $version = $version ?? self::FIXTURE_VERSION;
        
        // Add metadata
        $data['_fixture_meta'] = [
            'version' => $version,
            'created_at' => now()->toISOString(),
            'zoho_api_version' => '2.1',
            'package_version' => self::getPackageVersion()
        ];
        
        $dir = self::FIXTURES_PATH . "/zoho_responses/v{$version}";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(
            "{$dir}/{$name}.json",
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private static function getPackageVersion(): string
    {
        $composerPath = __DIR__ . '/../../composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            return $composer['version'] ?? 'dev';
        }
        
        return 'unknown';
    }
}
```

---

## 📦 Fixtures de base

### 1. zoho_responses/success_responses.json
```json
{
  "_fixture_meta": {
    "version": "1.0",
    "created_at": "2025-01-06T12:00:00Z",
    "description": "Standard successful responses from Zoho Creator API"
  },
  "get_response": {
    "code": 3000,
    "data": [
      {
        "ID": "61757000058385531",
        "Name": "Test Record",
        "Added_Time": "2025-01-01T00:00:00Z",
        "Modified_Time": "2025-01-01T00:00:00Z"
      }
    ],
    "info": {
      "count": 1,
      "more_records": false
    }
  },
  "create_response": {
    "code": 3000,
    "data": {
      "ID": "61757000058385532",
      "Added_Time": "2025-01-06T12:00:00Z"
    }
  },
  "update_response": {
    "code": 3000,
    "data": {
      "ID": "61757000058385531", 
      "Modified_Time": "2025-01-06T12:00:00Z"
    }
  }
}
```

### 2. zoho_responses/error_responses.json
```json
{
  "_fixture_meta": {
    "version": "1.0",
    "created_at": "2025-01-06T12:00:00Z",
    "description": "Common error responses from Zoho Creator API"
  },
  "rate_limit": {
    "code": 4820,
    "message": "Rate limit exceeded",
    "details": "Too many requests"
  },
  "invalid_token": {
    "code": 6000,
    "message": "Invalid access token",
    "details": "Token has expired or is invalid"
  },
  "report_not_found": {
    "code": 3001,
    "message": "Report not found",
    "details": "The specified report does not exist"
  },
  "permission_denied": {
    "code": 6500,
    "message": "Insufficient permission",
    "details": "Access denied for this operation"
  }
}
```

### 3. zoho_responses/auth/oauth_flow.json
```json
{
  "_fixture_meta": {
    "version": "1.0",
    "created_at": "2025-01-06T12:00:00Z",
    "description": "OAuth flow responses"
  },
  "token_request": {
    "access_token": "1000.test_access_token.abc123",
    "refresh_token": "1000.test_refresh_token.def456",
    "expires_in": 3600,
    "api_domain": "https://www.zohoapis.eu",
    "token_type": "Bearer",
    "scope": "ZohoCreator.report.READ"
  },
  "token_refresh": {
    "access_token": "1000.new_access_token.ghi789",
    "expires_in": 3600,
    "api_domain": "https://www.zohoapis.eu",
    "token_type": "Bearer"
  }
}
```

### 4. test_data/companies.json
```json
{
  "_fixture_meta": {
    "version": "1.0",
    "description": "Test company data compatible with VIPros Elastic Models"
  },
  "test_company_1": {
    "ID": "61757000058385531",
    "denomination": "VIPros Test Company Ltd",
    "siren": "123456789",
    "siret": "12345678901234",
    "vipros_number": "VP123456",
    "company_status": "active",
    "localisation": {
      "ID": "61757000000001111",
      "name": "France",
      "zc_display_value": "France"
    },
    "vipoints_balance": {
      "ID": "61757000000002222",
      "balance": "1000.50",
      "zc_display_value": "1000.50"
    },
    "Added_Time": "2025-01-01T00:00:00Z",
    "Modified_Time": "2025-01-01T00:00:00Z"
  }
}
```

---

## ✅ Checklist Phase 1

- [ ] **Configuration environnement**
  - [ ] Créer `.env.testing`
  - [ ] Configurer variables test
  - [ ] Tester isolation environnement

- [ ] **Classes de base**
  - [ ] Implémenter `TestCase.php`
  - [ ] Configurer `Pest.php`
  - [ ] Tester héritage et setup

- [ ] **Helpers de mocking**
  - [ ] Créer `ZohoApiMockingHelper.php`
  - [ ] Créer `CredentialsHelper.php`
  - [ ] Créer `FixtureVersionManager.php`
  - [ ] Tester fonctionnement mocks

- [ ] **Fixtures de données**
  - [ ] Créer fixtures success/error
  - [ ] Créer fixtures OAuth
  - [ ] Créer données de test
  - [ ] Valider format JSON

- [ ] **Tests de validation**
  - [ ] Test de chargement TestCase
  - [ ] Test de configuration Pest
  - [ ] Test de chargement fixtures
  - [ ] Test des helpers mocking

**Commit final Phase 1** : `feat: setup test infrastructure and foundation - all base components ready`