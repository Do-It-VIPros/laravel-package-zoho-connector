<?php

namespace Agencedoit\ZohoConnector\Tests\Feature;

use Agencedoit\ZohoConnector\ZohoConnectorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

class MockTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ZohoConnectorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Configuration pour tests avec base de données DDEV
        $this->setupDDEVDatabaseConfig($app);
        $this->setupMockConfig($app);
        $this->setupCacheConfig($app);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Utilise les migrations existantes de l'application principale
        $this->artisan('migrate', ['--database' => 'mariadb']);
        
        // Configuration minimale pour tests - pas de Http::fake() global pour éviter les conflits
        Queue::fake();
        Storage::fake('local');
    }

    protected function setupMockConfig($app): void
    {
        // Configuration Zoho pour tests avec vraie API et vraie DB
        $app['config']->set('zohoconnector.test_mode', false); // Utilise vraie validation
        $app['config']->set('zohoconnector.mock_mode', false);
        
        // Utilise les credentials du parent .env - test mode avec credentials sécurisés
        $app['config']->set('zohoconnector.client_id', env('ZOHO_CLIENT_ID', 'test_client_id'));
        $app['config']->set('zohoconnector.client_secret', env('ZOHO_CLIENT_SECRET', 'test_client_secret'));
        $app['config']->set('zohoconnector.user', env('ZOHO_USER', 'test_user'));
        $app['config']->set('zohoconnector.app_name', env('ZOHO_APP_NAME', 'test_app'));
        $app['config']->set('zohoconnector.scope', env('ZOHO_SCOPE', 'ZohoCreator.report.ALL,ZohoCreator.bulk.CREATE,ZohoCreator.bulk.READ'));
        
        // URLs basées sur le domaine
        $domain = env('ZOHO_ACCOUNT_DOMAIN', 'eu');
        $app['config']->set('zohoconnector.base_account_url', "https://accounts.zoho.{$domain}");
        $app['config']->set('zohoconnector.api_base_url', "https://www.zohoapis.{$domain}");
        
        $app['config']->set('zohoconnector.environment', env('ZOHO_CREATOR_ENVIRONMENT', 'development'));
        $app['config']->set('zohoconnector.request_timeout', env('ZOHO_REQUEST_TIMEOUT', 90));
        
        // Tables
        $app['config']->set('zohoconnector.tokens_table_name', 'zoho_connector_tokens');
        $app['config']->set('zohoconnector.bulks_table_name', 'zoho_connector_bulk_history');
    }



    protected function setupDDEVDatabaseConfig($app): void
    {
        // Configuration base de données DDEV - utilise 'db' comme host à l'intérieur du container
        $app['config']->set('database.default', 'mariadb');
        $app['config']->set('database.connections.mariadb', [
            'driver' => 'mysql',
            'host' => 'db', // Host interne DDEV (à l'intérieur du container)
            'port' => '3306', // Port standard MySQL à l'intérieur du container
            'database' => 'db',
            'username' => 'db', 
            'password' => 'db',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
    }

    protected function setupCacheConfig($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
    }

    protected function tearDown(): void
    {
        // Pas de Http::fake() global - laisse chaque test gérer ses propres mocks
        Queue::fake();
        Storage::fake('local');
        
        parent::tearDown();
    }
}