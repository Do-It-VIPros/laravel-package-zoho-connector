# Zoho Creator API Status Codes

## Overview
This document details the HTTP status codes and error messages for Zoho Creator's REST API v2.1.

## Status Code Categories

### 200 OK Status Codes

#### Account Limits
- **Code 4000**: "Developer API limit reached. Upgrade to execute more REST API calls."
- **Code 3060**: "Account's record limit reached. Please upgrade to add more data."

#### Request Limitations
- **Code 3970**: Maximum 200 records can be fetched per request
- **Code 3960**: Maximum 200 records can be processed per request
- **Code 3950**: Maximum 200 records can be added per request

#### Resource Availability
- **Code 3930**: No reports available
- **Code 3920**: No pages available
- **Code 3910**: No forms available

### 403 Forbidden Status Codes

#### Permission Errors
- **Code 2933**: "No permission to access this application"
- **Code 2899**: "Permission denied to add record(s)"
- **Code 2898**: "Permission denied to view record(s)"
- **Code 2897**: "Permission denied to update record(s)"
- **Code 2896**: "Permission denied to delete record(s)"

### 404 Not Found Status Codes

#### Resource Identification Errors
- **Code 3100**: "No records found for the given criteria"
- **Code 2894**: "No report named '<REPORT_NAME>' found"
- **Code 2893**: "No form named '<FORM_NAME>' found"
- **Code 2892**: "No application named '<APPLICATION_NAME>' found"

## Key Recommendations

1. Check API request formatting
2. Verify permissions
3. Respect record and API call limits
4. Use correct OAuth tokens
5. Validate input data

## Error Handling

When encountering errors:
- Review the specific status code
- Check permissions
- Validate input data
- Upgrade account if necessary
- Contact Zoho support if persistent issues occur