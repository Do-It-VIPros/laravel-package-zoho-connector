<?php

namespace Agencedoit\ZohoConnector;

use Illuminate\Support\ServiceProvider;

class ZohoConnectorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
        $this->mergeConfigFrom(__DIR__.'/../config/zohoconnector.php', 'zohoconnector');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        //$this->publishesMigrations([__DIR__.'/../database/migrations' => database_path('migrations'),]);
    }
}
