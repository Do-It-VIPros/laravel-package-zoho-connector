# Register Your Client Application with Zoho Creator

## Prerequisites
- Access to [Zoho Developer Console](https://api-console.zoho.com/)

## Registration Steps

1. **Access Developer Console**
   - Navigate to Zoho Developer Console
   - Click **GET STARTED** for first-time registration

2. **Select Client Type**
   - Choose appropriate client type
   - Refer to [OAuth overview](/accounts/protocol/oauth.html) for details

3. **Enter Application Details**
   - Provide **Client Name**
   - Enter **Homepage URL**

4. **Configure Redirect URIs**
   - For client/server/mobile applications:
     - Add at least one **Authorized Redirect URI**
     - URI format: `https://www.your-domain.com/callback`
     - Multiple URIs allowed (comma-separated)
     - Dummy values acceptable if no actual URL exists

5. **JavaScript Domain Configuration**
   - For client-based applications:
     - Enter at least one **JavaScript Domain**
     - Multiple domains permitted (comma-separated)
     - Dummy values acceptable

6. **Complete Registration**
   - Click **CREATE**
   - Client credentials will be displayed

## Important Notes
- Carefully review and save client credentials
- Ensure redirect URIs and domains are correctly configured