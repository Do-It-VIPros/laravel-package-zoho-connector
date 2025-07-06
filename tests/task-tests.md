# TASK-TESTS.md

## Stratégie de tests pour le package Zoho Connector

Ce document définit une approche rigoureuse et intelligente pour structurer les tests du package Zoho Connector, en harmonie avec ceux du package VIPros Elastic Models.

---

## 📊 Analyse de l'existant

### Package VIPros Elastic Models - Architecture des tests
- **Framework** : Pest PHP avec structure moderne
- **Coverage** : Tests unitaires et d'intégration complets
- **Mocking** : ZohoMockingHelper sophistiqué pour découpler les dépendances
- **Fixtures** : Données de test réalistes avec `createCompanyData()`, `createContactData()`, etc.
- **Configuration** : TestCase robuste avec support Elasticsearch et mocking Zoho

### Package Zoho Connector - État actuel
- **Tests limités** : Seulement 2 fichiers de test basiques
- **Couverture insuffisante** : Pas de tests pour les services core
- **Mocking incomplet** : Tests dépendants des services externes
- **Structure obsolète** : Tests unitaires et feature mélangés

---

## 🎯 Objectifs de la refonte

### 1. Complémentarité avec VIPros Elastic Models
- **Symbiose** : Tests qui valident l'intégration entre les deux packages
- **Réutilisation** : Partage des helpers et fixtures existants
- **Cohérence** : Structure et patterns identiques

### 2. Coverage complète du Zoho Connector
- **Services Core** : ZohoCreatorService, ZohoTokenManagement
- **API Layer** : Toutes les méthodes CRUD et bulk
- **Authentication** : OAuth2 flow complet
- **Error Handling** : Gestion d'erreurs et retry logic

### 3. Qualité et maintenabilité
- **Isolation** : Tests 100% découplés des services externes
- **Performance** : Exécution rapide avec mocks appropriés
- **Fiabilité** : Tests stables et reproductibles

---

## 🏗️ Architecture proposée

### Structure des dossiers
```
tests/
├── Feature/
│   ├── Authentication/
│   │   ├── OAuthFlowTest.php
│   │   ├── TokenRefreshTest.php
│   │   └── TokenManagementTest.php
│   ├── Api/
│   │   ├── CrudOperationsTest.php
│   │   ├── BulkOperationsTest.php
│   │   ├── CustomFunctionsTest.php
│   │   └── MetadataTest.php
│   ├── Http/
│   │   ├── ZohoControllerTest.php
│   │   └── RoutesTest.php
│   └── Integration/
│       ├── ElasticModelsIntegrationTest.php
│       └── EndToEndSyncTest.php
├── Unit/
│   ├── Services/
│   │   ├── ZohoCreatorServiceTest.php
│   │   ├── ZohoTokenManagementTest.php
│   │   └── ZohoServiceCheckerTest.php
│   ├── Models/
│   │   ├── ZohoConnectorTokenTest.php
│   │   └── ZohoBulkHistoryTest.php
│   ├── Jobs/
│   │   └── ZohoCreatorBulkProcessTest.php
│   ├── Facades/
│   │   └── ZohoCreatorFacadeTest.php
│   └── Helpers/
│       └── ResponseValidationTest.php
├── Helpers/
│   ├── ZohoApiMockingHelper.php
│   ├── TokenTestHelper.php
│   └── BulkProcessTestHelper.php
├── Fixtures/
│   ├── zoho_responses/
│   │   ├── success_responses.json
│   │   ├── error_responses.json
│   │   ├── bulk_responses.json
│   │   └── meta_responses.json
│   ├── auth/
│   │   ├── oauth_flow.json
│   │   └── token_responses.json
│   └── test_data/
│       ├── companies.json
│       ├── contacts.json
│       └── reports.json
├── Pest.php
├── TestCase.php
└── task-tests.md (ce document)
```

---

## 🔧 Environment Configuration

### Gestion des credentials Zoho

#### Variables d'environnement pour tests
```bash
# .env.testing - Configuration locale
ZOHO_ENABLED=false                    # Désactive les vraies API calls
ZOHO_CLIENT_ID=test_client_id         # Credentials de test
ZOHO_CLIENT_SECRET=test_secret        # Non-sensibles pour tests
ZOHO_USER=test_user                   # Utilisateur de test
ZOHO_APP_NAME=test_app                # Application de test
ZOHO_ACCOUNT_DOMAIN=eu                # Domaine par défaut

# Variables spécifiques aux tests
ZOHO_TEST_MODE=true                   # Active les mocks complets
ZOHO_MOCK_RESPONSES=true              # Force l'utilisation des fixtures
ZOHO_VALIDATE_MOCKS=false             # Tests périodiques avec vraie API
```

#### Stratégie de stockage sécurisé des API keys
```php
// tests/Helpers/CredentialsHelper.php
class CredentialsHelper
{
    public static function getTestCredentials(): array
    {
        if (env('ZOHO_TEST_MODE', true)) {
            return [
                'client_id' => 'mock_client_id',
                'client_secret' => 'mock_secret',
                'access_token' => 'mock_access_token',
            ];
        }
        
        // Pour tests d'intégration avec vraie API (CI seulement)
        return [
            'client_id' => env('ZOHO_TEST_CLIENT_ID'),
            'client_secret' => env('ZOHO_TEST_CLIENT_SECRET'),
            'access_token' => env('ZOHO_TEST_ACCESS_TOKEN'),
        ];
    }
}
```

### Configuration multi-environnements

#### Setup local avec .env.testing
```php
// Configuration automatique pour développement local
protected function getEnvironmentSetUp($app): void
{
    // Force mode test local
    $app['config']->set('zohoconnector.test_mode', true);
    $app['config']->set('zohoconnector.mock_responses', true);
    
    // Configuration base de données isolée
    $app['config']->set('database.default', 'testing');
    $app['config']->set('database.connections.testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => 'test_',
    ]);
}
```

#### Configuration CI/CD avec variables d'environnement
```yaml
# .github/workflows/tests.yml ou équivalent
env:
  ZOHO_ENABLED: false
  ZOHO_TEST_MODE: true
  ZOHO_MOCK_RESPONSES: true
  # Credentials réels pour tests d'intégration (secrets)
  ZOHO_INTEGRATION_CLIENT_ID: ${{ secrets.ZOHO_TEST_CLIENT_ID }}
  ZOHO_INTEGRATION_SECRET: ${{ secrets.ZOHO_TEST_SECRET }}
```

### Setup base de données et Elasticsearch

#### Configuration SQLite in-memory pour tests unitaires
```php
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
```

#### Setup Elasticsearch pour tests d'intégration
```php
protected function setupElasticsearchConfig($app): void
{
    if (env('ES_ENABLED_FOR_TESTS', false)) {
        $app['config']->set('database.connections.elasticsearch', [
            'driver' => 'elasticsearch',
            'hosts' => [env('ES_TEST_HOSTS', 'http://localhost:9200')],
            'index_prefix' => 'test_zoho_',
        ]);
    } else {
        // Mock Elasticsearch pour tests unitaires
        $this->mockElasticsearch();
    }
}
```

#### Stratégie de cleanup entre tests
```php
protected function cleanupBetweenTests(): void
{
    // Nettoyage base de données
    if ($this->app['config']->get('database.default') === 'testing') {
        DB::beginTransaction();
        DB::rollBack(); // Rollback automatique
    }
    
    // Nettoyage indices Elasticsearch de test
    if (env('ES_ENABLED_FOR_TESTS', false)) {
        $this->cleanupTestIndices();
    }
    
    // Reset des mocks
    Http::fake();
    Queue::fake();
    Storage::fake();
}
```

---

## 🔧 Configuration technique

### TestCase.php - Base de tests
```php
<?php

namespace Agencedoit\ZohoConnector\Tests;

use Agencedoit\ZohoConnector\ZohoConnectorServiceProvider;
use Agencedoit\ViprosElasticModels\Tests\Helpers\ZohoMockingHelper;
use Orchestra\Testbench\TestCase as Orchestra;

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
        // Configuration de test complète
        $this->setupDatabaseConfig($app);
        $this->setupZohoConfig($app);
        $this->setupLoggingConfig($app);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialisation des mocks
        ZohoMockingHelper::initialize();
        $this->setupZohoMocks();
        
        // Migration conditionnelle pour les tests Feature
        if ($this->isFeatureTest()) {
            $this->runMigrations();
        }
    }
    
    // Méthodes utilitaires partagées...
}
```

### Pest.php - Configuration globale
```php
<?php

use Agencedoit\ZohoConnector\Tests\TestCase;
use Agencedoit\ZohoConnector\Tests\Helpers\ZohoApiMockingHelper;

uses(TestCase::class)->in('Feature', 'Unit');

// Initialisation des helpers
ZohoApiMockingHelper::initialize();

// Fonctions globales pour la création de données de test
function createZohoReportData(array $overrides = []): array { ... }
function createZohoTokenData(array $overrides = []): array { ... }
function createZohoBulkData(array $overrides = []): array { ... }
function mockZohoSuccessResponse(array $data = []): array { ... }
function mockZohoErrorResponse(int $code = 400, string $message = 'Error'): array { ... }
```

---

## 🧪 Stratégies de test par composant

### 1. Tests de Service (ZohoCreatorService)

#### Tests unitaires prioritaires :
- **Méthodes CRUD** : `get()`, `getAll()`, `getByID()`, `create()`, `update()`
- **Operations bulk** : `createBulk()`, `readBulk()`, `downloadBulk()`, `getWithBulk()`
- **Custom functions** : `customFunctionGet()`, `customFunctionPost()`
- **Metadata** : `getFormsMeta()`, `getFieldsMeta()`, `getReportsMeta()`

#### Patterns de test :
```php
describe('ZohoCreatorService CRUD Operations', function () {
    beforeEach(function () {
        $this->service = new ZohoCreatorService();
        $this->mockZohoApiResponses();
    });

    it('retrieves records with pagination', function () {
        // Arrange
        $mockResponse = mockZohoSuccessResponse([
            'data' => [createZohoReportData()],
            'info' => ['more_records' => true, 'cursor' => 'abc123']
        ]);
        
        Http::fake(['*' => Http::response($mockResponse)]);
        
        // Act
        $cursor = '';
        $result = $this->service->get('test_report', '', $cursor);
        
        // Assert
        expect($result)->toBeArray();
        expect($cursor)->toBe('abc123');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/creator/v2.1/data/');
        });
    });
});
```

### 2. Tests d'Authentication (ZohoTokenManagement)

#### Scénarios critiques :
- **OAuth2 Flow** : Authorization code → Access token
- **Token Refresh** : Renouvellement automatique
- **Token Storage** : Sauvegarde en base de données
- **Error Handling** : Gestion des erreurs d'auth

```php
describe('OAuth2 Authentication Flow', function () {
    it('completes OAuth flow successfully', function () {
        // Test du flow complet avec mocks appropriés
        $this->mockOAuthResponses();
        
        $result = $this->service->requestAuthorizationCode();
        expect($result)->toContain('https://accounts.zoho.eu/oauth/v2/auth');
        
        $tokens = $this->service->exchangeCodeForTokens('test_code');
        expect($tokens)->toHaveKeys(['access_token', 'refresh_token']);
    });
});
```

### 3. Tests d'intégration avec VIPros Elastic Models

#### Objectifs :
- **Sync End-to-End** : Zoho → Elasticsearch via les deux packages
- **Data Consistency** : Cohérence des données entre systèmes
- **Error Propagation** : Gestion d'erreurs cross-package

```php
describe('VIPros Elastic Models Integration', function () {
    it('syncs company data from Zoho to Elasticsearch', function () {
        // Arrange
        $companyData = createCompanyData();
        $this->mockZohoResponse('company_report', [$companyData]);
        
        // Act - Via commande de sync du package Elastic Models
        $this->artisan('datamanager:sync', ['index' => 'company'])
             ->assertSuccessful();
        
        // Assert - Vérification dans Elasticsearch
        $elasticCompany = \Agencedoit\ViprosElasticModels\Models\ElasticApi\Company::find($companyData['ID']);
        expect($elasticCompany)->not->toBeNull();
        expect($elasticCompany->denomination)->toBe($companyData['denomination']);
    });
});
```

### 4. Tests de Jobs et Queue

#### ZohoCreatorBulkProcess :
- **Job Execution** : Traitement bulk complet
- **Error Handling** : Retry et failure handling
- **File Processing** : ZIP extraction et JSON transformation

```php
describe('ZohoCreatorBulkProcess Job', function () {
    it('processes bulk operation successfully', function () {
        // Arrange
        Queue::fake();
        $bulkData = createZohoBulkData();
        
        // Act
        ZohoCreatorBulkProcess::dispatch('test_report', 'http://callback.test', '');
        
        // Assert
        Queue::assertPushed(ZohoCreatorBulkProcess::class);
        
        // Execute job
        $job = new ZohoCreatorBulkProcess('test_report', 'http://callback.test', '');
        $job->handle();
        
        // Verify file operations and callbacks
        expect(Storage::exists('zohoconnector/test_report.json'))->toBeTrue();
    });
});
```

---

## 🎭 Mocking Strategy

### ZohoApiMockingHelper.php
```php
<?php

namespace Agencedoit\ZohoConnector\Tests\Helpers;

use Illuminate\Support\Facades\Http;

class ZohoApiMockingHelper
{
    public static function mockSuccessfulAuth(): void
    {
        Http::fake([
            'accounts.zoho.eu/oauth/v2/token' => Http::response([
                'access_token' => 'test_access_token',
                'refresh_token' => 'test_refresh_token',
                'expires_in' => 3600,
                'api_domain' => 'https://www.zohoapis.eu',
                'token_type' => 'Bearer'
            ], 200)
        ]);
    }

    public static function mockZohoDataResponse(string $report, array $data): void
    {
        Http::fake([
            "www.zohoapis.eu/creator/v2.1/data/*/report/{$report}" => Http::response([
                'code' => 3000,
                'data' => $data,
                'info' => [
                    'count' => count($data),
                    'more_records' => false
                ]
            ], 200)
        ]);
    }

    public static function mockZohoBulkResponse(string $report, string $bulkId): void
    {
        Http::fake([
            "www.zohoapis.eu/creator/v2.1/bulk/*/report/{$report}" => Http::sequence()
                ->push(['result' => ['bulk_id' => $bulkId]], 200)
                ->push(['result' => ['status' => 'Completed', 'download_url' => 'http://test.zip']], 200)
        ]);
    }
}
```

### Validation des mocks vs API réelle

#### Tests périodiques avec vraie API
```php
// tests/Integration/ApiValidationTest.php
class ApiValidationTest extends TestCase
{
    /** @group integration */
    /** @group slow */
    public function test_mock_responses_match_real_api_structure()
    {
        // Test uniquement si ZOHO_VALIDATE_MOCKS=true
        if (!env('ZOHO_VALIDATE_MOCKS', false)) {
            $this->markTestSkipped('Mock validation disabled');
        }
        
        // Test avec de vraies credentials
        $realResponse = $this->callRealZohoApi('company_report');
        $mockResponse = $this->getMockResponse('company_report');
        
        // Validation de la structure
        $this->assertSameStructure($realResponse, $mockResponse);
        $this->assertSameDataTypes($realResponse, $mockResponse);
    }
    
    private function assertSameStructure(array $real, array $mock): void
    {
        $realKeys = $this->extractStructureKeys($real);
        $mockKeys = $this->extractStructureKeys($mock);
        
        $this->assertEquals(
            $realKeys, 
            $mockKeys, 
            'Mock response structure differs from real API'
        );
    }
    
    private function callRealZohoApi(string $report): array
    {
        // Configuration temporaire avec vraies credentials
        config([
            'zohoconnector.client_id' => env('ZOHO_INTEGRATION_CLIENT_ID'),
            'zohoconnector.client_secret' => env('ZOHO_INTEGRATION_SECRET'),
        ]);
        
        return ZohoCreatorApi::get($report, '', $cursor = '');
    }
}
```

#### Processus de mise à jour des responses fixtures
```php
// tests/Helpers/FixtureUpdater.php
class FixtureUpdater
{
    /**
     * Script pour mettre à jour les fixtures avec les vraies réponses API
     */
    public static function updateFixturesFromRealApi(): void
    {
        $endpoints = [
            'company_report',
            'contact_report', 
            'product_report',
            'brand_report'
        ];
        
        foreach ($endpoints as $endpoint) {
            try {
                $realResponse = self::fetchRealResponse($endpoint);
                $sanitizedResponse = self::sanitizeResponse($realResponse);
                
                self::saveFixture($endpoint, $sanitizedResponse);
                
                echo "✅ Updated fixture for {$endpoint}\n";
            } catch (\Exception $e) {
                echo "❌ Failed to update {$endpoint}: {$e->getMessage()}\n";
            }
        }
    }
    
    private static function sanitizeResponse(array $response): array
    {
        // Anonymisation des données sensibles
        return self::recursiveAnonymize($response, [
            'email' => 'test@example.com',
            'phone' => '+33123456789',
            'siren' => '123456789',
            'siret' => '12345678901234'
        ]);
    }
    
    private static function saveFixture(string $endpoint, array $data): void
    {
        $path = __DIR__ . "/../Fixtures/zoho_responses/{$endpoint}.json";
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        
        // Versioning des fixtures
        $versionPath = __DIR__ . "/../Fixtures/zoho_responses/versions/{$endpoint}_" . date('Y-m-d') . ".json";
        file_put_contents($versionPath, json_encode($data, JSON_PRETTY_PRINT));
    }
}
```

#### Détection automatique des changements d'API
```php
// tests/Jobs/ApiChangeDetectionJob.php
class ApiChangeDetectionJob implements ShouldQueue
{
    /**
     * Job qui s'exécute périodiquement pour détecter les changements d'API
     */
    public function handle(): void
    {
        if (!env('ZOHO_VALIDATE_MOCKS', false)) {
            return;
        }
        
        $endpoints = config('zohoconnector.validation.endpoints', []);
        $changes = [];
        
        foreach ($endpoints as $endpoint) {
            $changeDetected = $this->detectChanges($endpoint);
            if ($changeDetected) {
                $changes[] = $endpoint;
            }
        }
        
        if (!empty($changes)) {
            $this->notifyDevelopers($changes);
        }
    }
    
    private function detectChanges(string $endpoint): bool
    {
        try {
            $currentResponse = $this->fetchCurrentApiResponse($endpoint);
            $lastKnownResponse = $this->getLastKnownResponse($endpoint);
            
            return !$this->responsesAreEquivalent($currentResponse, $lastKnownResponse);
        } catch (\Exception $e) {
            Log::warning("Failed to detect changes for {$endpoint}: {$e->getMessage()}");
            return false;
        }
    }
    
    private function notifyDevelopers(array $changedEndpoints): void
    {
        Mail::to(config('zohoconnector.notification.email'))
            ->send(new ApiChangeNotification($changedEndpoints));
        
        // Slack notification
        Http::post(config('zohoconnector.slack.webhook'), [
            'text' => "🚨 Zoho API changes detected for: " . implode(', ', $changedEndpoints)
        ]);
    }
}
```

### Stratégie de mise à jour des fixtures

#### Versionning des fixtures JSON
```php
// tests/Fixtures/FixtureVersionManager.php
class FixtureVersionManager
{
    private const FIXTURE_VERSION = '1.0';
    
    public static function loadFixture(string $name, string $version = null): array
    {
        $version = $version ?? self::FIXTURE_VERSION;
        $path = __DIR__ . "/zoho_responses/v{$version}/{$name}.json";
        
        if (!file_exists($path)) {
            throw new \Exception("Fixture {$name} v{$version} not found");
        }
        
        $data = json_decode(file_get_contents($path), true);
        
        // Validation de la version du fixture
        if (!isset($data['_fixture_meta']['version'])) {
            throw new \Exception("Fixture {$name} missing version metadata");
        }
        
        return $data;
    }
    
    public static function saveVersionedFixture(string $name, array $data, string $version): void
    {
        $data['_fixture_meta'] = [
            'version' => $version,
            'created_at' => now()->toISOString(),
            'zoho_api_version' => '2.1',
            'package_version' => self::getPackageVersion()
        ];
        
        $dir = __DIR__ . "/zoho_responses/v{$version}";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(
            "{$dir}/{$name}.json",
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
```

#### Scripts de génération automatique
```php
// tests/Console/GenerateFixturesCommand.php
class GenerateFixturesCommand extends Command
{
    protected $signature = 'zoho:generate-fixtures 
                           {--validate : Validate against real API}
                           {--update : Update existing fixtures}
                           {--endpoints=* : Specific endpoints to generate}';
    
    protected $description = 'Generate test fixtures from Zoho API responses';
    
    public function handle(): void
    {
        if ($this->option('validate') && !env('ZOHO_INTEGRATION_ENABLED')) {
            $this->error('Validation requires ZOHO_INTEGRATION_ENABLED=true');
            return;
        }
        
        $endpoints = $this->option('endpoints') ?: $this->getDefaultEndpoints();
        
        $this->info('Generating fixtures for: ' . implode(', ', $endpoints));
        
        foreach ($endpoints as $endpoint) {
            $this->generateFixtureForEndpoint($endpoint);
        }
        
        $this->info('✅ Fixtures generation completed');
    }
    
    private function generateFixtureForEndpoint(string $endpoint): void
    {
        try {
            if ($this->option('validate')) {
                $response = $this->fetchRealResponse($endpoint);
            } else {
                $response = $this->generateMockResponse($endpoint);
            }
            
            $sanitized = $this->sanitizeForTesting($response);
            
            FixtureVersionManager::saveVersionedFixture(
                $endpoint,
                $sanitized,
                config('zohoconnector.fixture_version', '1.0')
            );
            
            $this->line("Generated fixture for {$endpoint}");
        } catch (\Exception $e) {
            $this->error("Failed to generate fixture for {$endpoint}: {$e->getMessage()}");
        }
    }
}
```

### Gestion des edge cases Zoho-spécifiques

#### Mocks pour différents domaines (.eu, .com, .in)
```php
// tests/Helpers/ZohoDomainMockingHelper.php
class ZohoDomainMockingHelper
{
    private static array $domainConfigs = [
        'eu' => [
            'accounts_url' => 'accounts.zoho.eu',
            'api_url' => 'www.zohoapis.eu',
            'timezone' => 'Europe/Paris'
        ],
        'com' => [
            'accounts_url' => 'accounts.zoho.com',
            'api_url' => 'www.zohoapis.com',
            'timezone' => 'America/New_York'
        ],
        'in' => [
            'accounts_url' => 'accounts.zoho.in',
            'api_url' => 'www.zohoapis.in',
            'timezone' => 'Asia/Kolkata'
        ]
    ];
    
    public static function mockForDomain(string $domain): void
    {
        $config = self::$domainConfigs[$domain] ?? self::$domainConfigs['eu'];
        
        Http::fake([
            "{$config['accounts_url']}/oauth/v2/token" => Http::response([
                'access_token' => "test_token_{$domain}",
                'api_domain' => "https://{$config['api_url']}",
            ]),
            "{$config['api_url']}/creator/v2.1/data/*" => Http::response([
                'code' => 3000,
                'data' => self::generateDomainSpecificData($domain),
            ])
        ]);
    }
    
    private static function generateDomainSpecificData(string $domain): array
    {
        return [
            'domain' => $domain,
            'timezone' => self::$domainConfigs[$domain]['timezone'],
            'currency' => self::getCurrencyForDomain($domain),
            'date_format' => self::getDateFormatForDomain($domain)
        ];
    }
}
```

#### Gestion des erreurs API spécifiques
```php
// tests/Helpers/ZohoErrorMockingHelper.php
class ZohoErrorMockingHelper
{
    public static function mockSpecificErrors(): void
    {
        Http::fake([
            // Rate limiting
            '*rate*' => Http::response([
                'code' => 4820,
                'message' => 'Rate limit exceeded'
            ], 429),
            
            // Invalid token
            '*invalid_token*' => Http::response([
                'code' => 6000,
                'message' => 'Invalid access token'
            ], 401),
            
            // Maintenance mode
            '*maintenance*' => Http::response([
                'code' => 5000,
                'message' => 'Service temporarily unavailable'
            ], 503),
            
            // Invalid report
            '*invalid_report*' => Http::response([
                'code' => 3001,
                'message' => 'Report not found'
            ], 404),
            
            // Permission denied
            '*permission*' => Http::response([
                'code' => 6500,
                'message' => 'Insufficient permission'
            ], 403)
        ]);
    }
    
    public static function mockTimeoutError(): void
    {
        Http::fake([
            '*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
            }
        ]);
    }
    
    public static function mockNetworkError(): void
    {
        Http::fake([
            '*' => function () {
                throw new \Illuminate\Http\Client\RequestException(
                    \Illuminate\Http\Client\Response::create('', 0)
                );
            }
        ]);
    }
}
```

#### Tests de rate limiting et timeouts
```php
describe('Rate Limiting and Timeouts', function () {
    it('handles rate limiting gracefully', function () {
        ZohoErrorMockingHelper::mockSpecificErrors();
        
        $response = ZohoCreatorApi::get('rate_limited_report');
        
        expect($response)->toBeString();
        expect($response)->toContain('Rate limit exceeded');
        
        // Vérifier que le service implémente un retry avec backoff
        Http::assertSentCount(3); // Initial + 2 retries
    });
    
    it('handles timeouts appropriately', function () {
        ZohoErrorMockingHelper::mockTimeoutError();
        
        expect(function () {
            ZohoCreatorApi::get('timeout_report');
        })->toThrow(\Exception::class, 'timeout');
    });
    
    it('respects configured timeout values', function () {
        config(['zohoconnector.request_timeout' => 5]);
        
        $start = microtime(true);
        
        try {
            ZohoCreatorApi::get('slow_report');
        } catch (\Exception $e) {
            // Expected timeout
        }
        
        $duration = microtime(true) - $start;
        expect($duration)->toBeLessThan(6); // 5s timeout + 1s tolerance
    });
});
```

---

## 🔗 Stratégie inter-packages

### Contract testing entre Zoho Connector et VIPros Elastic Models

#### Définition des contrats entre packages
```php
// tests/Contracts/ZohoApiContract.php
interface ZohoApiContract
{
    public function getCompanyData(string $id): array;
    public function getBulkData(string $report, array $criteria): array;
    public function getMetadata(string $form): array;
}

// Tests de contrat partagés
class ZohoApiContractTest extends TestCase
{
    /** @test */
    public function company_data_structure_matches_elastic_model_expectations()
    {
        $zohoData = ZohoCreatorApi::getByID('company_report', '123');
        
        // Validation que les données Zoho respectent le contrat attendu par VIPros Elastic Models
        $this->assertArrayHasKey('ID', $zohoData);
        $this->assertArrayHasKey('denomination', $zohoData);
        $this->assertArrayHasKey('vipros_number', $zohoData);
        
        // Test de compatibilité avec CompanyZohoDTO
        $dto = new \Agencedoit\ViprosElasticModels\DTOs\CompanyZohoDTO();
        $this->assertTrue($dto->isValidStructure($zohoData));
    }
}
```

#### Tests de compatibilité des interfaces
```php
// tests/Integration/PackageCompatibilityTest.php
class PackageCompatibilityTest extends TestCase
{
    /** @test */
    public function zoho_connector_facade_is_accessible_from_elastic_models()
    {
        // Vérifier que ViprosElasticModels peut utiliser ZohoCreatorApi
        $this->assertTrue(class_exists('ZohoCreatorApi'));
        $this->assertTrue(method_exists('ZohoCreatorApi', 'get'));
        $this->assertTrue(method_exists('ZohoCreatorApi', 'getAll'));
    }
    
    /** @test */
    public function data_sync_commands_work_with_zoho_connector()
    {
        // Mock des réponses Zoho
        $this->mockZohoResponse('company_report', [
            createCompanyData(['ID' => '123', 'denomination' => 'Test Company'])
        ]);
        
        // Test que les commandes de sync utilisent bien le connector
        $this->artisan('datamanager:sync', ['index' => 'company'])
             ->expectsOutput('Syncing from Zoho...')
             ->assertSuccessful();
    }
}
```

### Gestion des versions et dépendances

#### Stratégie de versioning synchronisé
```json
// composer.json - Gestion des versions compatibles
{
    "require": {
        "agencedoit/laravel-package-vipros-elastic-models": "^1.0.0"
    },
    "extra": {
        "compatibility_matrix": {
            "zoho-connector": "1.0.x",
            "vipros-elastic-models": "1.0.x",
            "laravel": "^12.0"
        }
    }
}
```

#### Matrice de compatibilité des versions
```php
// tests/Compatibility/VersionCompatibilityTest.php
class VersionCompatibilityTest extends TestCase
{
    /** @test */
    public function packages_versions_are_compatible()
    {
        $zohoVersion = $this->getPackageVersion('agencedoit/zohoconnector');
        $elasticVersion = $this->getPackageVersion('agencedoit/laravel-package-vipros-elastic-models');
        
        // Matrix de compatibilité
        $compatibilityMatrix = [
            '1.0' => ['vipros-elastic-models' => '^1.0.0'],
            '1.1' => ['vipros-elastic-models' => '^1.1.0'],
        ];
        
        $this->assertTrue(
            $this->areVersionsCompatible($zohoVersion, $elasticVersion, $compatibilityMatrix)
        );
    }
    
    /** @test */
    public function breaking_changes_are_detected()
    {
        // Test des breaking changes entre versions
        $this->markTestSkipped('To implement when version changes occur');
    }
}
```

#### Tests de régression inter-packages
```php
// tests/Regression/CrossPackageRegressionTest.php
class CrossPackageRegressionTest extends TestCase
{
    /** @test */
    public function elastic_models_still_work_after_zoho_connector_updates()
    {
        // Setup données de test
        $testData = createCompanyData();
        $this->mockZohoResponse('company_report', [$testData]);
        
        // Test du flow complet : Zoho → DTO → Elasticsearch
        $this->artisan('datamanager:sync', ['index' => 'company'])
             ->assertSuccessful();
        
        // Vérification que les données sont correctement transformées
        $company = \Agencedoit\ViprosElasticModels\Models\ElasticApi\Company::find($testData['ID']);
        $this->assertNotNull($company);
        $this->assertEquals($testData['denomination'], $company->denomination);
    }
}
```

### Shared testing utilities

#### Réutilisation des helpers existants
```php
// tests/Helpers/SharedTestHelper.php
class SharedTestHelper
{
    /**
     * Utilise les helpers de ViprosElasticModels tout en les adaptant pour ZohoConnector
     */
    public static function createZohoCompatibleCompanyData(array $overrides = []): array
    {
        // Réutilise la fonction globale du package ViprosElasticModels
        $baseData = createCompanyData($overrides);
        
        // Adaptation spécifique pour les tests ZohoConnector
        return array_merge($baseData, [
            'zoho_created_time' => now()->toISOString(),
            'zoho_modified_time' => now()->toISOString(),
        ]);
    }
    
    public static function mockElasticModelsServices(): void
    {
        // Mock des services ViprosElasticModels pour les tests d'intégration
        app()->bind(
            \Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class,
            function () {
                return Mockery::mock(\Agencedoit\ViprosElasticModels\Services\Sync\SyncService::class);
            }
        );
    }
}
```

#### Extension des fixtures communes
```php
// tests/Fixtures/CrossPackageFixtures.php
class CrossPackageFixtures
{
    /**
     * Fixtures qui fonctionnent pour les deux packages
     */
    public static function getCompanyDataSet(): array
    {
        return [
            'zoho_format' => [
                'ID' => '61757000058385531',
                'denomination' => 'VIPros Test Company',
                'Added_Time' => '2025-01-01T00:00:00Z',
                'Modified_Time' => '2025-01-01T00:00:00Z',
            ],
            'elastic_format' => [
                'id' => '61757000058385531',
                'denomination' => 'VIPros Test Company',
                'created_at' => '2025-01-01T00:00:00Z',
                'updated_at' => '2025-01-01T00:00:00Z',
            ]
        ];
    }
    
    public static function getZohoApiResponseFixture(string $endpoint): array
    {
        $fixtures = json_decode(
            file_get_contents(__DIR__ . '/zoho_responses/api_responses.json'),
            true
        );
        
        return $fixtures[$endpoint] ?? [];
    }
}
```

#### Patterns de test partagés
```php
// tests/Patterns/SyncTestPattern.php
trait SyncTestPattern
{
    /**
     * Pattern de test réutilisable pour les syncs Zoho → Elasticsearch
     */
    protected function assertSyncWorksCorrectly(string $entity, array $testData): void
    {
        // 1. Mock Zoho response
        $this->mockZohoResponse("{$entity}_report", [$testData]);
        
        // 2. Execute sync
        $this->artisan('datamanager:sync', ['index' => $entity])
             ->assertSuccessful();
        
        // 3. Verify in Elasticsearch
        $modelClass = "\\Agencedoit\\ViprosElasticModels\\Models\\ElasticApi\\" . ucfirst($entity);
        $record = $modelClass::find($testData['ID']);
        
        $this->assertNotNull($record, "Record should exist in Elasticsearch");
        $this->assertEquals($testData['ID'], $record->getAttribute('ID'));
    }
    
    protected function assertSyncHandlesErrorsGracefully(string $entity): void
    {
        // Mock erreur Zoho
        Http::fake([
            '*' => Http::response(['error' => 'API Error'], 500)
        ]);
        
        // Sync doit échouer gracieusement
        $this->artisan('datamanager:sync', ['index' => $entity])
             ->assertFailed();
        
        // Vérifier que l'erreur est loggée
        $this->assertStringContainsString('Zoho API Error', $this->getLogContent());
    }
}
```

---

## 📋 Plan d'implémentation

### Phase 1 : Infrastructure (Semaine 1)
1. **Configuration de base**
   - [ ] Création de TestCase.php robuste
   - [ ] Configuration Pest.php
   - [ ] Setup des helpers de mocking
   - [ ] Fixtures de données de test

2. **Tests unitaires core**
   - [ ] ZohoCreatorService (méthodes principales)
   - [ ] ZohoTokenManagement (auth flow)
   - [ ] Models (ZohoConnectorToken, ZohoBulkHistory)

### Phase 2 : Tests d'intégration (Semaine 2)
1. **API Layer**
   - [ ] Tests complets des endpoints CRUD
   - [ ] Tests des operations bulk
   - [ ] Tests des custom functions

2. **Authentication**
   - [ ] OAuth2 flow complet
   - [ ] Token refresh automatique
   - [ ] Error handling

### Phase 3 : Intégration cross-package (Semaine 3)
1. **VIPros Elastic Models Integration**
   - [ ] Tests end-to-end sync
   - [ ] Validation de cohérence des données
   - [ ] Performance testing

2. **Quality Assurance**
   - [ ] Code coverage > 90%
   - [ ] Performance benchmarks
   - [ ] Documentation des tests

---

## 🔍 Métriques de qualité

### Coverage objectives
- **Unit Tests** : 95% de coverage minimum
- **Feature Tests** : Tous les endpoints et workflows
- **Integration Tests** : Scénarios critiques business

### Performance benchmarks
- **Suite complète** : < 30 secondes
- **Tests unitaires** : < 10 secondes
- **Tests d'intégration** : < 20 secondes

### Quality gates
- [ ] Tous les tests passent en CI/CD
- [ ] Aucun test ne dépend de services externes
- [ ] Coverage reports générés automatiquement
- [ ] Documentation mise à jour

---

## 📖 Bonnes pratiques

### Naming conventions
- **Tests** : `{action}_{expected_result}()` (ex: `creates_record_successfully()`)
- **Mocks** : `mock{Service}{Action}()` (ex: `mockZohoSuccessResponse()`)
- **Fixtures** : `{entity}_data.json` (ex: `company_data.json`)

### Test organization
- **AAA Pattern** : Arrange, Act, Assert toujours respecté
- **One assertion per test** : Focus sur un comportement spécifique
- **Descriptive names** : Tests auto-documentés

### Mocking strategy
- **External APIs** : Toujours mockées
- **Database** : In-memory SQLite pour les tests
- **File system** : Fake storage Laravel
- **Queue** : Queue::fake() pour les jobs

---

Cette stratégie assure une suite de tests robuste, complémentaire avec VIPros Elastic Models, et maintenable à long terme. L'approche progressive permet une implémentation maîtrisée avec des objectifs de qualité élevés.