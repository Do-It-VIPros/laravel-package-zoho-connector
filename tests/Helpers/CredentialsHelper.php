<?php

namespace Agencedoit\ZohoConnector\Tests\Helpers;

class CredentialsHelper
{
    public static function getTestCredentials(): array
    {
        if (env('ZOHO_TEST_MODE', true)) {
            return [
                'client_id' => 'test_client_id_' . md5('test'),
                'client_secret' => 'test_secret_' . md5('secret'),
                'access_token' => 'test_access_token_' . uniqid(),
                'refresh_token' => 'test_refresh_token_' . uniqid(),
            ];
        }
        
        // Pour tests d'intégration avec vraie API (CI uniquement)
        return [
            'client_id' => env('ZOHO_TEST_CLIENT_ID'),
            'client_secret' => env('ZOHO_TEST_CLIENT_SECRET'),
            'access_token' => env('ZOHO_TEST_ACCESS_TOKEN'),
            'refresh_token' => env('ZOHO_TEST_REFRESH_TOKEN'),
        ];
    }

    public static function isIntegrationMode(): bool
    {
        return env('ZOHO_INTEGRATION_MODE', false) && !env('ZOHO_TEST_MODE', true);
    }

    public static function validateCredentials(array $credentials): bool
    {
        $required = ['client_id', 'client_secret'];
        
        foreach ($required as $key) {
            if (empty($credentials[$key])) {
                return false;
            }
        }
        
        return true;
    }

    public static function getTestDomainCredentials(string $domain = 'eu'): array
    {
        $baseCredentials = self::getTestCredentials();
        
        return array_merge($baseCredentials, [
            'domain' => $domain,
            'accounts_url' => "https://accounts.zoho.{$domain}",
            'api_url' => "https://www.zohoapis.{$domain}",
        ]);
    }

    public static function createTestToken(): array
    {
        return [
            'token' => 'test_token_' . uniqid(),
            'refresh_token' => 'test_refresh_' . uniqid(),
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ];
    }

    public static function createExpiredToken(): array
    {
        return [
            'token' => 'expired_token_' . uniqid(),
            'refresh_token' => 'refresh_' . uniqid(),
            'token_created_at' => now()->subHours(2),
            'token_peremption_at' => now()->subMinute(),
            'token_duration' => 3600
        ];
    }
}