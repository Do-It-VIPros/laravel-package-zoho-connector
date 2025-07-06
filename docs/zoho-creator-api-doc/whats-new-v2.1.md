# Zoho Creator API v2.1: What's New

## Overview

Zoho Creator has released API v2.1 with several significant improvements and new features to enhance API functionality and flexibility.

## Key Updates

### 1. Request URLs
- All new request URLs now use `v2.1` instead of `v2`

### 2. New Header Parameters

#### record_cursor
- Optional header for bulk record fetching
- Retrieves consecutive 200 records by default
- Applicable in:
  - Get Records (Data API)
  - Get Records (Publish API)

#### accept
- Optional header to specify response format
- Possible values:
  - `application/json` (default)
  - `text/csv`
- Applicable in multiple record retrieval APIs

### 3. New API Parameters

#### field_config
- Determines which record fields to fetch
- Options:
  - `quick_view` (default)
  - `detail_view`
  - `custom`
  - `all`

#### fields
- Specifies exact fields to retrieve when using `custom` field_config
- Requires comma-separated field link names

#### max_records
- Controls number of records per fetch
- Options:
  - 200 (default)
  - 500
  - 1000

#### skip_workflow
- Allows skipping specific workflow triggers
- Options:
  - `form_workflow`
  - `schedules`
  - `all`
- Note: Blueprints and approvals cannot be skipped

### 4. Response Structure Revisions
- Updated display field values for subforms and lookup fields
- Simplified data type handling for:
  - Multiselect and Checkbox fields
  - Single select lookup
  - Integration fields
  - Multiselect lookup fields

## Conclusion
API v2.1 provides more granular control over data retrieval, formatting, and workflow management.