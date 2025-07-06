# Zoho Creator API v2.1 Documentation

This directory contains comprehensive documentation for the Zoho Creator API v2.1, converted from the official Zoho documentation for easier reference and LLM consumption.

## Table of Contents

### General Information
- [What's New in v2.1](whats-new-v2.1.md) - New features and improvements
- [Status Codes](status-codes.md) - HTTP status codes and error messages

### Authentication
- [OAuth Overview](oauth-overview.md) - Authentication framework overview
- [Register Client](register-client.md) - Client application registration
- [Authorization Request](authorization-request.md) - Making authorization requests
- [Generate Token](generate-token.md) - Access and refresh token generation
- [Refresh Access Token](refresh-access-token.md) - Token refresh process

### Data Operations (CRUD)
- [Add Records](add-records.md) - Creating new records in forms
- [Get Records](get-records.md) - Fetching records from reports
- [Get Records by ID](get-records-by-id.md) - Fetching specific records
- [Update Records](update-records.md) - Updating existing records
- [Delete Specific Record](delete-specific-record.md) - Deleting records by ID

### File Operations
- [Upload File](upload-file.md) - File upload functionality
- [Download File](download-file.md) - File download from records
- [Download File from Subform](download-file-from-subform.md) - Download files from subform records

### Token Management
- [Revoke Tokens](revoke-tokens.md) - Token revocation process

### Record Operations (Specific)
- [Update Specific Record](update-specific-record.md) - Update record by ID

### Publish API
- [Publish API - Add Records](publish-api-add-records.md) - Add records via publish API
- [Publish API - Get Record by ID](publish-api-get-record-by-id.md) - Get specific record via publish API
- [Publish API - Get Records](publish-api-get-records.md) - Get records via publish API

### Custom APIs
- [Custom APIs Overview](custom-apis-overview.md) - Custom API concepts and implementation

## Key API Characteristics

- **Base URL**: `https://<base_url>/creator/v2.1/`
- **Authentication**: OAuth 2.0 with Bearer tokens
- **Rate Limits**: 5 refresh tokens per minute, maximum 200 records per request
- **Supported Formats**: JSON (default), CSV
- **Maximum Records per Request**: 200 for most operations, up to 1000 for reads

## Common OAuth Scopes

| Scope | Purpose |
|-------|---------|
| `ZohoCreator.form.CREATE` | Add records in forms |
| `ZohoCreator.report.READ` | Fetch report data |
| `ZohoCreator.report.UPDATE` | Update report records |
| `ZohoCreator.report.DELETE` | Delete report records |
| `ZohoCreator.meta.form.READ` | Get form metadata |

## Important Notes

- Access tokens expire after 1 hour
- Use appropriate scopes for security
- Respect form-level validations
- Handle workflow triggers carefully with `skip_workflow` parameter

For the most up-to-date information, refer to the [official Zoho Creator API documentation](https://www.zoho.com/creator/help/api/v2.1/).