# Download File from Subform - API v2.1

## Overview

This API allows downloading files from specific subform record fields in Zoho Creator, supporting:
- File upload fields
- Image fields
- Audio fields
- Video fields
- Signature fields

## Request URL Structure

```
https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/report/<report_link_name>/<record_ID>/<subform_link_name>.<field_link_name>/<subform_record_ID>/download
```

## Authentication Requirements

- **Method**: GET
- **Authorization Header**: Zoho-oauthtoken
- **OAuth Scope**: `ZohoCreator.report.READ`

## Key Parameters

- `base_url`: Zoho Creator's API endpoint
- `account_owner_name`: Creator account username
- `app_link_name`: Target application link name
- `report_link_name`: Target report link name
- `record_ID`: Main form record ID
- `subform_link_name`: Subform link name
- `field_link_name`: Specific file field link name
- `subform_record_ID`: Subform record ID

## Sample Request

```bash
curl "https://www.zohoapis.com/creator/v2.1/data/jason18/zylker-store/report/All_Orders/3888834000000114050/Line_Items.Product_Manual/3888834000000104037/download" -X GET -H "Authorization: Zoho-oauthtoken <token>"
```

## Environments

- Production (default)
- Development
- Stage