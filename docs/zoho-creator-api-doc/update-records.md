# Zoho Creator Update Records API (v2.1)

## Overview

The Zoho Creator Update Records API allows updating records in a report with the following key features:

### API Endpoint Details

- **Method**: PATCH
- **URL**: `https://<base_url>/creator/v2.1/data/<account_owner_name>/<app_link_name>/report/<report_link_name>`
- **Maximum Records**: 200 records per request

## Key Parameters

### Mandatory Parameters

1. **criteria** (Required)
   - Filters target records for update
   - Example: `"criteria": "Status == \"Closed\""`

2. **data**
   - Contains field values to update
   - Supports most field types except:
     - Add notes
     - Formula
     - Auto number
     - File upload
     - Signature

### Optional Parameters

- **skip_workflow**
  - Prevents specific workflow triggers
  - Possible values: `["form_workflow", "schedules", "all"]`

- **process_until_limit**
  - Handles updates when records exceed 200

## Search Criteria Examples

```json
// Update records where Status is Closed
"criteria": "Status == \"Closed\""

// Multiple condition criteria
"criteria": "Status == \"Open\" || Status == \"In-progress\""

// Complex nested criteria
"criteria": "Status == \"Closed\" && Email.endswith(\"zylker.com\")"
```

## Sample Update Request

```json
{
  "criteria": "Single_Line.contains(\"Text\")",
  "data": {
    "Email": "user@example.com",
    "Single_Line": "Updated Text",
    "Number": "1000"
  },
  "skip_workflow": ["form_workflow"],
  "result": {
    "fields": ["Single_Line", "Number"],
    "message": true
  }
}
```

## Important Validation Checks

- Respects form-level data validations
- Checks mandatory fields
- Enforces unique value constraints
- Validates field-specific rules