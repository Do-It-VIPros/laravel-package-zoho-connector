# Get Records - Zoho Creator API v2.1

## Overview

The Get Records API allows fetching records from a Zoho Creator report with various configuration options.

## Request Details

### Endpoint
`https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/report/<report_link_name>`

### HTTP Method
GET

### Authentication
- Requires OAuth token in Authorization header
- Scope: `ZohoCreator.report.READ`

## Query Parameters

| Parameter | Type | Description | Default |
|-----------|------|-------------|---------|
| `criteria` | string | Filter records using search conditions | Fetch first 200 records |
| `field_config` | string | Specify field view options | `quick_view` |
| `fields` | string | Comma-separated field link names | All fields |
| `max_records` | int | Number of records to fetch | 200 (max 1000) |

## Search Criteria Examples

- `Name.last_name == "Boyle"`
- `Total >= 100.00`
- `Appointment_Date >= '13-Apr-2020' && Appointment_Date <= '18-Apr-2020'`

## Response Format

- Default: JSON
- Supports CSV
- Maximum 1000 records per request
- Includes detailed field information with `zc_display_value`

## Key Updates in V2.1

- New parameters like `record_cursor`
- Improved multi-value field parsing
- Enhanced display value representation

## Sample Request

```bash
curl "https://www.zohoapis.com/creator/v2.1/data/jason18/zylker-store/report/All_Orders" \
  -H "Authorization: Zoho-oauthtoken <token>"
```