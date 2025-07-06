# Zoho Creator Publish API - Get Records v2.1

## Overview

The Zoho Creator Get Records API allows fetching records from a published report in a Zoho Creator application. Key features include:

- Fetch up to 1000 records per request
- Filter records using search criteria
- Customize field configurations
- Support for various data types and field formats

## API Endpoint

```
https://<base_url>/creator/v2.1/publish/<account_owner_name>/<app_link_name>/report/<report_link_name>
```

## Key Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `criteria` | String | Optional filter for records |
| `privatelink` | String | Report private link |
| `field_config` | String | Field view configuration (quick_view, detail_view, custom, all) |
| `fields` | String | Specific fields to retrieve |
| `max_records` | Integer | Number of records to fetch (200-1000) |

## Search Criteria Guidelines

- Use field, operator, and value combinations
- Support logical operators `&&` (AND) and `||` (OR)
- Enclose string values in quotes
- Use specific formatting for date and time fields

## Response Structure Updates in V2.1

- Subform and lookup fields now use `zc_display_value`
- Multivalue field responses restructured
- More efficient value parsing

## Example Request

```curl
curl "https://www.zohoapis.com/creator/v2.1/publish/jason18/zylker-org/report/All_Employees?privatelink=1223456789poiuyytrewq" -X GET
```

## Supported Environments

- Production environments only
- Varies by data center (US, EU)