# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with the Zoho Connector Laravel package.

## Package Overview

The **Zoho Connector** package (`agencedoit/zohoconnector`) is a Laravel package that provides a comprehensive API interface for Zoho Creator integration. It handles authentication, CRUD operations, bulk processing, and metadata retrieval from Zoho Creator applications.

## Package Structure

### Core Components
- **Service**: `ZohoCreatorService` - Main service handling API interactions
- **Facade**: `ZohoCreatorFacade` - Laravel facade for easy access (`ZohoCreatorApi`)
- **Token Management**: `ZohoTokenManagement` helper for OAuth2 token lifecycle
- **Bulk Processing**: Queue-based jobs for large data operations
- **Models**: Database models for token storage and bulk operation history

### Key Features
- **CRUD Operations**: Create, read, update records in Zoho Creator
- **Bulk Operations**: Handle large datasets with automated queue processing
- **File Upload**: Support for file attachments to Zoho records
- **Custom Functions**: Execute custom Zoho Creator API functions
- **Metadata Access**: Retrieve forms, fields, reports, and pages metadata
- **OAuth2 Authentication**: Automated token management and refresh

### Testing Strategy
- **Comprehensive Test Suite**: 4-phase testing implementation (Foundation, Unit, Feature, Integration)
- **API v2.1 Validation**: Tests validated against official Zoho Creator API v2.1 documentation
- **Documentation**: Complete testing strategy and implementation guide in `/tasks/` directory
- **Coverage Target**: 95%+ test coverage across all package components

## Configuration

### Environment Variables

| Variable | Required | Description | Default |
|----------|----------|-------------|---------|
| `ZOHO_ACCOUNT_DOMAIN` | No | Zoho domain (eu, com, jp, in, com.au) | `eu` |
| `ZOHO_CLIENT_ID` | Yes | Client ID from Zoho API console | - |
| `ZOHO_CLIENT_SECRET` | Yes | Client secret from Zoho API console | - |
| `ZOHO_SCOPE` | No | API permissions scope | `ZohoCreator.report.READ` |
| `ZOHO_USER` | Yes | Zoho username | - |
| `ZOHO_APP_NAME` | Yes | Zoho Creator app identifier | - |
| `ZOHO_TOKENS_TABLE` | No | Database table for tokens | `zoho_connector_tokens` |
| `ZOHO_CREATOR_ENVIRONMENT` | No | Zoho environment (production/development) | `production` |
| `ZOHO_REQUEST_TIMEOUT` | No | API request timeout in seconds | `30` |
| `ZOHO_BULK_DOWNLOAD_PATH` | No | Path for bulk download files | `storage/zohoconnector` |
| `ZOHO_BULKS_TABLE` | No | Table for bulk operation history | `zoho_connector_bulk_history` |
| `ZOHO_BULK_QUEUE` | No | Queue name for bulk processing | `default` |

### Setup Requirements

1. **Zoho API Console Setup**:
   - Create Server-based Application at [api-console.zoho.com](https://api-console.zoho.com)
   - Set redirect URI: `{APP_URL}/zoho/request-code-response`
   - Obtain client_id and client_secret

2. **PHP Extensions**:
   - ZIP extension required for bulk operations: `sudo apt-get install php8.2-zip`

3. **Laravel Setup**:
   - Run migrations: `php artisan migrate`
   - Configure environment variables
   - Set up queue processing for bulk operations: `php artisan queue:work`

## API Usage

### Basic Operations

```php
use ZohoCreatorApi;

// Get records from a report
$records = ZohoCreatorApi::get('my_report', 'criteria_string');

// Get all records (handles pagination automatically)
$allRecords = ZohoCreatorApi::getAll('my_report', 'criteria_string');

// Get specific record by ID
$record = ZohoCreatorApi::getByID('my_report', 'record_id');

// Create new record
$result = ZohoCreatorApi::create('form_name', $attributes, $additionalFields);

// Update existing record
$result = ZohoCreatorApi::update('report_name', 'record_id', $attributes);

// Upload file to record
$result = ZohoCreatorApi::upload('report_name', 'record_id', 'field_name', $file);
```

### Bulk Operations

```php
// Automated bulk processing (recommended)
ZohoCreatorApi::getWithBulk('report_name', 'callback_url', 'criteria');

// Manual bulk process
$bulkId = ZohoCreatorApi::createBulk('report_name', 'criteria');
$status = ZohoCreatorApi::readBulk('report_name', $bulkId);
$result = ZohoCreatorApi::downloadBulk('report_name', $bulkId);
```

### Custom Functions

```php
// Custom GET function
$result = ZohoCreatorApi::customFunctionGet($url, $parameters, $publicKey);

// Custom POST function
$result = ZohoCreatorApi::customFunctionPost($url, $data, $publicKey);
```

### Metadata Operations

```php
// Get forms metadata
$forms = ZohoCreatorApi::getFormsMeta();

// Get fields metadata for a form
$fields = ZohoCreatorApi::getFieldsMeta('form_name');

// Get reports metadata
$reports = ZohoCreatorApi::getReportsMeta();

// Get pages metadata
$pages = ZohoCreatorApi::getPagesMeta();
```

## Development & Testing

### Available Routes

**Development Environment Only** (`APP_ENV != production`):
- `/zoho/test` - Test connection and configuration
- `/zoho/wip` - Work in progress testing endpoint
- `/zoho/reset-tokens` - Reset authentication tokens

**Always Available** (when not ready):
- `/zoho/request-code` - Initialize OAuth2 flow
- `/zoho/request-code-response` - OAuth2 callback endpoint

### Authentication Flow

1. **Initial Setup**: Navigate to `/zoho/request-code` to start OAuth2 flow
2. **Authorization**: User authorizes application in Zoho
3. **Token Storage**: Access and refresh tokens stored in database
4. **Auto-Refresh**: Tokens automatically refreshed when expired

### Testing

```bash
# Run package tests
vendor/bin/phpunit tests/

# Test specific features
vendor/bin/phpunit tests/Feature/ZohoControllerTest.php
vendor/bin/phpunit tests/Unit/ZohoControllerTest.php
```

## Database Schema

### Tokens Table (`zoho_connector_tokens`)
- `token` - Current access token
- `refresh_token` - Token for refreshing access
- `token_created_at` - Token creation timestamp
- `token_peremption_at` - Token expiration timestamp
- `token_duration` - Token validity duration

### Bulk History Table (`zoho_connector_bulk_history`)
- Tracks bulk operation status and results
- Used by `ZohoCreatorBulkProcess` job

## Error Handling

The service includes comprehensive error handling:
- **Token Management**: Automatic token refresh on expiration
- **API Errors**: Detailed logging of API response errors
- **Bulk Operations**: Retry logic for failed bulk processes
- **Service Checks**: Validation of configuration and readiness

## Integration Notes

### Queue Processing
- Bulk operations require active queue worker
- Configure queue connection in Laravel
- Use `--tries=3` for retry on failures
- Specify custom queue with `--queue` parameter

### File Operations
- Bulk downloads stored in `ZOHO_BULK_DOWNLOAD_PATH`
- ZIP files automatically extracted and cleaned up
- JSON transformation of CSV data for easier processing

### Scopes and Permissions
Required Zoho Creator API scopes:
- `ZohoCreator.report.READ` - Basic read operations
- `ZohoCreator.report.CREATE` - Create operations
- `ZohoCreator.report.UPDATE` - Update operations
- `ZohoCreator.report.DELETE` - Delete operations
- `ZohoCreator.bulk.CREATE` - Bulk operations
- `ZohoCreator.meta.application.READ` - Metadata access
- `ZohoCreator.meta.form.READ` - Form metadata

## Development Workflow

1. **Configuration**: Set up environment variables and Zoho API credentials
2. **Authentication**: Complete OAuth2 flow via `/zoho/request-code`
3. **Testing**: Use `/zoho/test` for connection verification
4. **Implementation**: Use facade methods for API operations
5. **Bulk Processing**: Set up queue workers for large data operations
6. **Error Handling**: Monitor logs for API errors and token issues

### Testing Implementation

For comprehensive testing strategy and implementation:
- **Main Guide**: `/tasks/task-tests-index.md` - Complete 4-phase testing strategy
- **Phase 1**: `/tasks/task-tests-phase-1-foundation.md` - Infrastructure setup (✅ COMPLETED)
- **Phase 2**: `/tasks/task-tests-phase-2-unit-tests.md` - Unit tests for core components
- **Phase 3**: `/tasks/task-tests-phase-3-feature-tests.md` - Feature and workflow tests
- **Phase 4**: `/tasks/task-tests-phase-4-integration.md` - Integration and cross-package tests

Current Status: **Phase 1 Foundation COMPLETED** - API v2.1 validated, all infrastructure ready

## Security Considerations

- Store Zoho credentials securely in environment variables
- Use appropriate scopes for API access
- Implement rate limiting for API calls
- Secure token storage in database
- Validate all input data before API calls

## API Documentation

### Zoho Creator API v2.1 Reference
Complete local documentation available in `/docs/zoho-creator-api-doc/`:
- **Authentication**: OAuth2 flow, token management, and security
- **CRUD Operations**: Add, get, update, delete records with examples
- **Status Codes**: Comprehensive error codes and troubleshooting
- **File Operations**: Upload and manage file attachments
- **API Features**: v2.1 improvements and parameter references

Access the documentation via `/docs/zoho-creator-api-doc/README.md` for structured navigation.

## Version Compatibility

- **Laravel**: Compatible with Laravel 11+ (tested with Laravel 12)
- **PHP**: Requires PHP 8.2+
- **Zoho Creator API**: Uses v2.1
- **Dependencies**: Minimal external dependencies for maximum compatibility