# Zoho Creator Download File API (v2.1)

## Overview

The Download File API allows retrieving files from various field types in a Zoho Creator application, including:
- File upload fields
- Image fields
- Audio fields
- Video fields
- Signature fields

## API Request Details

### Endpoint
```
https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/report/<report_link_name>/<record_ID>/<field_link_name>/download
```

### Request Method
- `GET`

### Required Headers
| Header | Value | Description |
|--------|-------|-------------|
| Authorization | Zoho-oauthtoken | OAuth authentication token |
| environment | development/stage/production (optional) | Environment stage |

### OAuth Scope
`ZohoCreator.report.READ`

## Sample Request
```bash
curl "https://www.zohoapis.com/creator/v2.1/data/jason18/zylker-store/report/Inventory_Report/3888834000000114050/Product_Manual/download" -X GET -H "Authorization: Zoho-oauthtoken <token>"
```

## Supported Languages
- Curl
- Deluge
- Java
- JavaScript
- Python

## Notes
- Default environment is production
- Requires valid OAuth token
- Supports downloading files from specific record fields