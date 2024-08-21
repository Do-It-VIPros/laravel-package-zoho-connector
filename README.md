
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

### Requirements

To use the service and interact with the Zoho API you need to get a client_id and a client_secret on the [Zoho API console](https://api-console.zoho.com).
- Click on "Add Client" on top right
- Use "Server-based Applications"
- Fill the informations :
    - Client Name : Your App Name
    - Homepage URL : You App URL
    - Authorized Redirect URIs : <APP_URL>/zoho/request-code-response <= IMPORTANT
- You'll get the required client_id and client_secret

- PHP has to have the [zip extension](https://www.php.net/manual/en/zip.installation.php) installed. For Linux system, just type with the right php version :
``` bash
  sudo apt-get install php8.2-zip
```

### Basics

#### Creator

  To interact with Zoho Creator, all is based on report. 

  The Zoho API documentation is available [here](https://www.zoho.com/creator/help/api/v2.1/).

  The version of the Zoho API is the v2.1 .

#### CRM

Comming soon.

## Configuration
### Environnements variables

|Identifier|Required|Description|Availables values|Commentary|
| :--------------- |:--:|:--------------- |:---------------:| ----------------:|
|ZOHO_ACCOUNT_DOMAIN|No|Zoho domain name where your Zoho account is registred | eu, com, jp, in, com.au ...| Default value : eu |
|ZOHO_CLIENT_ID|Yes| The client ID from https://api-console.zoho.<ZOHO_ACCOUNT_DOMAIN> | - | Default value : 1000.8cb99dxxxxxxxxxxxxx9be93|
|ZOHO_CLIENT_SECRET|Yes| Your client secret from https://api-console.zoho.<ZOHO_ACCOUNT_DOMAIN> | - | Default value : 9b8xxxxxxxxxxxxxxxf|
|ZOHO_SCOPE|No| The scope for your client | ZohoCreator.report.ALL, ZohoCreator.report.READ,ZohoCreator.bulk.CREATE ... see [API doc](https://www.zoho.com/creator/help/api/v2.1/oauth-overview.html#scopes)  | Default value : ZohoCreator.report.READ |
|ZOHO_USER|Yes| Your Zoho user name | - | Default value : jason18|
|ZOHO_APP_NAME|Yes| Your Zoho App identifier | - | Default value : zylker-store|
|ZOHO_BULK_DOWNLOAD_PATH|No| Path where the ZIP from bulk are loaded | - | Default value : storage_path("zohoconnector") |
|ZOHO_TOKENS_TABLE|No| tokens table name | - | Default value : zoho_connector_tokens |
|ZOHO_BULKS_TABLE|No| bulk process table name | - | Default value : zoho_connector_bulk_history |

### Initalize

- Be sure to have the APP_URL env var set.
- Get a client_id and a client_secret from ZOHO => [Requirements](#Requirements).
- Fill the Environnements variables as described in [Environnements variables](#Environnements-variables).
- On DEV environnement, you can go on <APP_URL>/zoho/test to check if all your environnement is ready. Then click on "Request code page" link to activate the 
on DEV environnement).
- If the service is ready. You can now [use the service](#usage).

You can reset the service with the /zoho/reset-tokens route or by clicking the reset button on the /zoho/test page.

### On Production environnement

The /zoho/test page is not available.

Go on /zoho/request-code to activate the service (Once the parameters are set obviously).

The /zoho/reset-tokens is not available too. To reset tokens, truncate the ZOHO_TOKENS_TABLE.

# Usage

## Basic exemple

``` php
use ZohoCreatorApi;

public static function test() {
  return ZohoCreatorApi::get("my_report");
}

```

## Availables functions

### Get from Report

``` php
ZohoCreatorApi::get(<report_name>,<criterias>="",&<cursor>="");
```
Return the found records as an array.

This function has a 1000 records limit. If there is more the cursor variable will be filled.

Check at ZohoCreatorApi::getAll() code function to see how handle the cursor and the loop.

[See criterias by Zoho](https://www.zoho.com/creator/help/api/v2.1/get-records.html#search_criteria).

### Get All from Report

``` php
ZohoCreatorApi::getAll(<report_name>,<criterias>="");
```
Return the found records as an array.

This function may be very long. Look at the function to see who use it.

[See criterias by Zoho](https://www.zoho.com/creator/help/api/v2.1/get-records.html#search_criteria).

### Get by ID

``` php
ZohoCreatorApi::getByID(<report_name>,<object_id>);
```
Return the found record as an array.

field_config is set to "all".

### Bulk operations
#### Automated gestion
The whole bulk process is now entierly supervised with Laravel Jobs. Just use the function : 
``` php
  ZohoCreatorApi::getWithBulk(<report_name>, <call_back_url>, <criterias>="")
```
This launch a laravel JOB and send to the callback_url by the parameter json_location, the location of the downloaded,extracted,transformed report datas in JSON.
This function require the [zip extension](https://www.php.net/manual/en/zip.installation.php) of PHP and you need to run the jobs of Laravel with : 
``` bash
php artisan queue:work
```
#### Create bulk

``` php
  ZohoCreatorApi::createBulk(<report_name>,<criterias>="");
```
Create the bulk request.

[See criterias by Zoho](https://www.zoho.com/creator/help/api/v2.1/get-records.html#search_criteria).

#### Read Bulk infos

``` php
  ZohoCreatorApi::readBulk(<report_name>,<bulk_id>="");
```
Return the bulk request infos as an array.

#### Download bulk

``` php
  ZohoCreatorApi::downloadBulk(<report_name>,<bulk_id>="");
```
Download the bulk request result in the ZOHO_BULK_DOWNLOAD_PATH.

## Todo
 - Continue GET Bulk function (see for a callback)
 - Create a table for created bulks and status
 - Create Tests function
 - Complete commentarys 
