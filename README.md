
# Laravel package - Zoho Connector

## Table of contents
- [Laravel package - Zoho Connector](#Laravel-package---Zoho-Connector)
  - [Table of contents](#Table-of-contents)
  - [General](#general)
  - [Configuration](#Configuration)
    - [Environnements variables](#Environnements-variables)
    - [Initalize](#Initalize)

## General

Laravel Package to manage a connexion to Zoho Creator API

## Configuration
### Environnements variables

|Identifier|Description|Availables values|Commentary|
| :--------------- |:--------------- |:---------------:| ----------------:|
|ZOHO_ACCOUNT_DOMAIN|Zoho domain name where your Zoho account is registred | eu, com, jp, in, com.au ...| Default value : eu |
|ZOHO_CLIENT_ID| The client ID from https://api-console.zoho.<ZOHO_ACCOUNT_DOMAIN> | - | Default value : 1000.8cb99dxxxxxxxxxxxxx9be93|
|ZOHO_CLIENT_SECRET| Your client secret from https://api-console.zoho.<ZOHO_ACCOUNT_DOMAIN> | - | Default value : 9b8xxxxxxxxxxxxxxxf|
|ZOHO_SCOPE| The scope for your client | ZohoCreator.report.ALL, ZohoCreator.report.READ ... see [API doc](https://www.zoho.com/creator/help/api/v2.1/oauth-overview.html#scopes)  | Default value : ZohoCreator.report.ALL |
|ZOHO_USER| Your Zoho user name | - | Default value : jason18|
|ZOHO_APP_NAME| Your Zoho App identifier | - | Default value : zylker-store|

### Initalize

First things first, you have to generate your Zoho API access on  [Zoho API console](https://api-console.zoho.com). (check for the right domain)
The "Authorized Redirect URIs" have to be formed like that : APP_URL . /zoho/request-code-response (ex : http://localhost:8000/zoho/request-code-response)
Once you have your access (client ID/secret...), please fill the env variables in your .env file as described in [Environnements variables](#Environnements-variables).
Don't forget to fill the APP_URL env parameter.
Check if the required informations are all set on the <APP_URL>/zoho/test (Only available on DEV environnement).
When all is set, access to /zoho/request-code to generate your first access token. Since this is not done, you will not be able to use the zoho Service.

### Usage

```php
use ZohoCreatorApi;

public static function test() {
  return ZohoCreatorApi::get(/*TODO : complete required params*/);
}

```

## Todo
 - Create the GET function
 - Create the GET Bulk function
