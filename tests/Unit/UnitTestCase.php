<?php

namespace Agencedoit\ZohoConnector\Tests\Unit;

use Agencedoit\ZohoConnector\ZohoConnectorServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class UnitTestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            ZohoConnectorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Simple config for unit tests without database
        $app['config']->set('app.env', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
        
        // Configuration Zoho minimale pour tests unit
        $app['config']->set('zohoconnector.test_mode', true);
        $app['config']->set('zohoconnector.client_id', 'test_client');
        $app['config']->set('zohoconnector.client_secret', 'test_secret');
        $app['config']->set('zohoconnector.user', 'test_user');
        $app['config']->set('zohoconnector.app_name', 'test_app');
        $app['config']->set('zohoconnector.base_account_url', 'https://accounts.zoho.eu');
        $app['config']->set('zohoconnector.api_base_url', 'https://www.zohoapis.eu');
        $app['config']->set('zohoconnector.environment', 'development');
        $app['config']->set('zohoconnector.tokens_table_name', 'zoho_connector_tokens');
        $app['config']->set('zohoconnector.bulks_table_name', 'zoho_connector_bulk_history');
    }
}