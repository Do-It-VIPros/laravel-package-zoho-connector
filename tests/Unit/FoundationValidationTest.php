<?php

use Agencedoit\ZohoConnector\Tests\Unit\UnitTestCase;
use Agencedoit\ZohoConnector\Tests\Helpers\ZohoApiMockingHelper;
use Agencedoit\ZohoConnector\Tests\Helpers\CredentialsHelper;
use Agencedoit\ZohoConnector\Tests\Helpers\FixtureVersionManager;

uses(UnitTestCase::class);

describe('Foundation Infrastructure Validation', function () {
    test('global helper functions are available', function () {
        expect(function_exists('createZohoReportData'))->toBeTrue();
        expect(function_exists('createCompanyData'))->toBeTrue();
        expect(function_exists('createContactData'))->toBeTrue();
        expect(function_exists('loadFixture'))->toBeTrue();
        expect(function_exists('mockZohoResponse'))->toBeTrue();
    });

    test('helper classes can be instantiated', function () {
        $credentialsHelper = new CredentialsHelper();
        $fixtureManager = new FixtureVersionManager();

        expect($credentialsHelper)->toBeInstanceOf(CredentialsHelper::class);
        expect($fixtureManager)->toBeInstanceOf(FixtureVersionManager::class);
    });

    test('fixtures are properly versioned and loadable', function () {
        $fixtureManager = new FixtureVersionManager();
        
        $companyFixture = $fixtureManager->loadFixture('companies');
        expect($companyFixture)->toHaveKey('_fixture_meta');
        expect($companyFixture['_fixture_meta']['version'])->toBe('1.0');
        expect($companyFixture['_fixture_meta']['entity_type'])->toBe('company');

        $contactFixture = $fixtureManager->loadFixture('contacts');
        expect($contactFixture)->toHaveKey('_fixture_meta');
        expect($contactFixture['_fixture_meta']['version'])->toBe('1.0');
        expect($contactFixture['_fixture_meta']['entity_type'])->toBe('contact');
    });

    test('zoho response fixtures are accessible', function () {
        $fixtureManager = new FixtureVersionManager();
        
        $successResponses = $fixtureManager->loadZohoResponse('success_responses');
        expect($successResponses)->toHaveKey('_fixture_meta');
        expect($successResponses)->toHaveKey('get_response');
        expect($successResponses)->toHaveKey('create_response');

        $errorResponses = $fixtureManager->loadZohoResponse('error_responses');
        expect($errorResponses)->toHaveKey('rate_limit');
        expect($errorResponses)->toHaveKey('invalid_token');

        $oauthResponses = $fixtureManager->loadZohoResponse('auth/oauth_flow');
        expect($oauthResponses)->toHaveKey('token_request_success');
        expect($oauthResponses)->toHaveKey('token_refresh_success');
    });

    test('global helper functions work correctly', function () {
        $reportData = createZohoReportData();
        expect($reportData)->toHaveKey('ID');
        expect($reportData)->toHaveKey('Added_Time');
        expect($reportData)->toHaveKey('Modified_Time');

        $companyData = createCompanyData();
        expect($companyData)->toHaveKey('ID');
        expect($companyData)->toHaveKey('denomination');
        expect($companyData['is_test'])->toBeTrue();

        $contactData = createContactData();
        expect($contactData)->toHaveKey('ID');
        expect($contactData)->toHaveKey('first_name');
        expect($contactData['is_test'])->toBeTrue();
    });

    test('mock credentials are properly configured', function () {
        $credentialsHelper = new CredentialsHelper();
        
        $credentials = $credentialsHelper->getTestCredentials();
        expect($credentials)->toHaveKey('client_id');
        expect($credentials)->toHaveKey('client_secret');
        expect($credentials)->toHaveKey('access_token');
        expect($credentials)->toHaveKey('refresh_token');
        
        expect($credentials['client_id'])->toContain('test_');
        expect($credentials['client_secret'])->toContain('test_');
    });

    test('test directory structure is properly set up', function () {
        expect(is_dir(__DIR__ . '/../Fixtures'))->toBeTrue();
        expect(is_dir(__DIR__ . '/../Helpers'))->toBeTrue();
        expect(file_exists(__DIR__ . '/../.env.testing'))->toBeTrue();
        expect(file_exists(__DIR__ . '/../Pest.php'))->toBeTrue();
    });
});