# Get Record by ID - API v2.1

## Overview

This API allows fetching data for a specific record from a report using its record ID.

## Request Details

### Endpoint
```
GET https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/report/<report_link_name>/<record_id>
```

### Headers

| Key | Value | Description |
|-----|-------|-------------|
| Authorization | Zoho-oauthtoken | Authentication token |
| environment | development/stage | Environment stage |
| accept | application/json/text/csv | Response format |

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| field_config | string | Optional. Specifies which fields to fetch |
| fields | string | Optional. Specific field link names to retrieve |

### Field Config Options
- `quick_view`: Fields in quick view layout
- `detail_view`: Fields in detailed view layout
- `custom`: Specified fields
- `all`: Fields in both quick and detailed views

## Response Structure

The response includes:
- Record ID
- Field values
- Nested objects for complex fields
- Display values for lookup and multiselect fields

## Key Updates in V2.1

1. New parameters added:
   - `field_config`
   - `fields`

2. Improved response structures:
   - Subform and lookup fields now use key-value pairs
   - Multivalue fields have more structured responses

## Authentication

Requires OAuth scope: `ZohoCreator.report.READ`

## Example Request

```curl
curl "https://www.zohoapis.com/creator/v2.1/data/jason18/zylker-store/report/All_Orders/3888833000000114027" 
-X GET 
-H "Authorization: Zoho-oauthtoken <token>"
```