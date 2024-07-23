<?php

namespace Agencedoit\ZohoConnector;

use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\AliasLoader;

use Agencedoit\ZohoConnector\Services\ZohoCreatorService;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade;

class ZohoConnectorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/zohoconnector.php', 'zohoconnector');
        $this->app->singleton(ZohoCreatorService::class, function ($app) {
            return new ZohoCreatorService();
        });
        AliasLoader::getInstance([
            'ZohoCreatorApi' => ZohoCreatorFacade::class,
        ]);
        //$this->app->alias(ZohoCreatorService::class, 'ZohoCreatorApi');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadMigrationsFrom([__DIR__.'/../database/migrations' => database_path('migrations'),]);
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'zohoconnector');
    }
}
