# Make an Authorization Request - Zoho Creator API v2.1

## Overview

Zoho Creator uses the authorization code grant type, requiring an authorization code to obtain an access token. The code generation process varies based on the client type.

## Server-Based Client Authorization

### Request Parameters

| Parameter | Type | Description | Required |
|-----------|------|-------------|----------|
| `client_id` | String | Client ID from application registration | Yes |
| `scope` | String | Zoho Creator scope to access | Yes |
| `redirect_uri` | URL | Authorized redirect URI | Yes |
| `access_type` | String | `offline` or `online` | Optional |
| `prompt` | String | Set to `consent` for mandatory user consent | Optional |

### Authorization Request URL

```
https://accounts.zoho.com/oauth/v2/auth?response_type=code&client_id=<client_id>&scope=<scope>&redirect_uri=<redirect_uri>&access_type=offline
```

### Response Details

- **Code Validity**: 1 minute
- Response includes:
  - `code`: Short-lived grant token
  - `location`: User's domain location
  - `accounts-server`: Zoho Accounts URL for token generation

## Self Client Authorization

1. Register a self client in Zoho Developer Console
2. Navigate to "Generate Code" tab
3. Enter required scopes
4. Select code validity duration
5. Optionally add scope description
6. Click "CREATE"

### Key Considerations

- Suitable for applications without a domain/redirect URI
- Ideal for standalone server-side back-end jobs