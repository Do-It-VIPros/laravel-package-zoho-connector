<html>
    <body>
        <h1>ZohoConnector - Test page</h1>
        @if (ZohoCreatorApi::isReady())
            All is ready. You can now use ZohoCreatorApi.
            Example :
                ZohoCreatorApi::get(parameters);
        @else
            The ZohoConnectorService is not ready.
            Please check the required environnement variables :
             : 
            <table>
                <thead>
                    <tr>
                        <th>Environnement variable</th>
                        <th>Actual value</th>
                        <th>Default value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th>APP_ENV</th>
                        <td>{{ env("APP_URL") }}</td>
                        <td>No Default value</td>
                    </tr>
                    <tr>
                        <th>ZOHO_ACCOUNT_DOMAIN</th>
                        <td>{{ env('ZOHO_ACCOUNT_DOMAIN') }}</td>
                        <td>eu, com, jp, in, com.au ...</td>
                    </tr>
                    <tr>
                        <th>ZOHO_CLIENT_ID</th>
                        <td>{{ env('ZOHO_CLIENT_ID') }}</td>
                        <td>1000.8cb99dxxxxxxxxxxxxx9be93</td>
                    </tr>
                    <tr>
                        <th>ZOHO_CLIENT_SECRET</th>
                        <td>{{ env('ZOHO_CLIENT_SECRET') }}</td>
                        <td>9b8xxxxxxxxxxxxxxxf</td>
                    </tr>
                    <tr>
                        <th>ZOHO_SCOPE</th>
                        <td>{{ env('ZOHO_SCOPE') }}</td>
                        <td> ZohoCreator.report.AL</td>
                    </tr>
                    <tr>
                        <th>ZOHO_USER</th>
                        <td>{{ env('ZOHO_USER') }}</td>
                        <td>jason18</td>
                    </tr>
                    <tr>
                        <th>ZOHO_APP_NAME</th>
                        <td>{{ env('ZOHO_APP_NAME') }}</td>
                        <td>zylker-store</td>
                    </tr>
                </tbody>
            </table>

            <b> Be sure than your ZOHO_CLIENT_ID has been well parametrized on <a href="https://api-console.zoho.{{ env('ZOHO_ACCOUNT_DOMAIN') }}">the zoho API console</a> with <u>{{ env("APP_URL") }}/zoho/request-code-response</u> as authorized redirect URI. </b>
        
            Once all is set, go on <a href='{{ env("APP_URL") }}/zoho/request-code-response'>Request code page</a> to start the Service.
        @endif
    </body>
</html>