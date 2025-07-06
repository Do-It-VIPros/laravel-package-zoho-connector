# Zoho Creator Upload File API (v2.1)

## Overview

The Upload File API allows updating files in various field types within a Zoho Creator application, including:
- File upload
- Image
- Audio
- Video
- Signature

## Request Details

### Endpoint
```
https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/report/<report_link_name>/<record_ID>/<field_link_name>/upload
```

### Method
POST

### Authentication
- Requires OAuth token
- Scope: `ZohoCreator.report.CREATE`

## File Type and Size Limits

### Supported File Types
- Image fields: Images
- Signature fields: Images
- File upload fields: Various file types
- Audio fields: Audio files
- Video fields: Video files

### Size Restrictions
- Image/Signature fields: Max 10 MB
- File upload/Audio/Video fields: Max 50 MB

## Optional Parameters

### `skip_workflow`
Prevents workflow triggers during file upload.

Possible values:
- `form_workflow`
- `schedules`
- `all`

## Sample Request
```curl
curl "https://www.zohoapis.com/creator/v2.1/data/jason18/zylker-store/report/Inventory_Report/3888834000000114050/Product_Manual/upload?skip_workflow=["schedules","form_workflow"]" -X POST -H "Authorization: Zoho-oauthtoken <token>"
```

## Response Example
```json
{
  "code": 3000,
  "filename": "Screen Shot 2019-12-20 at 10.56.27 AM.png",
  "filepath": "1580987985461_Screen_Shot_2019-12-20_at_10.56.27_AM.png",
  "message": "File uploaded successfully !"
}
```