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
        // Configuration pour tests mock SANS base de données
        $this->setupMockConfig($app);
        $this->setupCacheConfig($app);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Configuration minimale pour tests mock
        Http::fake();
        Queue::fake();
        Storage::fake('local');
    }

    protected function setupMockConfig($app): void
    {
        // Configuration Zoho pour tests mock - mode test activé
        $app['config']->set('zohoconnector.test_mode', true);
        $app['config']->set('zohoconnector.mock_mode', true);
        
        // Credentials mock
        $app['config']->set('zohoconnector.client_id', 'mock_client');
        $app['config']->set('zohoconnector.client_secret', 'mock_secret');
        $app['config']->set('zohoconnector.user', 'mock_user');
        $app['config']->set('zohoconnector.app_name', 'mock_app');
        $app['config']->set('zohoconnector.scope', 'ZohoCreator.report.READ');
        $app['config']->set('zohoconnector.base_account_url', 'https://accounts.zoho.eu');
        $app['config']->set('zohoconnector.api_base_url', 'https://www.zohoapis.eu');
        $app['config']->set('zohoconnector.environment', 'development');
        $app['config']->set('zohoconnector.request_timeout', 5);
        
        // Tables (non utilisées en mode mock)
        $app['config']->set('zohoconnector.tokens_table_name', 'zoho_connector_tokens');
        $app['config']->set('zohoconnector.bulks_table_name', 'zoho_connector_bulk_history');
    }

    protected function setupCacheConfig($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
    }

    protected function tearDown(): void
    {
        Http::fake();
        Queue::fake();
        Storage::fake('local');
        
        parent::tearDown();
    }
}