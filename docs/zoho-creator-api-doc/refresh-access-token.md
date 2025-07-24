# Zoho Creator API: Refreshing Access Tokens

## Overview

Access tokens in the Zoho Creator API expire after one hour. To maintain continuous API access, developers must refresh tokens using a specific OAuth process.

## Token Refresh Request

### Endpoint
```
POST https://<base_accounts_url>/oauth/v2/token
```

### Required Parameters
- `refresh_token`: User's unique refresh token
- `client_id`: Application's client ID
- `client_secret`: Application's client secret
- `grant_type`: Must be `refresh_token`

### Example Request
```bash
curl "https://accounts.zoho.com/oauth/v2/token?refresh_token=1000.3ph66exxxxxxx6ce34.3c4xxxxxxxxxf&client_id=1000.xxxxxxxxxxHF2C6H&client_secret=xxxxxxxxx4f4f7a&grant_type=refresh_token" -POST
```

## Response Structure

```json
{
    "access_token": "1000.6jh82dxxxxxxxxxxxxx9be93.9b8xxxxxxxxxxxxxxxf",
    "expires_in": 3600,
    "api_domain": "https://www.zohoapis.com",
    "token_type": "Bearer"
}
```

## Important Limitations

- Maximum 5 refresh tokens per minute
- Users can have up to 20 refresh tokens
- Each refresh token supports 30 active access tokens

## Potential Error Scenarios

- `invalid_client`: Incorrect credentials
- `invalid_code`: Expired or revoked refresh token

## Best Practices

1. Always store refresh tokens securely
2. Implement automatic token refresh before expiration
3. Handle token refresh errors gracefully
4. Use the specific Creator account base URL for API requests