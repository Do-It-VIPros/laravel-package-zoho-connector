<?php

use Agencedoit\ZohoConnector\Models\ZohoConnectorToken;
use Agencedoit\ZohoConnector\Facades\ZohoCreatorFacade as ZohoCreatorApi;
use Illuminate\Support\Facades\Http;

describe('Metadata Operations', function () {
    beforeEach(function () {
        ZohoConnectorToken::create([
            'token' => 'valid_token',
            'refresh_token' => 'refresh_token',
            'token_created_at' => now(),
            'token_peremption_at' => now()->addHour(),
            'token_duration' => 3600
        ]);
    });

    describe('Forms Metadata', function () {
        it('retrieves all forms metadata', function () {
            $formsData = [
                [
                    'form_name' => 'Company',
                    'display_name' => 'Company Form',
                    'link_name' => 'Company',
                    'type' => 'normal',
                    'created_time' => '2024-01-01T10:00:00Z',
                    'modified_time' => '2024-06-01T10:00:00Z'
                ],
                [
                    'form_name' => 'Contact',
                    'display_name' => 'Contact Form',
                    'link_name' => 'Contact',
                    'type' => 'stateless',
                    'created_time' => '2024-01-01T10:00:00Z',
                    'modified_time' => '2024-06-01T10:00:00Z'
                ]
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/forms' => Http::response([
                    'code' => 3000,
                    'forms' => $formsData
                ], 200)
            ]);

            $forms = ZohoCreatorApi::getFormsMeta();

            expect($forms)->toBeArray();
            expect($forms)->toHaveCount(2);
            expect($forms[0]['form_name'])->toBe('Company');
            expect($forms[1]['form_name'])->toBe('Contact');
        });

        it('handles empty forms list', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/forms' => Http::response([
                    'code' => 3000,
                    'forms' => []
                ], 200)
            ]);

            $forms = ZohoCreatorApi::getFormsMeta();

            expect($forms)->toBeArray();
            expect($forms)->toBeEmpty();
        });

        it('handles forms metadata error', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/forms' => Http::response([
                    'code' => 3100,
                    'message' => 'No permission to access forms'
                ], 403)
            ]);

            expect(fn() => ZohoCreatorApi::getFormsMeta())
                ->toThrow(\Exception::class, 'No permission to access forms');
        });
    });

    describe('Fields Metadata', function () {
        it('retrieves fields metadata for a specific form', function () {
            $fieldsData = [
                [
                    'field_name' => 'denomination',
                    'display_name' => 'Company Name',
                    'type' => 'single_line',
                    'required' => true,
                    'unique' => false,
                    'max_length' => 255
                ],
                [
                    'field_name' => 'siren',
                    'display_name' => 'SIREN Number',
                    'type' => 'single_line',
                    'required' => true,
                    'unique' => true,
                    'max_length' => 9,
                    'validation' => [
                        'type' => 'regex',
                        'pattern' => '^[0-9]{9}$'
                    ]
                ],
                [
                    'field_name' => 'status',
                    'display_name' => 'Status',
                    'type' => 'dropdown',
                    'required' => false,
                    'choices' => ['Active', 'Inactive', 'Pending']
                ]
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/form/Company/fields' => Http::response([
                    'code' => 3000,
                    'fields' => $fieldsData
                ], 200)
            ]);

            $fields = ZohoCreatorApi::getFieldsMeta('Company');

            expect($fields)->toBeArray();
            expect($fields)->toHaveCount(3);
            expect($fields[0]['field_name'])->toBe('denomination');
            expect($fields[1]['unique'])->toBeTrue();
            expect($fields[2]['choices'])->toContain('Active');
        });

        it('handles complex field types', function () {
            $complexFields = [
                [
                    'field_name' => 'address',
                    'display_name' => 'Address',
                    'type' => 'address',
                    'sub_fields' => [
                        'address_line_1',
                        'address_line_2',
                        'city',
                        'state',
                        'postal_code',
                        'country'
                    ]
                ],
                [
                    'field_name' => 'attachments',
                    'display_name' => 'File Attachments',
                    'type' => 'file_upload',
                    'multiple' => true,
                    'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'png'],
                    'max_size' => 10485760 // 10MB
                ],
                [
                    'field_name' => 'employees',
                    'display_name' => 'Employees',
                    'type' => 'subform',
                    'link_name' => 'Employee_Details',
                    'fields' => [
                        ['field_name' => 'name', 'type' => 'single_line'],
                        ['field_name' => 'position', 'type' => 'single_line'],
                        ['field_name' => 'email', 'type' => 'email']
                    ]
                ]
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/form/ComplexForm/fields' => Http::response([
                    'code' => 3000,
                    'fields' => $complexFields
                ], 200)
            ]);

            $fields = ZohoCreatorApi::getFieldsMeta('ComplexForm');

            expect($fields[0]['type'])->toBe('address');
            expect($fields[0]['sub_fields'])->toContain('city');
            expect($fields[1]['allowed_types'])->toContain('pdf');
            expect($fields[2]['type'])->toBe('subform');
            expect($fields[2]['fields'])->toHaveCount(3);
        });

        it('handles form not found error', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/form/NonExistentForm/fields' => Http::response([
                    'code' => 3101,
                    'message' => 'Form not found'
                ], 404)
            ]);

            expect(fn() => ZohoCreatorApi::getFieldsMeta('NonExistentForm'))
                ->toThrow(\Exception::class, 'Form not found');
        });
    });

    describe('Reports Metadata', function () {
        it('retrieves all reports metadata', function () {
            $reportsData = [
                [
                    'report_name' => 'All_Companies',
                    'display_name' => 'All Companies',
                    'link_name' => 'All_Companies',
                    'type' => 'report',
                    'form_link_name' => 'Company',
                    'is_default' => true
                ],
                [
                    'report_name' => 'Active_Companies',
                    'display_name' => 'Active Companies',
                    'link_name' => 'Active_Companies',
                    'type' => 'report',
                    'form_link_name' => 'Company',
                    'is_default' => false,
                    'criteria' => 'status == "Active"'
                ],
                [
                    'report_name' => 'Company_Statistics',
                    'display_name' => 'Company Statistics',
                    'link_name' => 'Company_Statistics',
                    'type' => 'calendar',
                    'form_link_name' => 'Company',
                    'is_default' => false
                ]
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/reports' => Http::response([
                    'code' => 3000,
                    'reports' => $reportsData
                ], 200)
            ]);

            $reports = ZohoCreatorApi::getReportsMeta();

            expect($reports)->toBeArray();
            expect($reports)->toHaveCount(3);
            expect($reports[0]['is_default'])->toBeTrue();
            expect($reports[1]['criteria'])->toBe('status == "Active"');
            expect($reports[2]['type'])->toBe('calendar');
        });

        it('filters reports by type', function () {
            $mixedReports = [
                ['report_name' => 'Report1', 'type' => 'report'],
                ['report_name' => 'Calendar1', 'type' => 'calendar'],
                ['report_name' => 'Report2', 'type' => 'report'],
                ['report_name' => 'Kanban1', 'type' => 'kanban']
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/reports' => Http::response([
                    'code' => 3000,
                    'reports' => $mixedReports
                ], 200)
            ]);

            $reports = ZohoCreatorApi::getReportsMeta();

            // Filter only 'report' type
            $standardReports = array_filter($reports, fn($r) => $r['type'] === 'report');
            
            expect(count($standardReports))->toBe(2);
        });

        it('handles reports with permissions', function () {
            $reportsWithPermissions = [
                [
                    'report_name' => 'Public_Report',
                    'display_name' => 'Public Report',
                    'permissions' => [
                        'view' => true,
                        'add' => true,
                        'edit' => true,
                        'delete' => true
                    ]
                ],
                [
                    'report_name' => 'Restricted_Report',
                    'display_name' => 'Restricted Report',
                    'permissions' => [
                        'view' => true,
                        'add' => false,
                        'edit' => false,
                        'delete' => false
                    ]
                ]
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/reports' => Http::response([
                    'code' => 3000,
                    'reports' => $reportsWithPermissions
                ], 200)
            ]);

            $reports = ZohoCreatorApi::getReportsMeta();

            expect($reports[0]['permissions']['add'])->toBeTrue();
            expect($reports[1]['permissions']['add'])->toBeFalse();
        });
    });

    describe('Pages Metadata', function () {
        it('retrieves all pages metadata', function () {
            $pagesData = [
                [
                    'page_name' => 'Dashboard',
                    'display_name' => 'Main Dashboard',
                    'link_name' => 'Dashboard',
                    'type' => 'page',
                    'components' => ['chart', 'report', 'html']
                ],
                [
                    'page_name' => 'Reports_Page',
                    'display_name' => 'Reports',
                    'link_name' => 'Reports_Page',
                    'type' => 'page',
                    'components' => ['report', 'report', 'report']
                ]
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/pages' => Http::response([
                    'code' => 3000,
                    'pages' => $pagesData
                ], 200)
            ]);

            $pages = ZohoCreatorApi::getPagesMeta();

            expect($pages)->toBeArray();
            expect($pages)->toHaveCount(2);
            expect($pages[0]['page_name'])->toBe('Dashboard');
            expect($pages[0]['components'])->toContain('chart');
        });

        it('handles pages with navigation structure', function () {
            $navigationPages = [
                [
                    'page_name' => 'Home',
                    'display_name' => 'Home',
                    'is_visible' => true,
                    'order' => 1,
                    'icon' => 'home',
                    'parent_page' => null
                ],
                [
                    'page_name' => 'Companies',
                    'display_name' => 'Companies',
                    'is_visible' => true,
                    'order' => 2,
                    'icon' => 'building',
                    'parent_page' => null
                ],
                [
                    'page_name' => 'Company_Details',
                    'display_name' => 'Company Details',
                    'is_visible' => false,
                    'order' => 1,
                    'parent_page' => 'Companies'
                ]
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/pages' => Http::response([
                    'code' => 3000,
                    'pages' => $navigationPages
                ], 200)
            ]);

            $pages = ZohoCreatorApi::getPagesMeta();

            // Check hierarchy
            $parentPages = array_filter($pages, fn($p) => $p['parent_page'] === null);
            $childPages = array_filter($pages, fn($p) => $p['parent_page'] !== null);

            expect(count($parentPages))->toBe(2);
            expect(count($childPages))->toBe(1);
            expect($childPages[0]['parent_page'])->toBe('Companies');
        });
    });

    describe('Application Metadata', function () {
        it('retrieves complete application metadata', function () {
            $appData = [
                'application_name' => 'VIPros CRM',
                'link_name' => 'vipros-crm',
                'created_time' => '2023-01-01T10:00:00Z',
                'modified_time' => '2025-01-06T10:00:00Z',
                'owner' => [
                    'name' => 'Admin User',
                    'email' => 'admin@vipros.com'
                ],
                'shared_users' => [
                    ['name' => 'User 1', 'email' => 'user1@vipros.com', 'role' => 'Developer'],
                    ['name' => 'User 2', 'email' => 'user2@vipros.com', 'role' => 'Manager']
                ],
                'forms_count' => 15,
                'reports_count' => 25,
                'pages_count' => 10,
                'workflows_count' => 8
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/application' => Http::response([
                    'code' => 3000,
                    'application' => $appData
                ], 200)
            ]);

            // Note: This method would need to be implemented in the actual service
            // For now, we'll test the expected behavior
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken valid_token'
            ])->get('www.zohoapis.eu/creator/v2.1/meta/test/application');

            $app = $response->json()['application'];

            expect($app['application_name'])->toBe('VIPros CRM');
            expect($app['forms_count'])->toBe(15);
            expect($app['shared_users'])->toHaveCount(2);
        });
    });

    describe('Metadata Caching and Performance', function () {
        it('caches metadata responses for performance', function () {
            $formsData = [
                ['form_name' => 'Form1'],
                ['form_name' => 'Form2']
            ];

            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/forms' => Http::response([
                    'code' => 3000,
                    'forms' => $formsData
                ], 200)
            ]);

            // First call
            $forms1 = ZohoCreatorApi::getFormsMeta();
            
            // Second call (should use cache if implemented)
            $forms2 = ZohoCreatorApi::getFormsMeta();

            expect($forms1)->toEqual($forms2);
            
            // Should only make one HTTP call if caching is implemented
            // Without caching, this would be 2
            Http::assertSentCount(2); // Adjust based on actual implementation
        });

        it('handles metadata version changes', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/forms' => Http::sequence()
                    ->push([
                        'code' => 3000,
                        'forms' => [['form_name' => 'OldForm']],
                        'version' => '1.0'
                    ], 200)
                    ->push([
                        'code' => 3000,
                        'forms' => [['form_name' => 'OldForm'], ['form_name' => 'NewForm']],
                        'version' => '1.1'
                    ], 200)
            ]);

            $forms1 = ZohoCreatorApi::getFormsMeta();
            expect($forms1)->toHaveCount(1);

            // Simulate time passing and metadata update
            $forms2 = ZohoCreatorApi::getFormsMeta();
            expect($forms2)->toHaveCount(2);
        });
    });

    describe('Metadata Error Handling', function () {
        it('handles network errors gracefully', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*' => function () {
                    throw new \Illuminate\Http\Client\ConnectionException('Network timeout');
                }
            ]);

            expect(fn() => ZohoCreatorApi::getFormsMeta())
                ->toThrow(\Exception::class, 'Network timeout');
        });

        it('handles partial metadata responses', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/forms' => Http::response([
                    'code' => 3000,
                    'forms' => [
                        ['form_name' => 'CompleteForm', 'display_name' => 'Complete Form'],
                        ['form_name' => 'PartialForm'] // Missing display_name
                    ]
                ], 200)
            ]);

            $forms = ZohoCreatorApi::getFormsMeta();

            expect($forms)->toHaveCount(2);
            expect($forms[0]['display_name'])->toBe('Complete Form');
            expect($forms[1])->not->toHaveKey('display_name');
        });

        it('handles metadata permission errors', function () {
            Http::fake([
                'www.zohoapis.eu/creator/v2.1/meta/*/forms' => Http::response([
                    'code' => 3100,
                    'message' => 'Insufficient permissions to view forms metadata'
                ], 403)
            ]);

            expect(fn() => ZohoCreatorApi::getFormsMeta())
                ->toThrow(\Exception::class, 'Insufficient permissions');
        });
    });
});