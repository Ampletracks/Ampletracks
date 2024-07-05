# Ampletracks SSL Certificate Process Documentation

## Overview
Ampletracks provides an integrated method to obtain and renew SSL certificates using the Let's Encrypt API. This document outlines the steps required for installation, configuration, and maintenance, as well as debugging instructions to assist administrators.

## Prerequisites

1. **Configuration Parameters**:
    - Ensure the following configuration parameter is set in your main configuration script:
      ```php
      define('ACME_ACCOUNT_EMAIL', '<sysadmin_email_address>');
      ```
    
2. **Apache modRedirect Module**:
    - The modRedirect module must be installed and enabled in Apache.

3. **Apache Redirect Configuration**:
    - Add the following redirect rule to your Apache configuration:
      ```apache
      RedirectMatch "^/.well-known/acme-challenge/(.*)" "/acme/checkOrders.php?mode=verify&filename=$1"
      ```

4. **Cron Job**:
    - Set up a cron job to call the Ampletracks cron script every minute. This script performs various maintenance tasks, including SSL certificate renewal. The cron script can be found at `/scripts/cron.sh` in the Ampletracks codebase. This script includes the following command which calls the certificate renewal script:
      ```sh
      wget --no-check-certificate -q -O - "http://<your_ampletracks_domain>/acme/checkOrders.php" &
      ```

If you used the Ansible script provided in the Ampletracks repository for installation, these prerequisites will be automatically configured.

## Installation

If you used the Ansible script provided in the Ampletracks repository for installation, the following will automatically have been done by that Ansible script.

To install the SSL certificate process for your Ampletracks installation without using the Ansible scripts provided, you must complete the following steps:
1. Ensure all prerequisites are met.
2. Configure the `ACME_ACCOUNT_EMAIL` in your main configuration script.
3. Add the necessary Apache redirect rule.
4. Set up the cron job for regular script execution.


## SSL Certificate Renewal Script
The renewal process is managed by the PHP script found in the Ampletracks codebase at `www/acme/checkOrders.php`. An overview of the operation of this script is provided below:

1. **Initialization and Configuration Check**:
    - The script checks if ACME functionality is enabled and properly configured using `ACME_ACCOUNT_EMAIL`.
   
2. **State Management**:
    - The state of the renewal process is managed using `systemData('acmeCheckState')` function call to store the state data in the database configured into your installation.
    - A random delay mechanism ensures that the script runs at least once every 24 hours.

3. **Certificate Renewal**:
    - The script checks if the SSL certificate needs renewal using `certNeedsRenewing`.
    - If renewal is needed, it initiates the certificate renewal process using the Let's Encrypt API.
    - N.B. If called from a browser (in fact, if called from anything other than wget) the code will override the normal back-off logic and attempt renewal every time.

4. **Order Verification**:
    - Handles verification requests from Let's Encrypt servers via the `verify` mode.

5. **Logging and Error Handling**:
    - Logs errors and status updates using the Ampletracks `$LOGGER` object.

## Maintenance

### Manual Execution
Administrators can manually trigger the renewal script by visiting:
http://<your_ampletracks_domain>/acme/checkOrders.php

This can be done multiple times without any issues.

### Automatic Renewal
The cron job set up during installation ensures that the script runs regularly, checking for certificate renewal needs and renewing if necessary.

## Debugging

If you encounter issues with the SSL certificate process, follow these steps:

1. **Manual Script Execution**:
    - Visit the renewal script URL in a browser to manually trigger the process.

2. **DNS Configuration**:
    - Ensure that external DNS is correctly configured so that Let's Encrypt servers can reach your Ampletracks installation.

3. **Apache Access Logs**:
    - Check the Apache access logs to verify if the verification callbacks from Let's Encrypt are arriving at the server.

4. **Directory Permissions**:
    - Ensure that the directory `<Ampletracks_installation_base_directory>/data/acme` is writable by the web server user.

5. **Ampletracks Error Logs**:
    - Review the Ampletracks error log located at `<Ampletracks_installation_base_directory>/core_<YYYYMMDD>.log`.

6. **Apache Error Logs**:
    - Check the Apache error logs for any errors related to the Ampletracks installation.

7. **Server Restart**:
    - If a new certificate is obtained successfully, restart the web server. The cron script should handle this automatically, but manual restart may be necessary in some cases.
