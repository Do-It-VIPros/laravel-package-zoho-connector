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
        // Configuration pour tests SANS base de données
        $this->setupZohoConfig($app);
        $this->setupCacheConfig($app);
        $this->setupTestMode($app);
    }

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset des facades entre les tests
        Http::fake();
        Queue::fake();
        Storage::fake('local');
        Cache::flush();
        
        // Mode test : pas de DB, tout en cache/mock
        $this->setupTestMode();
    }

    protected function setupZohoConfig($app): void
    {
        // Configuration Zoho pour tests
        $app['config']->set('zohoconnector.test_mode', true);
        $app['config']->set('zohoconnector.mock_responses', true);
        $app['config']->set('zohoconnector.storage_driver', 'cache'); // Utiliser cache au lieu de DB
        
        // Credentials de test
        $app['config']->set('zohoconnector.client_id', env('ZOHO_TEST_CLIENT_ID', 'test_client'));
        $app['config']->set('zohoconnector.client_secret', env('ZOHO_TEST_SECRET', 'test_secret'));
        $app['config']->set('zohoconnector.user', env('ZOHO_TEST_USER', 'test_user'));
        $app['config']->set('zohoconnector.app_name', env('ZOHO_TEST_APP', 'test_app'));
        $app['config']->set('zohoconnector.base_account_url', 'https://accounts.zoho.eu');
        $app['config']->set('zohoconnector.api_base_url', 'https://www.zohoapis.eu');
        $app['config']->set('zohoconnector.request_timeout', 5);
    }

    protected function setupCacheConfig($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('session.driver', 'array');
    }

    protected function setupTestMode($app = null): void
    {
        // En mode test, on stocke les tokens en cache
        if (!$app) {
            config(['zohoconnector.storage_driver' => 'cache']);
        }
    }

    /**
     * Helper pour créer un token en cache (sans DB)
     */
    protected function createValidZohoToken(): void
    {
        $tokenData = [
            'access_token' => 'test_access_token_' . uniqid(),
            'refresh_token' => 'test_refresh_token_' . uniqid(),
            'expires_in' => 3600,
            'token_created_at' => now(),
            'token_expires_at' => now()->addHour(),
        ];
        
        Cache::put('zoho_token', $tokenData, 3600);
    }

    /**
     * Helper pour mock Zoho API responses
     */
    protected function mockZohoResponse(string $report, array $data): void
    {
        ZohoApiMockingHelper::mockZohoDataResponse($report, $data);
    }

    /**
     * Helper pour vérifier qu'un token existe en cache
     */
    protected function assertZohoTokenExists(): void
    {
        expect(Cache::has('zoho_token'))->toBeTrue();
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
        Cache::flush();
        
        parent::tearDown();
    }
}