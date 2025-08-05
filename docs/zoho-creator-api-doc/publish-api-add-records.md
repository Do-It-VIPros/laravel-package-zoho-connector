# Zoho Creator Publish API - Add Records (v2.1)

## Key Features

### Overview
- Adds one or more records to a Zoho Creator form
- Maximum of 200 records per request
- Supports published app components only
- Restricted to production environments

### Request Details
- **Method**: POST
- **URL Structure**: 
  `https://<base_url>/creator/v2.1/publish/<account_owner_name>/<app_link_name>/form/<form_link_name>`

### Unique API Characteristics
- Supports most field types with specific restrictions
- Validates input against form-specific rules
- Handles complex field types like:
  - Nested objects (Name, Address)
  - Subforms
  - Lookup references

## Field Value Setting Guidelines

### Supported Field Types
- Text fields (max 255 characters)
- Numeric fields
- Dropdown and radio fields
- Multi-select fields
- Lookup fields

### Unsupported Field Types
- Add notes
- Formula
- Auto number
- File upload
- Signature
- Prediction fields

## Data Validation Checks
- Mandatory field requirements
- Unique value constraints
- Character and digit limits
- User/IP entry restrictions
- Custom form validation rules

## Response Structure
- `result`: Created record details
- `code`: Success/failure indicator
- `message`: Submission status
- `data`: Record IDs and optional field values
- `tasks`: Optional redirection information

## Sample Request Example
```json
{
  "data": [{
    "Single_Line": "Sample Text",
    "Number": "12345",
    "Name": {
      "first_name": "Jason",
      "last_name": "Bowley"
    }
  }],
  "result": {
    "fields": ["Single_Line", "Number"],
    "message": true
  }
}
```

## Key Differences from Standard APIs
- Publish API specific
- Production environment only
- Strict field validation
- Limited to 200 record creation per request