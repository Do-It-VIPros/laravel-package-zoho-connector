# Revoking Refresh Tokens in Zoho Creator API v2.1

## Endpoint Details

### Request URL
`https://<base_accounts_url>/oauth/v2/token/revoke?token=<token>`

### Request Method
`POST`

## Parameters

| Parameter | Description | Required |
|-----------|-------------|----------|
| `base_accounts_url` | Zoho account base URL (e.g., `accounts.zoho.com`) | Yes |
| `token` | Refresh token to revoke | Yes |

## Example Request
```bash
curl "https://accounts.zoho.com/oauth/v2/token/revoke?token=1000.8cb99dxxxxxxxxxxxxx9be93.9b8xxxxxxxxxxxxxxxf" -X POST
```

## Response

### Success Response
```json
{
   "status": "success"
}
```

## Notes
- Can revoke refresh tokens or access tokens for JavaScript client applications
- Invalid tokens will result in a 400 HTTP status code

## Security Considerations
- Only revoke tokens that are no longer needed
- Ensure secure handling of token information