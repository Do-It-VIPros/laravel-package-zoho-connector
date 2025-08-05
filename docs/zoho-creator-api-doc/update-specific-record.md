# Update Record by ID - Zoho Creator API v2.1

## Overview

This API allows updating a specific record in a Zoho Creator application by its record ID.

## Request Details

### Endpoint
```
PATCH https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/report/<report_link_name>/<record_ID>
```

### Authentication
- Header: `Authorization: Zoho-oauthtoken <token>`
- OAuth Scope: `ZohoCreator.report.UPDATE`

### Request Body Parameters

#### Required Fields
- `data`: Object containing field updates

#### Optional Parameters
- `skip_workflow`: Prevents specific workflow triggers
  - Possible values: `["form_workflow"]`, `["schedules"]`, `["all"]`

## Field Value Restrictions

### General Restrictions
- Most field types can be updated
- Excluded fields: 
  - Add notes
  - Formula
  - Auto number
  - File upload
  - Signature
  - Prediction fields

### Specific Field Limitations
- Text fields: Max 255 characters
- Numeric fields: Respect max digits/decimal settings
- Dropdown/Radio: Must use predefined choices
- Multi-select: Comma-separated values
- Lookup: Requires valid record ID

## Sample Request

```json
{
  "data": {
    "Email": "jason@zylker.com",
    "Name": {
      "first_name": "Jason",
      "last_name": "Bowley"
    }
  },
  "skip_workflow": ["form_workflow"]
}
```

## Sample Response

```json
{
  "code": 3000,
  "data": {
    "Email": "jason@zylker.com",
    "ID": "3888833000000114027"
  },
  "message": "Data Updated Successfully!"
}
```

## Data Validation

Updates are subject to form-level validations:
- Mandatory fields
- Unique value constraints
- Character/digit limits
- Custom validation rules