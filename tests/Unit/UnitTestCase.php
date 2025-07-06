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
    }
}