# Delete Record by ID - Zoho Creator API v2.1

## Overview

This API allows deletion of a specific record by its unique ID in a Zoho Creator application.

## Request Details

### Endpoint
```
DELETE https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/report/<report_link_name>/<record_ID>
```

### Headers

| Header | Value | Description |
|--------|-------|-------------|
| Authorization | Zoho-oauthtoken | Authentication token |
| environment | development/stage | Optional environment specification |

### OAuth Scope
`ZohoCreator.report.DELETE`

### Optional Parameters
- `skip_workflow`: Prevents form workflows from triggering
  - Possible value: `["form_workflow"]`

## Sample Request

```bash
curl "https://www.zohoapis.com/creator/v2.1/data/jason18/zylker-store/report/All_Orders/3888833000000114027" -X DELETE -H "Authorization: Zoho-oauthtoken <token>"
```

## Response Codes

| Code | Description |
|------|-------------|
| 3000 | Successful deletion |

## Response Structure

```json
{
  "code": 3000,
  "data": {
    "ID": <record_id>
  },
  "message": "Record Deleted Successfully!"
}
```

## Notes
- Blueprints cannot be skipped during record deletion
- Default behavior triggers all associated workflows