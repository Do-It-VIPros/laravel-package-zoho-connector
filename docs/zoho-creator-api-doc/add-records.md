# Zoho Creator Add Records API (v2.1)

## Overview

The Add Records API allows adding one or more records to a Zoho Creator form, with the following key features:

### Request Details

**Endpoint:** 
`https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/form/<form_link_name>`

**Method:** POST

### Authentication

- **Header Required:** 
  - `Authorization: Zoho-oauthtoken <token>`
  - OAuth Scope: `ZohoCreator.form.CREATE`

### Parameters

#### Request Body Parameters

1. `data`: Array of record objects to be added
2. `skip_workflow` (Optional): Prevent specific workflows from triggering
   - Possible values: 
     - `"form_workflow"`
     - `"schedules"`
     - `"all"`

### Field Type Restrictions

- Supports most field types except:
  - Add notes
  - Formula
  - Auto number
  - Section
  - File upload
  - Audio/Video
  - Signature
  - Prediction fields

### Data Validations

Records must pass form-level validations, including:
- Mandatory fields
- Unique value constraints
- Character/digit limits
- User/IP entry restrictions

### Sample Request

```json
{
  "data": [
    {
      "Email": "jason@zylker.com",
      "Phone_Number": "+16103948336"
    }
  ],
  "skip_workflow": ["form_workflow", "schedules"],
  "result": {
    "fields": ["Phone_Number", "Email"],
    "message": true,
    "tasks": true
  }
}
```

### Sample Response

```json
{
  "result": [
    {
      "code": 3000,
      "data": {
        "Email": "jason@zylker.com",
        "Phone_Number": "+16103948336",
        "ID": "3888833000000121319"
      },
      "message": "Data Added Successfully!"
    }
  ]
}
```