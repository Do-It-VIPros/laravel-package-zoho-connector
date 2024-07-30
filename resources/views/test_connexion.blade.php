<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        h1 {
            background-color: #4CAF50;
            color: white;
            padding: 20px;
            text-align: center;
            margin: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .content {
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #fafafa;
        }
        a {
            color: #4CAF50;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 15px;
            background-color: #f44336;
            color: white;
            margin-top: 20px;
            border-radius: 5px;
        }
        .success {
            padding: 15px;
            background-color: #4CAF50;
            color: white;
            margin-top: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>ZohoConnector - Test page</h1>
    <div class="content">
        @if (ZohoCreatorApi::isReady())
            <div class="success">
                All is ready. You can now use ZohoCreatorApi.<br>
                Example :<br>
                <b>$datas = ZohoCreatorApi::get(report_name, criterias);</b>
            </div>
            <div class="alert">
                Click <a href='{{ env("APP_URL") }}/zoho/reset-tokens'>here</a> to reset tokens.
            </div>
        @else
            <div class="alert">
                The ZohoConnectorService is not ready.<br>
                Please check the required environment variables:
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Environment variable</th>
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
                        <td> ZohoCreator.report.READ</td>
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
            <div class="alert">
                Be sure that your ZOHO_CLIENT_ID has been well parametrized on 
                <a href="https://api-console.zoho.{{ env('ZOHO_ACCOUNT_DOMAIN') }}">the Zoho API console</a> 
                with <u>{{ env("APP_URL") }}/zoho/request-code-response</u> as authorized redirect URI.
            </div>
            @if (!Schema::hasTable(config('zohoconnector.tokens_table_name')))
            <div class="alert">
                /!\ Required {{ config('zohoconnector.tokens_table_name') }} table is not present. Please run migration.
            </div>
            @endif
            <div class="alert">
                Once all is set, go to <a href='{{ env("APP_URL") }}/zoho/request-code'>Request code page</a> to start the service.
            </div>
        @endif
    </div>
</body>
</html>
