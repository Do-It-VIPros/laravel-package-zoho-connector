<?php

namespace Agencedoit\ZohoConnector\Tests\Helpers;

class FixtureVersionManager
{
    private const FIXTURE_VERSION = '1.0';
    private const FIXTURES_PATH = __DIR__ . '/../Fixtures';

    public function loadFixture(string $name, string $version = null): array
    {
        $path = self::FIXTURES_PATH . "/test_data/{$name}.json";
        
        if (!file_exists($path)) {
            throw new \Exception("Fixture {$name} not found at {$path}");
        }
        
        $data = json_decode(file_get_contents($path), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in fixture {$name}: " . json_last_error_msg());
        }
        
        return $data;
    }

    public function loadZohoResponse(string $name, string $version = null): array
    {
        $path = self::FIXTURES_PATH . "/zoho_responses/{$name}.json";
        
        if (!file_exists($path)) {
            throw new \Exception("Zoho response fixture {$name} not found at {$path}");
        }
        
        $data = json_decode(file_get_contents($path), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON in fixture {$name}: " . json_last_error_msg());
        }
        
        return $data;
    }

    public static function load(string $name, string $version = null): array
    {
        $instance = new self();
        return $instance->loadFixture($name, $version);
    }

    public static function saveVersionedFixture(string $name, array $data, string $version = null): void
    {
        $version = $version ?? self::FIXTURE_VERSION;
        
        // Add metadata
        $data['_fixture_meta'] = [
            'version' => $version,
            'created_at' => now()->toISOString(),
            'zoho_api_version' => '2.1',
            'package_version' => self::getPackageVersion()
        ];
        
        $dir = self::FIXTURES_PATH . "/zoho_responses/v{$version}";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents(
            "{$dir}/{$name}.json",
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    public static function getFixturePath(string $name): string
    {
        return self::FIXTURES_PATH . "/zoho_responses/{$name}.json";
    }

    public static function fixtureExists(string $name): bool
    {
        return file_exists(self::getFixturePath($name));
    }

    public static function createFixtureDirectory(): void
    {
        $dirs = [
            self::FIXTURES_PATH . '/zoho_responses',
            self::FIXTURES_PATH . '/zoho_responses/auth',
            self::FIXTURES_PATH . '/zoho_responses/versions',
            self::FIXTURES_PATH . '/test_data'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    public static function getAvailableFixtures(): array
    {
        $path = self::FIXTURES_PATH . '/zoho_responses';
        if (!is_dir($path)) {
            return [];
        }
        
        $fixtures = [];
        $files = glob($path . '/*.json');
        
        foreach ($files as $file) {
            $fixtures[] = basename($file, '.json');
        }
        
        return $fixtures;
    }

    private static function getPackageVersion(): string
    {
        $composerPath = __DIR__ . '/../../composer.json';
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            return $composer['version'] ?? 'dev';
        }
        
        return 'unknown';
    }

    public static function validateFixture(string $name): bool
    {
        try {
            $data = self::loadFixture($name);
            
            // Basic validation
            if (!is_array($data)) {
                return false;
            }
            
            // Check for metadata
            if (isset($data['_fixture_meta'])) {
                $meta = $data['_fixture_meta'];
                if (!isset($meta['version']) || !isset($meta['created_at'])) {
                    return false;
                }
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}