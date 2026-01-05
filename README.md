# iMobility SippySoft PHP Wrapper

A PHP wrapper for the SippySoft XML-RPC API, providing an easy-to-use interface for managing accounts, customers, and other SippySoft resources.

## Features

- **HTTP Digest Authentication**: Secure authentication using HTTP Digest Auth
- **Exception Handling**: Comprehensive error handling with custom exception classes
- **Account Management**: Create, update, block/unblock, and delete accounts
- **Customer Management**: Create, update, block/unblock, and delete customers
- **Financial Operations**: Add funds, credit, and debit operations for accounts and customers
- **CDR Retrieval**: Get call detail records for accounts and customers
- **Extensible**: Easy to extend with additional API methods

## Requirements

- PHP 7.0 or higher
- cURL extension
- SimpleXML extension

### Installing Required PHP Extensions

**Ubuntu/Debian:**
```bash
sudo apt-get install php-curl php-xml
```

**CentOS/RHEL:**
```bash
sudo yum install php-curl php-xml
```

**macOS (using Homebrew):**
```bash
brew install php
# cURL and SimpleXML are typically included
```

**Verify Installation:**
```bash
php -m | grep -E "curl|SimpleXML"
```

## Installation

Simply include the `imobility-sippy.php` file in your project:

```php
require_once 'imobility-sippy.php';
```

## Quick Start

```php
<?php
require_once 'imobility-sippy.php';

try {
    // Initialize the client with URL containing embedded credentials
    $url = 'https://username:password@your-sippysoft-server.com/xmlapi/xmlapi';
    $client = new SippySoftClient($url, true); // true = verify SSL (recommended)

    // List accounts
    $accounts = $client->listAccounts();
    print_r($accounts);

    // Get account information
    $accountInfo = $client->getAccountInfo(123);
    print_r($accountInfo);

    // Create a new account
    $params = [
        'username' => 'testuser',
        'web_password' => 'webpass123',
        'authname' => 'voipuser',
        'voip_password' => 'voippass123',
        'i_customer' => 123, // Optional: for trusted mode
        // ... add other required and optional parameters
    ];
    $result = $client->createAccount($params);
    print_r($result);

} catch (SippySoftError $e) {
    echo "SippySoft Error: " . $e->getMessage();
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage();
}
```

## API Methods

### Account Management

- `listAccounts($i_customer = null, $offset = null, $limit = null)` - Get list of accounts
- `getAccountInfo($account_id)` - Get detailed account information
- `createAccount($params)` - Create new account
- `updateAccount($i_account, $i_customer = null, $update_params = [])` - Update existing account
- `blockAccount($i_account, $i_customer = null)` - Block an account
- `unblockAccount($i_account, $i_customer = null)` - Unblock an account
- `deleteAccount($i_account, $i_customer = null)` - Delete an account

### Financial Operations (Accounts)

- `accountAddFunds($i_account, $amount, $currency, $payment_notes = null, $payment_time = null)` - Add funds to account
- `accountCredit($i_account, $amount, $currency, $payment_notes = null, $payment_time = null)` - Credit account balance
- `accountDebit($i_account, $amount, $currency, $payment_notes = null, $payment_time = null)` - Debit account balance

### Customer Management

- `listCustomers($i_wholesaler = null, $offset = null, $limit = null)` - Get list of customers
- `getCustomerInfo($customer_identifier, $i_wholesaler = null)` - Get customer information
- `createCustomer($name, $web_password, $i_tariff, $additional_params = [])` - Create new customer
- `updateCustomer($i_customer, $i_wholesaler = null, $update_params = [])` - Update customer
- `blockCustomer($i_customer, $i_wholesaler = null)` - Block a customer
- `unblockCustomer($i_customer, $i_wholesaler = null)` - Unblock a customer
- `deleteCustomer($i_customer, $i_wholesaler = null)` - Delete a customer

### Financial Operations (Customers)

- `customerAddFunds($i_customer, $amount, $currency, $i_wholesaler = null, $payment_notes = null, $payment_time = null)` - Add funds to customer
- `customerCredit($i_customer, $amount, $currency, $i_wholesaler = null, $payment_notes = null, $payment_time = null)` - Credit customer balance
- `customerDebit($i_customer, $amount, $currency, $i_wholesaler = null, $payment_notes = null, $payment_time = null)` - Debit customer balance

### CDR Operations

- `getAccountCDRs($i_account = null, $i_customer = null, $additional_params = [])` - Get account CDRs
- `getCustomerCDRs($i_customer = null, $i_wholesaler = null, $additional_params = [])` - Get customer CDRs

### Routing Groups

- `listRoutingGroups($name_pattern = null, $i_routing_group = null)` - Get list of routing groups

### DID Management

- `getDIDsList($did = null, $incoming_did = null, $delegated_to = null, $i_account = null, $i_ivr_application = null, $not_assigned = null, $offset = null, $limit = null)` - Get list of DIDs with filtering
- `getDIDInfo($i_did = null, $did = null, $did_range_end = null)` - Get detailed DID information
- `updateDID($i_did = null, $did = null, $did_range_end = null, ...)` - Update DID properties
- `deleteDID($i_did = null, $did = null, $did_range_end = null)` - Delete a DID
- `addDIDDelegation($i_did, $delegated_to, $parent_i_did_delegation = null, $i_dids_charging_group = null, $description = null)` - Delegate DID to subcustomer
- `updateDIDDelegation($i_did_delegation, $i_dids_charging_group = null, $delegated_to = null, $description = null)` - Update DID delegation
- `deleteDIDDelegation($i_did_delegation)` - Delete DID delegation

### Authentication Rules

- `addAuthRule($i_account, $i_protocol, $remote_ip = null, $incoming_cli = null, ...)` - Add authentication rule to account
- `updateAuthRule($i_authentication, $i_account = null, $i_protocol = null, ...)` - Update authentication rule
- `delAuthRule($i_authentication)` - Delete authentication rule
- `getAuthRuleInfo($i_authentication)` - Get authentication rule information
- `listAuthRules($i_account, $i_authentication = null, $i_protocol = null, $remote_ip = null, $offset = null, $limit = null)` - List authentication rules for account

### Utility Methods

- `getDictionary($name, $additional_params = [])` - Get dictionary of available values (languages, currencies, timezones, etc.)

## Error Handling

The wrapper provides custom exception classes for different types of errors:

- `SippySoftError` - Base exception for all SippySoft-related errors
- `SippySoftConnectionError` - Network or connection-related errors
- `SippySoftAPIError` - API-specific errors (invalid responses, etc.)

```php
try {
    $result = $client->createAccount('testuser');
} catch (SippySoftConnectionError $e) {
    // Handle connection issues
    echo "Connection failed: " . $e->getMessage();
} catch (SippySoftAPIError $e) {
    // Handle API errors
    echo "API Error: " . $e->getMessage();
} catch (SippySoftError $e) {
    // Handle other SippySoft errors
    echo "SippySoft Error: " . $e->getMessage();
}
```

## Trusted Mode

Many methods support "trusted mode" where you can perform operations on behalf of other customers. This is done by passing the appropriate customer/wholesaler ID:

```php
// List accounts for a specific customer (trusted mode)
$accounts = $client->listAccounts(123); // i_customer = 123

// Create account for a specific customer
$result = $client->createAccount('newuser', 123); // i_customer = 123
```

## Configuration

### SSL Verification

By default, SSL certificates are verified. You can disable this (not recommended for production):

```php
$url = 'https://username:password@your-server.com/xmlapi/xmlapi';
$client = new SippySoftClient($url, false); // false = disable SSL verification (not recommended)
```

### Creating Accounts

The `createAccount` method requires all parameters to be passed as an associative array. Refer to the SippySoft API documentation for required and optional parameters:

```php
$params = [
    // Required parameters
    'username' => 'testuser',
    'web_password' => 'webpass123',
    'authname' => 'voipuser',
    'voip_password' => 'voippass123',
    'max_sessions' => 5,
    'max_credit_time' => 3600,
    'translation_rule' => '',
    'cli_translation_rule' => '',
    'credit_limit' => 100.0,
    'i_billing_plan' => 1,
    'i_time_zone' => 1,
    'balance' => 0.0,
    'cpe_number' => '',
    'vm_enabled' => 0,
    'vm_password' => '',
    'blocked' => 0,
    'i_lang' => 'en',
    'payment_currency' => 'USD',
    'payment_method' => 1,
    'i_export_type' => 1,
    'lifetime' => -1,
    'preferred_codec' => null,
    'use_preferred_codec_only' => false,
    'reg_allowed' => 1,
    'welcome_call_ivr' => 0,
    'on_payment_action' => null,
    'min_payment_amount' => 0.0,
    'trust_cli' => false,
    'disallow_loops' => true,
    // ... (see PHPDoc for complete list)
    
    // Optional parameters
    'i_customer' => 123, // For trusted mode
    'description' => 'Test account',
];

$result = $client->createAccount($params);
```

## Extending the Wrapper

To add new API methods, simply add them to the `SippySoftClient` class following the existing pattern:

```php
public function newMethodName($param1, $param2 = null) {
    $params = ['param1' => $param1];
    if ($param2 !== null) $params['param2'] = $param2;

    error_log("Calling newMethodName with parameters: " . json_encode($params));

    $result = $this->call('newMethodName', [$params]);

    error_log("Successfully called newMethodName");

    return $result;
}
```

## License

This wrapper is provided as-is for use with SippySoft systems. Please ensure you comply with SippySoft's terms of service and API usage policies.

## Support

For issues specific to the SippySoft API, please refer to the official SippySoft documentation. For issues with this PHP wrapper, please check the implementation and error logs.
