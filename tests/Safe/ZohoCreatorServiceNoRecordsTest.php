<?php

namespace Agencedoit\ZohoConnector\Tests\Safe;

use Agencedoit\ZohoConnector\Services\ZohoCreatorService;
use Agencedoit\ZohoConnector\ZohoConnectorServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;

class ZohoCreatorServiceNoRecordsTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ZohoConnectorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('zohoconnector.test_mode', true);
        $app['config']->set('zohoconnector.api_base_url', 'https://www.zohoapis.eu');
        $app['config']->set('zohoconnector.user', 'test_user');
        $app['config']->set('zohoconnector.app_name', 'test_app');
        $app['config']->set('zohoconnector.environment', 'stage');
        $app['config']->set('zohoconnector.request_timeout', 1);
    }

    public static function noRecordsCodes(): array
    {
        return [
            'legacy code 3100' => [3100],
            'Creator API v2.1 code 9280' => [9280],
        ];
    }

    /**
     * @dataProvider noRecordsCodes
     */
    public function test_get_returns_an_empty_array_for_a_no_records_response(int $zohoCode): void
    {
        Http::fake([
            '*' => Http::response([
                'code' => $zohoCode,
                'message' => 'No records found matching the given criteria. Try using different search criteria.',
            ], 400),
        ]);

        $result = (new ZohoCreatorService)->get('API_email_confirmation', 'ID = 61757000084396957');

        $this->assertSame([], $result);
        Http::assertSentCount(1);
    }

    public function test_get_keeps_a_server_error_as_a_technical_failure(): void
    {
        Http::fake([
            '*' => Http::response([
                'code' => 9280,
                'message' => 'No records found matching the given criteria. Try using different search criteria.',
            ], 500),
        ]);

        $this->expectException(\Exception::class);

        (new ZohoCreatorService)->get('API_email_confirmation', 'ID = 61757000084396957');
    }
}
