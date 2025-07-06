<?php

namespace Agencedoit\ZohoConnector\Tests;

use Agencedoit\ZohoConnector\ZohoConnectorServiceProvider;
use Agencedoit\ZohoConnector\Tests\Helpers\ZohoApiMockingHelper;
use Agencedoit\ZohoConnector\Tests\Helpers\CredentialsHelper;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCase extends Orchestra
{
    // Conditionally use RefreshDatabase only for Feature/Integration tests
    protected bool $useDatabase = false;

    protected function getPackageProviders($app): array
    {
        return [
            ZohoConnectorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Load test environment file
        if (file_exists(__DIR__ . '/.env.testing')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__, '.env.testing');
            $dotenv->load();
        }

        // Configuration de base
        $this->setupDatabaseConfig($app);
        $this->setupZohoConfig($app);
        $this->setupLoggingConfig($app);
        $this->setupCacheConfig($app);
    }

    protected function setUp(): void
    {
        // Enable database for Feature/Integration tests
        if ($this->isFeatureTest()) {
            $this->useDatabase = true;
            $this->initializeTraitForTesting(RefreshDatabase::class);
        }
        
        parent::setUp();
        
        // Reset des facades entre les tests
        Http::fake();
        Queue::fake();
        Storage::fake('local');
        
        // Initialisation des mocks Zoho (skip for Unit tests)
        // ZohoApiMockingHelper::initialize();
        
        // Migration conditionnelle pour les tests Feature
        if ($this->isFeatureTest()) {
            $this->runMigrations();
        }
        
        // Setup logging pour debug
        $this->setupTestLogging();
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
        $app['config']->set('zohoconnector.user', env('ZOHO_USER', 'test_user'));
        $app['config']->set('zohoconnector.app_name', env('ZOHO_APP_NAME', 'test_app'));
        $app['config']->set('zohoconnector.base_account_url', 'https://accounts.zoho.eu');
        $app['config']->set('zohoconnector.api_base_url', 'https://www.zohoapis.eu');
        $app['config']->set('zohoconnector.request_timeout', 5);
        
        // Tables de test
        $app['config']->set('zohoconnector.tokens_table_name', 'zoho_connector_tokens');
        $app['config']->set('zohoconnector.bulks_table_name', 'zoho_connector_bulk_history');
        $app['config']->set('zohoconnector.bulk_download_path', storage_path('testing/zohoconnector'));
        $app['config']->set('zohoconnector.bulk_queue', 'testing');
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

    protected function setupCacheConfig($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
    }

    protected function runMigrations(): void
    {
        $this->artisan('migrate:fresh', [
            '--database' => 'testing',
            '--force' => true
        ]);
        
        $this->loadLaravelMigrations(['--database' => 'testing']);
    }

    protected function isFeatureTest(): bool
    {
        return str_contains(static::class, 'Feature') || 
               str_contains(static::class, 'Integration');
    }

    protected function cleanupBetweenTests(): void
    {
        // Reset HTTP mocks
        Http::fake();
        
        // Reset Queue
        Queue::fake();
        
        // Reset Storage
        Storage::fake('local');
        
        // Clear any cached data
        if (method_exists($this->app['cache'], 'flush')) {
            $this->app['cache']->flush();
        }
        
        // Reset Zoho mocks (skip for Unit tests)
        // ZohoApiMockingHelper::reset();
    }

    protected function setupTestLogging(): void
    {
        // Ensure logs directory exists
        $logsPath = storage_path('logs');
        if (!is_dir($logsPath)) {
            mkdir($logsPath, 0755, true);
        }
    }

    /**
     * Helper method to mock Zoho API responses
     */
    protected function mockZohoResponse(string $report, array $data): void
    {
        ZohoApiMockingHelper::mockZohoDataResponse($report, $data);
    }

    /**
     * Helper method to mock Zoho API errors
     */
    protected function mockZohoError(int $code = 500, string $message = 'API Error'): void
    {
        Http::fake(['*' => Http::response(['error' => $message], $code)]);
    }

    /**
     * Helper method to create valid Zoho token for tests
     */
    protected function createValidZohoToken(): void
    {
        \Agencedoit\ZohoConnector\Models\ZohoConnectorToken::create([
            'token' => 'test_access_token_' . uniqid(),
            'refresh_token' => 'test_refresh_token_' . uniqid(),
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    }

    /**
     * Helper method to assert database has Zoho token
     */
    protected function assertZohoTokenExists(): void
    {
        $this->assertDatabaseHas('zoho_connector_tokens', []);
    }

    /**
     * Helper method to assert database is clean
     */
    protected function assertDatabaseClean(): void
    {
        $this->assertDatabaseEmpty('zoho_connector_tokens');
        $this->assertDatabaseEmpty('zoho_connector_bulk_history');
    }

    /**
     * Helper method to get test logs
     */
    protected function getTestLogs(): string
    {
        $logPath = storage_path('logs/testing.log');
        return file_exists($logPath) ? file_get_contents($logPath) : '';
    }

    /**
     * Helper method to mock Elasticsearch for integration tests
     */
    protected function mockElasticsearch(): void
    {
        // Mock basic Elasticsearch operations if needed
        // This can be extended based on integration requirements
    }
}