# OAuth Authentication in Zoho Creator API v2.1

## Overview

Zoho Creator's API uses OAuth 2.0, an open standard authorization framework that allows client applications to access user data securely without exposing passwords.

## Key Terms

| Term | Description |
|------|-------------|
| Resource Owner | End user who can grant access to Zoho Creator account resources |
| Client Application | Web or mobile app requiring access to Creator resources |
| Client ID/Secret | Zoho-provided credentials for authenticating API requests |
| Authorization Code | Short-lived code exchangeable for an access token |
| Access Token | Token allowing access to protected resources |
| Refresh Token | Longer-lived token for generating additional access tokens |

## OAuth Authentication Flow

1. Client application requests authorization
2. Resource owner authenticates and authorizes client
3. Client receives authorization code
4. Client exchanges code for access and refresh tokens
5. Client uses access token to request protected resources

## Available Scopes

| Scope | Purpose |
|-------|---------|
| `ZohoCreator.form.CREATE` | Add records in forms |
| `ZohoCreator.report.READ` | Fetch and download report data |
| `ZohoCreator.report.UPDATE` | Update report records |
| `ZohoCreator.report.DELETE` | Delete report records |
| `ZohoCreator.meta.form.READ` | Get form field information |
| `ZohoCreator.dashboard.READ` | List applications |

## Client Management Steps

1. Register client application
2. Request authorization code
3. Generate access and refresh tokens
4. Refresh access token as needed
5. Revoke tokens when no longer required

## Security Recommendations

- Limit scope to minimum required permissions
- Securely store client credentials
- Regularly rotate tokens
- Implement token revocation when access is no longer needed