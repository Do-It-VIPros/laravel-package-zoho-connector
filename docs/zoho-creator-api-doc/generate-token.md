# Generate Access and Refresh Tokens - Zoho Creator API v2.1

## Token Generation Overview

### Request Details

#### Endpoint
`POST https://<base_accounts_url>/oauth/v2/token`

#### Parameters
- `grant_type`: `authorization_code`
- `client_id`: Your registered client ID
- `client_secret`: Your client secret
- `redirect_uri`: Authorized redirect URI
- `code`: Authorization code

### Request Example
```bash
curl "https://accounts.zoho.com/oauth/v2/token?grant_type=authorization_code&code=1000.xxxxxxd34d.xxxxxxx909a&client_id=1000.xxxxxxxxxxHF2C6H&redirect_uri=https://www.zylker.com/callback&client_secret=xxxxxxxxx4f4f7a" -X POST
```

### Response Parameters
- `access_token`: Token for API access
- `refresh_token`: Token to generate new access tokens
- `expires_in`: Token validity duration (3600 seconds)
- `api_domain`: API request domain
- `token_type`: Token type (Bearer)

### Response Example
```json
{
    "access_token": "1000.8cb99dxxxxxxxxxxxxx9be93.9b8xxxxxxxxxxxxxxxf",
    "refresh_token": "1000.3ph66exxxxxxxxxxxxx6ce34.3c4xxxxxxxxxxxxxxxf",
    "api_domain": "https://www.zohoapis.com",
    "token_type": "Bearer",
    "expires_in": 3600
}
```

### Important Notes
- Access tokens expire after 1 hour
- Maximum 5 refresh tokens per minute
- Use your Creator account's base URL for API requests

### Potential Errors
- `invalid_client`: Incorrect credentials
- `invalid_code`: Expired or used authorization code
- `invalid_redirect_uri`: Unregistered redirect URI