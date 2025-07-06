# Get Record by ID - Zoho Creator Publish API v2.1

## Overview

This Publish API allows fetching data for a specific record from a Zoho Creator report using its unique record ID.

## Key Features

### API Specification
- **Version**: V2.1
- **Method**: GET
- **Base URL**: `https://<base_url>/creator/v2.1/publish/...`

### New Parameters in V2.1

1. **field_config** options:
   - `quick_view`: Fetch quick view layout fields
   - `detail_view`: Fetch detailed view layout fields
   - `custom`: Fetch specific fields
   - `all`: Fetch fields from both layouts

2. **Response Improvements**:
   - Enhanced display value parsing
   - Updated multivalue field response structures

## Request URL Structure

```
https://<base_url>/creator/v2.1/publish/<account_owner_name>/<app_link_name>/report/<report_link_name>/<record_ID>
```

## Required Parameters

- `privatelink`: Mandatory report private link
- `field_config`: Optional (default: `quick_view`)
- `fields`: Optional (for custom field selection)

## Important Notes

- "Publish APIs are supported only for production environment"
- Cannot fetch related data block fields

## Sample Request

```curl
curl "https://www.zohoapis.com/creator/v2.1/publish/jason18/zylker-org/report/All_Employees/3888833000000114027?privatelink=1223456789poiuyytrewq" -X GET
```

## Potential Errors

Refer to Zoho Creator's status codes documentation for comprehensive error details.