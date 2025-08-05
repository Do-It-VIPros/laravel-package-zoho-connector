<?php

namespace Agencedoit\ZohoConnector\Tests;

use Agencedoit\ZohoConnector\ZohoConnectorServiceProvider;
use Agencedoit\ZohoConnector\Tests\Helpers\ZohoApiMockingHelper;
use Agencedoit\ZohoConnector\Tests\Helpers\CredentialsHelper;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            ZohoConnectorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Charger le fichier .env local s'il existe
        $this->loadLocalEnvironment($app);
        
        // Configuration pour tests AVEC base de données du projet parent
        $this->setupDatabaseConfig($app);
        $this->setupZohoConfig($app);
        $this->setupCacheConfig($app);
    }

    protected function loadLocalEnvironment($app): void
    {
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath)) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname($envPath));
            $dotenv->load();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configuration minimale pour tests avec vraie DB
        Http::fake();
        Queue::fake();
        Storage::fake('local');
        
        // Créer les tables nécessaires si elles n'existent pas
        $this->setUpDatabase();
    }

    protected function setupDatabaseConfig($app): void
    {
        // Configuration base de données DDEV MariaDB
        $app['config']->set('database.default', 'mysql');
        $app['config']->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => env('DB_HOST', 'db'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'db'),
            'username' => env('DB_USERNAME', 'db'),
            'password' => env('DB_PASSWORD', 'db'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
            'engine' => null,
        ]);
    }

    protected function setupZohoConfig($app): void
    {
        // Configuration Zoho pour tests - utiliser vraies credentials
        $app['config']->set('zohoconnector.test_mode', false); // Désactiver le mode test artificiel
        $app['config']->set('zohoconnector.mock_mode', false);
        
        // Credentials du projet parent
        $app['config']->set('zohoconnector.client_id', env('ZOHO_CLIENT_ID'));
        $app['config']->set('zohoconnector.client_secret', env('ZOHO_CLIENT_SECRET'));
        $app['config']->set('zohoconnector.user', env('ZOHO_USER'));
        $app['config']->set('zohoconnector.app_name', env('ZOHO_APP_NAME'));
        $app['config']->set('zohoconnector.scope', env('ZOHO_SCOPE', 'ZohoCreator.report.READ'));
        $app['config']->set('zohoconnector.base_account_url', 'https://accounts.zoho.' . env('ZOHO_ACCOUNT_DOMAIN', 'eu'));
        $app['config']->set('zohoconnector.api_base_url', 'https://www.zohoapis.' . env('ZOHO_ACCOUNT_DOMAIN', 'eu'));
        $app['config']->set('zohoconnector.environment', env('ZOHO_CREATOR_ENVIRONMENT', 'development'));
        $app['config']->set('zohoconnector.request_timeout', env('ZOHO_REQUEST_TIMEOUT', 30));
        
        // Tables
        $app['config']->set('zohoconnector.tokens_table_name', env('ZOHO_TOKENS_TABLE', 'zoho_connector_tokens'));
        $app['config']->set('zohoconnector.bulks_table_name', env('ZOHO_BULKS_TABLE', 'zoho_connector_bulk_history'));
    }

    protected function setupCacheConfig($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
    }

    protected function setUpDatabase(): void
    {
        // Créer les tables Zoho Connector si elles n'existent pas
        if (!$this->hasTable('zoho_connector_tokens')) {
            $this->artisan('migrate', [
                '--path' => 'vendor/agencedoit/zohoconnector/database/migrations',
                '--force' => true
            ]);
        }
    }

    protected function hasTable(string $table): bool
    {
        return \Schema::hasTable($table);
    }

    /**
     * Helper pour créer un token valide en DB
     */
    protected function createValidZohoToken(): void
    {
        \DB::table('zoho_connector_tokens')->insert([
            'token' => 'test_access_token_' . uniqid(),
            'refresh_token' => 'test_refresh_token_' . uniqid(),
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Helper pour mock Zoho API responses
     */
    protected function mockZohoResponse(string $report, array $data): void
    {
        ZohoApiMockingHelper::mockZohoDataResponse($report, $data);
    }

    /**
     * Helper pour vérifier qu'un token existe en DB
     */
    protected function assertZohoTokenExists(): void
    {
        expect(\DB::table('zoho_connector_tokens')->count())->toBeGreaterThan(0);
    }

    /**
     * Test avec vraie API Zoho (optionnel)
     */
    protected function skipIfNoRealApi(): void
    {
        if (!env('ZOHO_REAL_API_TESTS', false)) {
            $this->markTestSkipped('Real API tests disabled');
        }
    }

    protected function tearDown(): void
    {
        Http::fake();
        Queue::fake();
        Storage::fake('local');
        
        parent::tearDown();
    }
}