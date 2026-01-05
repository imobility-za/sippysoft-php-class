<?php

/**
 * iMobility SippySoft XML-RPC API Wrapper.
 *
 * This class provides a wrapper for interacting with the SippySoft XML-RPC API.
 * It includes custom transport for HTTP Digest Authentication and a client
 * class for making API calls.
 */

// Custom exception classes
class SippySoftError extends Exception {}
class SippySoftConnectionError extends SippySoftError {}
class SippySoftAPIError extends SippySoftError {}

/**
 * Custom transport for XML-RPC that uses HTTP Digest Authentication.
 *
 * This transport class handles the authentication and request/response
 * processing for the SippySoft XML-RPC API.
 */
class HTTPSDigestAuthTransport {
    private $username;
    private $password;
    private $host;
    private $verify_ssl;

    /**
     * Initialize the transport with a full URL containing credentials.
     *
     * @param string $url Full URL with embedded credentials (e.g., "https://user:pass@host/xmlapi/xmlapi")
     * @param bool $verify_ssl Whether to verify SSL certificates
     */
    public function __construct($url, $verify_ssl = true) {
        $this->verify_ssl = $verify_ssl;

        $parsed = parse_url($url);
        if ($parsed === false) {
            throw new SippySoftConnectionError("Invalid URL format: {$url}");
        }

        $this->username = $parsed['user'] ?? '';
        $this->password = $parsed['pass'] ?? '';
        $this->host = $parsed['host'] ?? '';

        if (empty($this->host)) {
            throw new SippySoftConnectionError("No host found in URL: {$url}");
        }
    }

    /**
     * Make an XML-RPC request to the server.
     *
     * @param string $host The host to connect to (hostname only, no credentials)
     * @param string $handler The URL handler
     * @param string $request_body The XML-RPC request body
     * @param int $verbose Verbosity level
     * @return mixed The parsed response
     * @throws SippySoftAPIError If the server returns an error
     * @throws SippySoftConnectionError If connection fails
     */
    public function makeRequest($host, $handler, $request_body, $verbose = 0) {
        $parsed = parse_url("https://" . $host);
        $hostname = $parsed['host'] ?? $host;
        $port = $parsed['port'] ?? 443;

        $url = "https://{$hostname}:{$port}{$handler}";

        if ($verbose) {
            error_log("Making request to: {$url}");
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml',
            'User-Agent: PHP-XMLRPC-Client/1.0'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verify_ssl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verify_ssl ? 2 : 0);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new SippySoftConnectionError("Failed to connect to SippySoft API: {$error}");
        }

        if ($http_code != 200) {
            throw new SippySoftAPIError("API error: {$http_code} - Response: " . substr($response, 0, 200));
        }

        // Parse XML-RPC response
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            throw new SippySoftAPIError("Failed to parse XML-RPC response");
        }

        // Convert XML to PHP data structure
        return $this->xmlToPhp($xml);
    }

    /**
     * Convert XML-RPC response to PHP data structure.
     *
     * @param SimpleXMLElement $xml The XML response
     * @return mixed The PHP data structure
     * @throws SippySoftAPIError If the response contains a fault
     */
    private function xmlToPhp($xml) {
        // Check for fault response first
        $fault = $xml->xpath('//methodResponse/fault/value');
        if (!empty($fault)) {
            $faultData = $this->parseValue($fault[0]);
            $faultCode = $faultData['faultCode'] ?? 'Unknown';
            $faultString = $faultData['faultString'] ?? 'Unknown error';
            throw new SippySoftAPIError("API Fault {$faultCode}: {$faultString}");
        }

        // Check for success response
        $methodResponse = $xml->xpath('//methodResponse/params/param/value');
        if (empty($methodResponse)) {
            throw new SippySoftAPIError("Invalid XML-RPC response: no params or fault found");
        }

        return $this->parseValue($methodResponse[0]);
    }

    /**
     * Parse XML-RPC value element.
     *
     * @param SimpleXMLElement $value The value element
     * @return mixed The parsed value
     */
    private function parseValue($value) {
        // Handle null values (XML-RPC nil extension)
        if (isset($value->nil)) {
            return null;
        }
        
        // Handle different value types
        if (isset($value->struct)) {
            $result = [];
            foreach ($value->struct->member as $member) {
                $name = (string)$member->name;
                $result[$name] = $this->parseValue($member->value);
            }
            return $result;
        } elseif (isset($value->array)) {
            $result = [];
            if (isset($value->array->data->value)) {
                foreach ($value->array->data->value as $item) {
                    $result[] = $this->parseValue($item);
                }
            }
            return $result;
        } elseif (isset($value->string)) {
            return (string)$value->string;
        } elseif (isset($value->int) || isset($value->i4)) {
            return (int)$value->int;
        } elseif (isset($value->double)) {
            return (float)$value->double;
        } elseif (isset($value->boolean)) {
            return (bool)((string)$value->boolean === '1');
        } else {
            return (string)$value;
        }
    }
}

/**
 * Client for interacting with the SippySoft XML-RPC API.
 *
 * This class provides methods for making API calls to the SippySoft XML-RPC API.
 * It handles authentication, request/response processing, and error handling.
 */
class SippySoftClient {
    private $host;
    private $verify_ssl;
    private $transport;

    /**
     * Initialize the client with a full URL containing embedded credentials.
     *
     * @param string $url Full URL with embedded credentials (e.g., "https://user:pass@host/xmlapi/xmlapi")
     * @param bool $verify_ssl Whether to verify SSL certificates
     */
    public function __construct($url, $verify_ssl = true) {
        $this->verify_ssl = $verify_ssl;

        $this->transport = new HTTPSDigestAuthTransport($url, $verify_ssl);

        // Extract host for logging
        $parsed = parse_url($url);
        $this->host = $parsed['host'] ?? $url;

        error_log("Initialized SippySoft client for host: {$this->host}");
    }

    /**
     * Make an XML-RPC call.
     *
     * @param string $method The method name
     * @param array $params The method parameters
     * @return mixed The response
     * @throws SippySoftAPIError If the API returns an error
     * @throws SippySoftConnectionError If connection fails
     */
    private function call($method, $params = []) {
        $request_xml = $this->buildXmlRequest($method, $params[0] ?? []);

        try {
            $response = $this->transport->makeRequest(
                $this->host,
                '/xmlapi/xmlapi',
                $request_xml
            );

            return $response;

        } catch (SippySoftAPIError $e) {
            throw $e;
        } catch (Exception $e) {
            throw new SippySoftError("Error in {$method}: " . $e->getMessage());
        }
    }

    /**
     * Build XML-RPC request using SimpleXMLElement.
     *
     * @param string $method The method name
     * @param array $params The method parameters (single param dict)
     * @return string The XML request
     */
    private function buildXmlRequest($method, $params = []) {
        $xml = new SimpleXMLElement('<?xml version="1.0"?><methodCall></methodCall>');
        $xml->addChild('methodName', $method);
        $paramsNode = $xml->addChild('params');
        $paramNode = $paramsNode->addChild('param');
        $valueNode = $paramNode->addChild('value');
        $this->addXmlValue($valueNode, $params);
        return $xml->asXML();
    }

    /**
     * Add a PHP value to an XML node recursively.
     *
     * @param SimpleXMLElement $xml The XML node to add to
     * @param mixed $value The PHP value to add
     */
    private function addXmlValue($xml, $value) {
        // Handle null values with <nil/> element (XML-RPC extension)
        if ($value === null) {
            $xml->addChild('nil');
            return;
        }
        
        if (is_array($value)) {
            if ($this->isAssociativeArray($value)) {
                // Struct (associative array)
                $structNode = $xml->addChild('struct');
                foreach ($value as $key => $item) {
                    $memberNode = $structNode->addChild('member');
                    $memberNode->addChild('name', htmlspecialchars($key));
                    $this->addXmlValue($memberNode->addChild('value'), $item);
                }
            } else {
                // Array (indexed array)
                $arrayNode = $xml->addChild('array');
                $dataNode = $arrayNode->addChild('data');
                foreach ($value as $item) {
                    $this->addXmlValue($dataNode->addChild('value'), $item);
                }
            }
        } elseif (is_int($value)) {
            $xml->addChild('int', $value);
        } elseif (is_float($value)) {
            $xml->addChild('double', $value);
        } elseif (is_bool($value)) {
            $xml->addChild('boolean', $value ? '1' : '0');
        } elseif (is_string($value)) {
            $xml->addChild('string', htmlspecialchars($value));
        } else {
            // Default to string for other types
            $xml->addChild('string', htmlspecialchars((string)$value));
        }
    }

    /**
     * Check if array is associative.
     *
     * @param array $array The array to check
     * @return bool True if associative
     */
    private function isAssociativeArray($array) {
        // Empty arrays should be treated as structs (associative) for XML-RPC
        if (empty($array)) {
            return true;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    // Methods for managing accounts on the Switches

    /**
     * Get a list of accounts belonging to a customer.
     *
     * This method supports trusted mode where i_customer parameter should be supplied.
     *
     * @param int|null $i_customer The customer ID for trusted mode
     * @param int|null $offset Skip first offset records
     * @param int|null $limit Return only limit records
     * @return array A dictionary containing result and accounts
     */
    public function listAccounts($i_customer = null, $offset = null, $limit = null) {
        $params = [];
        if ($i_customer !== null) $params['i_customer'] = $i_customer;
        if ($offset !== null) $params['offset'] = $offset;
        if ($limit !== null) $params['limit'] = $limit;

        error_log("Listing accounts with parameters: " . json_encode($params));

        $result = $this->call('listAccounts', [$params]);

        error_log("Successfully retrieved accounts");

        return $result;
    }

    /**
     * Get account information from the SippySoft API.
     *
     * @param int $account_id The account ID to query
     * @return array Account information
     */
    public function getAccountInfo($account_id) {
        error_log("Getting account info for account ID: {$account_id}");
        $result = $this->call('getAccountInfo', [['i_account' => $account_id]]);

        // The API returns a struct with 'account_info' key
        if (is_array($result) && isset($result['account_info'])) {
            return $result['account_info'];
        }
        
        return $result;
    }

    /**
     * Create a new account on the SippySoft server.
     *
     * All required parameters must be provided in the $params array.
     * 
     * Required parameters (per SippySoft API documentation):
     * - username: Username to login to self-care interface (String)
     * - web_password: Password to login to self-care interface (String)
     * - authname: VoIP login (String)
     * - voip_password: VoIP password (String)
     * - max_sessions: Max Sessions (Integer)
     * - max_credit_time: Max Session Time (Integer)
     * - translation_rule: CLD Translation Rule (String)
     * - cli_translation_rule: CLI Translation Rule (String)
     * - credit_limit: Credit Limit (Double)
     * - i_billing_plan: Service plan (Integer) - from version >= 1.8
     * - i_time_zone: Time Zone (Integer) - refer to Timezones List
     * - balance: Balance (Double)
     * - cpe_number: CPE# (String)
     * - vm_enabled: VM Enabled (Integer)
     * - vm_password: PIN Code (String) - should only contain digits 0-9
     * - blocked: Blocked (Integer)
     * - i_lang: Two character language code (String) - e.g., 'en' for English
     * - payment_currency: Payment Currency (String)
     * - payment_method: Payments Preferred Method (Integer)
     * - i_export_type: Download Format (Integer)
     * - lifetime: Lifetime (Integer) - use -1 for unlimited
     * - preferred_codec: Preferred SIP Codec (Integer) - null for disabled, 0-18 for specific codecs
     * - use_preferred_codec_only: Use Preferred Codec Only (Boolean)
     * - reg_allowed: Allow Registration (Integer)
     * - welcome_call_ivr: Welcome Call (Integer)
     * - on_payment_action: On Payment (Integer) - null for no action, 0-2 for specific actions
     * - min_payment_amount: Payments Minimum Amount (Double)
     * - trust_cli: Trust CLI (Boolean)
     * - disallow_loops: Disallow Loops (Boolean)
     * - vm_notify_emails: E-Mail Notification (String)
     * - vm_forward_emails: E-Mail Forwarding (String)
     * - vm_del_after_fwd: Delete after forwarding (Boolean)
     * - company_name: Company Name (String)
     * - salutation: Mr./Ms... (String)
     * - first_name: First Name (String)
     * - last_name: Last Name (String)
     * - mid_init: M.I. (String)
     * - street_addr: Address (String)
     * - state: Province/State (String)
     * - postal_code: Postal Code (String)
     * - city: City (String)
     * - country: Country/Region (String)
     * - contact: Contact (String)
     * - phone: Phone (String)
     * - fax: Fax (String)
     * - alt_phone: Alternative Phone (String)
     * - alt_contact: Alternative Contact (String)
     * - email: E-Mail (String)
     * - cc: CC (String)
     * - bcc: BCC (String)
     * - i_password_policy: Password Policy (Integer)
     * - i_media_relay_type: Use Media Relay (Integer)
     *
     * Optional parameters:
     * - i_customer: Customer ID for trusted mode (Integer)
     * - i_routing_group: Routing group (Integer) - required for root customer accounts
     * - i_account_class: Class (Integer) - from version >= 1.10
     * - vm_timeout: Timeout to redirect to VM (Integer) - from version 2.0
     * - vm_check_number: Access # to VM (String) - from version 2.0
     * - i_commission_agent: i_customer of commission agent (Integer)
     * - commission_size: Commission size in percent (Double)
     * - lan_access: LAN Access (Boolean)
     * - batch_tag: Batch Tag (String)
     * - i_provisioning: Auto-Provisioning type (Integer) - from version 2.0
     * - invoicing_enabled: Is invoicing enabled (Boolean) - from version 2.0
     * - i_invoice_template: Invoice template ID (Integer) - from version 2.0
     * - i_caller_name_type: Caller name type (Integer) - from version 2.0
     * - caller_name: Custom caller name (String) - from version 2.0
     * - followme_enabled: Enable call forwarding (Boolean) - from version 2.1
     * - vm_dialin_access: Enable external access to voicemail (Boolean) - from version 2.1
     * - hide_own_cli: Enable anonymous outgoing calls (Boolean) - from version 2.1
     * - block_incoming_anonymous: Block incoming anonymous calls (Boolean) - from version 2.1
     * - i_incoming_anonymous_action: Action for incoming anonymous calls (Integer) - from version 2.1
     * - dnd_enabled: Enable/Disable DND mode (Boolean) - from version 2.1
     * - description: Description (String)
     * - pass_p_asserted_id: Pass header with Identity (Boolean) - from version 2.2
     * - p_assrt_id_translation_rule: Translation rule for incoming Identity (String) - from version 2.2
     * - dncl_lookup: Do lookup on DNC list (Boolean) - from version 4.3
     * - generate_ringbacktone: Generate ringbacktone (Boolean) - from version 4.4
     * - max_calls_per_second: Max allowed CPS (Double)
     * - allow_free_onnet_calls: Allow onnet calls with 0 price (Boolean) - from version 5.1
     * - start_page: Start page to display upon login (Integer) - from version 5.2
     * - trust_privacy_hdrs: Whether privacy headers should be trusted (Boolean) - from version 2021
     * - privacy_schemas: Allowed privacy schemas (Array) - from version 2021
     *
     * @param array $params All account parameters (required and optional)
     * @return array Creation result containing:
     *               - result: "OK" if successful
     *               - i_account: ID of created account
     *               - username: Username of created account
     *               - web_password: Web password of created account
     *               - authname: VoIP login of created account
     *               - voip_password: VoIP password of created account
     */
    public function createAccount($params = []) {
        error_log("Creating account with parameters: " . json_encode($params));

        $result = $this->call('createAccount', [$params]);
        
        // Validate the response contains account creation data
        if (is_array($result) && isset($result['i_account'])) {
            error_log("Successfully created account with ID: " . $result['i_account']);
        } else {
            error_log("Account created, response: " . json_encode($result));
        }
        
        return $result;
    }

    /**
     * Update an existing account on the SippySoft server.
     *
     * @param int $i_account The account ID to update
     * @param int|null $i_customer The customer ID for trusted mode
     * @param array $update_params Update parameters
     * @return string Result message
     * @throws Exception If no update parameters provided
     */
    public function updateAccount($i_account, $i_customer = null, $update_params = []) {
        if (empty($update_params)) {
            throw new Exception("At least one update parameter must be provided");
        }

        $params = ['i_account' => $i_account];
        if ($i_customer !== null) {
            $params['i_customer'] = $i_customer;
        }
        $params = array_merge($params, $update_params);

        error_log("Updating account with parameters: " . json_encode($params));

        $result = $this->call('updateAccount', [$params]);

        error_log("Successfully updated account: {$i_account}");

        return $result;
    }

    /**
     * Block an account.
     *
     * @param int $i_account The account ID to block
     * @param int|null $i_customer The customer ID for trusted mode
     * @return string Result message
     */
    public function blockAccount($i_account, $i_customer = null) {
        $params = ['i_account' => $i_account];
        if ($i_customer !== null) {
            $params['i_customer'] = $i_customer;
        }

        error_log("Blocking account with parameters: " . json_encode($params));

        $result = $this->call('blockAccount', [$params]);

        error_log("Successfully blocked account: {$i_account}");

        return $result;
    }

    /**
     * Unblock an account.
     *
     * @param int $i_account The account ID to unblock
     * @param int|null $i_customer The customer ID for trusted mode
     * @return string Result message
     */
    public function unblockAccount($i_account, $i_customer = null) {
        $params = ['i_account' => $i_account];
        if ($i_customer !== null) {
            $params['i_customer'] = $i_customer;
        }

        error_log("Unblocking account with parameters: " . json_encode($params));

        $result = $this->call('unblockAccount', [$params]);

        error_log("Successfully unblocked account: {$i_account}");

        return $result;
    }

    /**
     * Add funds to an account's balance.
     *
     * @param int $i_account Account ID
     * @param float $amount Amount to add
     * @param string $currency Currency code
     * @param string|null $payment_notes Payment notes
     * @param string|null $payment_time Payment time
     * @return string Result message
     */
    public function accountAddFunds($i_account, $amount, $currency, $payment_notes = null, $payment_time = null) {
        $params = [
            'i_account' => $i_account,
            'amount' => (float)$amount,
            'currency' => strtoupper($currency)
        ];

        if ($payment_notes !== null) $params['payment_notes'] = $payment_notes;
        if ($payment_time !== null) $params['payment_time'] = $payment_time;

        error_log("Adding funds to account with parameters: " . json_encode($params));

        $result = $this->call('accountAddFunds', [$params]);

        error_log("Successfully added funds to account: {$i_account}");

        return $result;
    }

    /**
     * Credit an account's balance.
     *
     * @param int $i_account Account ID
     * @param float $amount Amount to credit
     * @param string $currency Currency code
     * @param string|null $payment_notes Payment notes
     * @param string|null $payment_time Payment time
     * @return string Result message
     */
    public function accountCredit($i_account, $amount, $currency, $payment_notes = null, $payment_time = null) {
        $params = [
            'i_account' => $i_account,
            'amount' => (float)$amount,
            'currency' => strtoupper($currency)
        ];

        if ($payment_notes !== null) $params['payment_notes'] = $payment_notes;
        if ($payment_time !== null) $params['payment_time'] = $payment_time;

        error_log("Crediting account with parameters: " . json_encode($params));

        $result = $this->call('accountCredit', [$params]);

        error_log("Successfully credited account: {$i_account}");

        return $result;
    }

    /**
     * Debit an account's balance.
     *
     * @param int $i_account Account ID
     * @param float $amount Amount to debit
     * @param string $currency Currency code
     * @param string|null $payment_notes Payment notes
     * @param string|null $payment_time Payment time
     * @return string Result message
     */
    public function accountDebit($i_account, $amount, $currency, $payment_notes = null, $payment_time = null) {
        $params = [
            'i_account' => $i_account,
            'amount' => (float)$amount,
            'currency' => strtoupper($currency)
        ];

        if ($payment_notes !== null) $params['payment_notes'] = $payment_notes;
        if ($payment_time !== null) $params['payment_time'] = $payment_time;

        error_log("Debiting account with parameters: " . json_encode($params));

        $result = $this->call('accountDebit', [$params]);

        error_log("Successfully debited account: {$i_account}");

        return $result;
    }

    /**
     * Get CDRs of an account.
     *
     * @param int|null $i_account Account ID
     * @param int|null $i_customer Customer ID for trusted mode
     * @param array $additional_params Additional parameters
     * @return array CDR results
     */
    public function getAccountCDRs($i_account = null, $i_customer = null, $additional_params = []) {
        $params = [];
        if ($i_account !== null) $params['i_account'] = $i_account;
        if ($i_customer !== null) $params['i_customer'] = $i_customer;
        $params = array_merge($params, $additional_params);

        error_log("Getting account CDRs with parameters: " . json_encode($params));

        $result = $this->call('getAccountCDRs', [$params]);

        error_log("Successfully retrieved CDRs");

        return $result;
    }

    /**
     * Delete an account.
     *
     * @param int $i_account Account ID to delete
     * @param int|null $i_customer Customer ID for trusted mode
     * @return string Result message
     */
    public function deleteAccount($i_account, $i_customer = null) {
        $params = ['i_account' => $i_account];
        if ($i_customer !== null) {
            $params['i_customer'] = $i_customer;
        }

        error_log("Deleting account with parameters: " . json_encode($params));

        $result = $this->call('deleteAccount', [$params]);

        error_log("Successfully deleted account: {$i_account}");

        return $result;
    }

    // Methods for managing customers on the Switches

    /**
     * Get a list of customers belonging to a customer.
     *
     * @param int|null $i_wholesaler The wholesaler ID for trusted mode
     * @param int|null $offset Skip first offset records
     * @param int|null $limit Return only limit records
     * @return array Customer list results
     */
    public function listCustomers($i_wholesaler = null, $offset = null, $limit = null) {
        $params = [];
        if ($i_wholesaler !== null) $params['i_wholesaler'] = $i_wholesaler;
        if ($offset !== null) $params['offset'] = $offset;
        if ($limit !== null) $params['limit'] = $limit;

        error_log("Listing customers with parameters: " . json_encode($params));

        $result = $this->call('listCustomers', [$params]);

        error_log("Successfully retrieved customers");

        return $result;
    }

    /**
     * Get CDRs of a Customer.
     *
     * @param int|null $i_customer Customer ID
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @param array $additional_params Additional parameters
     * @return array CDR results
     */
    public function getCustomerCDRs($i_customer = null, $i_wholesaler = null, $additional_params = []) {
        $params = [];
        if ($i_customer !== null) $params['i_customer'] = $i_customer;
        if ($i_wholesaler !== null) $params['i_wholesaler'] = $i_wholesaler;
        $params = array_merge($params, $additional_params);

        error_log("Getting customer CDRs with parameters: " . json_encode($params));

        $result = $this->call('getCustomerCDRs', [$params]);

        error_log("Successfully retrieved customer CDRs");

        return $result;
    }

    /**
     * Create a new customer.
     *
     * @param string $name Customer's name
     * @param string $web_password Password for web interface
     * @param int $i_tariff Tariff ID
     * @param array $additional_params Additional parameters
     * @return array Creation result
     */
    public function createCustomer($name, $web_password, $i_tariff, $additional_params = []) {
        $params = array_merge([
            'name' => $name,
            'web_password' => $web_password,
            'i_tariff' => $i_tariff
        ], $additional_params);

        error_log("Creating customer with parameters: " . json_encode($params));

        $result = $this->call('createCustomer', [$params]);

        error_log("Successfully created customer");

        return $result;
    }

    /**
     * Block a customer.
     *
     * @param int $i_customer Customer ID to block
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @return string Result message
     */
    public function blockCustomer($i_customer, $i_wholesaler = null) {
        $params = ['i_customer' => $i_customer];
        if ($i_wholesaler !== null) {
            $params['i_wholesaler'] = $i_wholesaler;
        }

        error_log("Blocking customer with parameters: " . json_encode($params));

        $result = $this->call('blockCustomer', [$params]);

        error_log("Successfully blocked customer: {$i_customer}");

        return $result;
    }

    /**
     * Unblock a customer.
     *
     * @param int $i_customer Customer ID to unblock
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @return string Result message
     */
    public function unblockCustomer($i_customer, $i_wholesaler = null) {
        $params = ['i_customer' => $i_customer];
        if ($i_wholesaler !== null) {
            $params['i_wholesaler'] = $i_wholesaler;
        }

        error_log("Unblocking customer with parameters: " . json_encode($params));

        $result = $this->call('unblockCustomer', [$params]);

        error_log("Successfully unblocked customer: {$i_customer}");

        return $result;
    }

    /**
     * Update a customer.
     *
     * @param int $i_customer Customer ID to update
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @param array $update_params Update parameters
     * @return string Result message
     * @throws Exception If no update parameters provided
     */
    public function updateCustomer($i_customer, $i_wholesaler = null, $update_params = []) {
        if (empty($update_params)) {
            throw new Exception("At least one update parameter must be provided");
        }

        $params = ['i_customer' => $i_customer];
        if ($i_wholesaler !== null) {
            $params['i_wholesaler'] = $i_wholesaler;
        }
        $params = array_merge($params, $update_params);

        error_log("Updating customer with parameters: " . json_encode($params));

        $result = $this->call('updateCustomer', [$params]);

        error_log("Successfully updated customer: {$i_customer}");

        return $result;
    }

    /**
     * Delete a customer.
     *
     * @param int $i_customer Customer ID to delete
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @return string Result message
     */
    public function deleteCustomer($i_customer, $i_wholesaler = null) {
        $params = ['i_customer' => $i_customer];
        if ($i_wholesaler !== null) {
            $params['i_wholesaler'] = $i_wholesaler;
        }

        error_log("Deleting customer with parameters: " . json_encode($params));

        $result = $this->call('deleteCustomer', [$params]);

        error_log("Successfully deleted customer: {$i_customer}");

        return $result;
    }

    /**
     * Get customer information.
     *
     * @param int|string $customer_identifier Customer ID or name
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @return array Customer information
     */
    public function getCustomerInfo($customer_identifier, $i_wholesaler = null) {
        $params = ['i_customer' => $customer_identifier];
        if ($i_wholesaler !== null) {
            $params['i_wholesaler'] = $i_wholesaler;
        }

        error_log("Getting customer info with parameters: " . json_encode($params));

        $result = $this->call('getCustomerInfo', [$params]);

        error_log("Successfully retrieved customer info");

        return $result;
    }

    /**
     * Add funds to a customer's balance.
     *
     * @param int $i_customer Customer ID
     * @param float $amount Amount to add
     * @param string $currency Currency code
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @param string|null $payment_notes Payment notes
     * @param string|null $payment_time Payment time
     * @return string Result message
     */
    public function customerAddFunds($i_customer, $amount, $currency, $i_wholesaler = null, $payment_notes = null, $payment_time = null) {
        $params = [
            'i_customer' => $i_customer,
            'amount' => (float)$amount,
            'currency' => strtoupper($currency)
        ];

        if ($i_wholesaler !== null) $params['i_wholesaler'] = $i_wholesaler;
        if ($payment_notes !== null) $params['payment_notes'] = $payment_notes;
        if ($payment_time !== null) $params['payment_time'] = $payment_time;

        error_log("Adding funds to customer with parameters: " . json_encode($params));

        $result = $this->call('customerAddFunds', [$params]);

        error_log("Successfully added funds to customer: {$i_customer}");

        return $result;
    }

    /**
     * Credit a customer's balance.
     *
     * @param int $i_customer Customer ID
     * @param float $amount Amount to credit
     * @param string $currency Currency code
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @param string|null $payment_notes Payment notes
     * @param string|null $payment_time Payment time
     * @return string Result message
     */
    public function customerCredit($i_customer, $amount, $currency, $i_wholesaler = null, $payment_notes = null, $payment_time = null) {
        $params = [
            'i_customer' => $i_customer,
            'amount' => (float)$amount,
            'currency' => strtoupper($currency)
        ];

        if ($i_wholesaler !== null) $params['i_wholesaler'] = $i_wholesaler;
        if ($payment_notes !== null) $params['payment_notes'] = $payment_notes;
        if ($payment_time !== null) $params['payment_time'] = $payment_time;

        error_log("Crediting customer with parameters: " . json_encode($params));

        $result = $this->call('customerCredit', [$params]);

        error_log("Successfully credited customer: {$i_customer}");

        return $result;
    }

    /**
     * Debit a customer's balance.
     *
     * @param int $i_customer Customer ID
     * @param float $amount Amount to debit
     * @param string $currency Currency code
     * @param int|null $i_wholesaler Wholesaler ID for trusted mode
     * @param string|null $payment_notes Payment notes
     * @param string|null $payment_time Payment time
     * @return string Result message
     */
    public function customerDebit($i_customer, $amount, $currency, $i_wholesaler = null, $payment_notes = null, $payment_time = null) {
        $params = [
            'i_customer' => $i_customer,
            'amount' => (float)$amount,
            'currency' => strtoupper($currency)
        ];

        if ($i_wholesaler !== null) $params['i_wholesaler'] = $i_wholesaler;
        if ($payment_notes !== null) $params['payment_notes'] = $payment_notes;
        if ($payment_time !== null) $params['payment_time'] = $payment_time;

        error_log("Debiting customer with parameters: " . json_encode($params));

        $result = $this->call('customerDebit', [$params]);

        error_log("Successfully debited customer: {$i_customer}");

        return $result;
    }

    // Methods for managing service plans on the Switches

    /**
     * Retrieve the list of routing groups with all parameters per each.
     *
     * @param string|null $name_pattern Pattern to filter routing groups by name (SQL syntax for the LIKE operator is used)
     * @param int|null $i_routing_group Specific routing group ID to retrieve
     * @return array A dictionary containing result and list of routing groups
     */
    public function listRoutingGroups($name_pattern = null, $i_routing_group = null) {
        $params = [];
        if ($name_pattern !== null) $params['name_pattern'] = $name_pattern;
        if ($i_routing_group !== null) $params['i_routing_group'] = $i_routing_group;

        error_log("Listing routing groups with parameters: " . json_encode($params));

        $result = $this->call('listRoutingGroups', [$params]);

        error_log("Successfully retrieved routing groups list");

        return $result;
    }

    // Methods for managing DIDs on the Switches

    /**
     * Get a list of DIDs with filtering options.
     *
     * @param string|null $did DID number pattern (SQL syntax for the ILIKE operator is used)
     * @param string|null $incoming_did Incoming DID number pattern (SQL syntax for the ILIKE operator is used)
     * @param int|null $delegated_to Assigned customer (Id of customer the DID is delegated to)
     * @param int|null $i_account Assigned account
     * @param int|null $i_ivr_application Assigned IVR application
     * @param bool|null $not_assigned DID is not assigned to any account, customer or IVR application
     * @param int|null $offset Skip first offset rows
     * @param int|null $limit Return only limit rows
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     *               - dids: Array of DID structures with the following fields:
     *                 - i_did: DID's Id (Integer)
     *                 - did: DID number (String)
     *                 - did_range_end: DID range end (String)
     *                 - i_did_allocated_from: i_did that child DID or DID subrange is allocated from (Integer)
     *                 - incoming_did: Incoming DID number (String)
     *                 - translation_rule: Translation rule applied to DID (String)
     *                 - cli_translation_rule: CLI translation rule (String)
     *                 - description: Custom description (String)
     *                 - i_ivr_application: IVR application assigned to the DID (Integer)
     *                 - i_account: Account assigned to the DID (Integer)
     *                 - i_dids_charging_group: Charging group to be used (Integer)
     *                 - i_vendor: Vendor to authenticate this DID (Integer)
     *                 - i_connection: Connection to authenticate this DID (Integer)
     *                 - buying_i_dids_charging_group: Charging group to charge vendor (Integer)
     *                 - i_did_delegation: Id of DID delegation (Integer)
     *                 - delegated_to: Id of the subcustomer the DID is delegated to (Integer)
     *                 - parent_i_did_delegation: Id of the parent DID delegation entry (Integer, NULL for first delegation)
     *                 - incoming_cli: Incoming CLI to match authentication rule (String, Optional, since 2020)
     */
    public function getDIDsList($did = null, $incoming_did = null, $delegated_to = null, $i_account = null, $i_ivr_application = null, $not_assigned = null, $offset = null, $limit = null) {
        $params = [];
        if ($did !== null) $params['did'] = $did;
        if ($incoming_did !== null) $params['incoming_did'] = $incoming_did;
        if ($delegated_to !== null) $params['delegated_to'] = $delegated_to;
        if ($i_account !== null) $params['i_account'] = $i_account;
        if ($i_ivr_application !== null) $params['i_ivr_application'] = $i_ivr_application;
        if ($not_assigned !== null) $params['not_assigned'] = $not_assigned;
        if ($offset !== null) $params['offset'] = $offset;
        if ($limit !== null) $params['limit'] = $limit;

        error_log("Getting DIDs list with parameters: " . json_encode($params));

        $result = $this->call('getDIDsList', [$params]);

        error_log("Successfully retrieved DIDs list");

        return $result;
    }

    /**
     * Get DID information.
     *
     * Either i_did or did should be specified.
     *
     * Note (since 2023): As the feature for DID ranges is included, input parameters must conform to one of the following rules:
     * 1. i_did: The API functions in the same way that it did before.
     * 2. Only did: The API searches the DID number by using did because the did_range_end is not provided.
     * 3. Only did_range_end: This case is invalid.
     * 4. did and did_range_end: These parameters are used to get DID range.
     *
     * @param int|null $i_did DID's Id
     * @param string|null $did DID number
     * @param string|null $did_range_end DID range end (since 2023)
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     *               - i_did: DID's Id (Integer)
     *               - did: DID number (String)
     *               - did_range_end: DID range end (String, since 2023)
     *               - i_did_allocated_from: i_did that child DID or DID subrange is allocated from (Integer, since 2023)
     *               - incoming_did: Incoming DID number (String)
     *               - translation_rule: Translation rule applied to DID (String)
     *               - cld_translation_rule: CLD translation rule (String)
     *               - cli_translation_rule: CLI translation rule (String)
     *               - description: Custom description (String)
     *               - i_ivr_application: IVR application assigned to the DID (Integer)
     *               - i_account: Account assigned to the DID (Integer)
     *               - i_dids_charging_group: Charging group to be used (Integer)
     *               - i_vendor: Vendor to authenticate this DID (Integer)
     *               - i_connection: Connection to authenticate this DID (Integer)
     *               - buying_i_dids_charging_group: Charging group to charge vendor (Integer)
     *               - i_did_delegation: Id of DID delegation (Integer)
     *               - delegated_to: Id of the subcustomer the DID is delegated to (Integer)
     *               - parent_i_did_delegation: Id of the parent DID delegation entry (Integer, NULL for first delegation, since 4.4)
     *               - incoming_cli: Incoming CLI to match authentication rule (String, Optional, since 2021)
     * @throws Exception If neither i_did nor did is specified
     */
    public function getDIDInfo($i_did = null, $did = null, $did_range_end = null) {
        if ($i_did === null && $did === null) {
            throw new Exception("Either i_did or did must be specified");
        }

        $params = [];
        if ($i_did !== null) $params['i_did'] = $i_did;
        if ($did !== null) $params['did'] = $did;
        if ($did_range_end !== null) $params['did_range_end'] = $did_range_end;

        error_log("Getting DID info with parameters: " . json_encode($params));

        $result = $this->call('getDIDInfo', [$params]);

        error_log("Successfully retrieved DID info");

        return $result;
    }

    /**
     * Update a DID.
     *
     * Either i_did or did should be specified in the params array. At least one update parameter must be provided.
     *
     * Note: To remove Account's assignment from DID, i_account should be supplied as null.
     * - If i_did is supplied in such call, i_ivr_application should be supplied as null too.
     * - If did is supplied in such call, it's enough to provide i_account as null.
     *
     * Note (since 2023): As the feature for DID ranges is included, input parameters must conform to one of the following rules:
     * 1. i_did: The API functions in the same way that it did before.
     * 2. Only did: The API searches the DID number before the update by using did because the did_range_end is not provided.
     * 3. Only did_range_end: This case is invalid.
     * 4. did and did_range_end: These parameters are used to fetch DID range for the update.
     *
     * Important: If did or did_range_end need to be updated, i_did must be provided.
     *
     * @param array $params Associative array of parameters:
     *                      - i_did: DID's Id (Integer, optional)
     *                      - did: DID number (String, optional)
     *                      - did_range_end: DID range end (String, optional, since 2023)
     *                      - incoming_did: Incoming DID number (String, optional)
     *                      - translation_rule: Translation rule applied to DID (String, optional)
     *                      - cli_translation_rule: CLI translation rule (String, optional)
     *                      - description: Custom description (String, optional)
     *                      - i_ivr_application: IVR application assigned to the DID (Integer/null, optional - use null to remove)
     *                      - i_account: Account assigned to the DID (Integer/null, optional - use null to remove)
     *                      - i_dids_charging_group: Selling charging group to be used (Integer, optional)
     *                      - i_vendor: Vendor to authenticate this DID (Integer, optional)
     *                      - i_connection: Connection to authenticate this DID (Integer, optional)
     *                      - buying_i_dids_charging_group: Charging group to charge vendor (Integer, optional)
     *                      - incoming_cli: Incoming CLI to match authentication rule (String, optional, since 2020)
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     *               - i_did: Id of updated DID (Integer)
     * @throws Exception If neither i_did nor did is specified, or if no update parameters provided
     */
    public function updateDID($params = []) {
        // Check if either i_did or did is specified
        if (!isset($params['i_did']) && !isset($params['did'])) {
            throw new Exception("Either i_did or did must be specified");
        }

        // Check if at least one update parameter is provided (excluding identifier params)
        $update_params = array_diff_key($params, array_flip(['i_did', 'did', 'did_range_end']));
        if (empty($update_params)) {
            throw new Exception("At least one parameter must be updated");
        }

        error_log("Updating DID with parameters: " . json_encode($params));

        $result = $this->call('updateDID', [$params]);

        error_log("Successfully updated DID");

        return $result;
    }

    /**
     * Delete a DID.
     *
     * Either i_did or did should be specified.
     *
     * Note (since 2023): As the feature for DID ranges is included, input parameters must conform to one of the following rules:
     * 1. i_did: The API functions in the same way that it did before.
     * 2. Only did: The API searches the DID number before the delete by using did because the did_range_end is not provided.
     * 3. Only did_range_end: This case is invalid.
     * 4. did and did_range_end: These parameters are used to delete DID range.
     *
     * @param int|null $i_did DID's Id
     * @param string|null $did DID number
     * @param string|null $did_range_end DID range end (since 2023)
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     * @throws Exception If neither i_did nor did is specified
     */
    public function deleteDID($i_did = null, $did = null, $did_range_end = null) {
        if ($i_did === null && $did === null) {
            throw new Exception("Either i_did or did must be specified");
        }

        $params = [];
        if ($i_did !== null) $params['i_did'] = $i_did;
        if ($did !== null) $params['did'] = $did;
        if ($did_range_end !== null) $params['did_range_end'] = $did_range_end;

        error_log("Deleting DID with parameters: " . json_encode($params));

        $result = $this->call('deleteDID', [$params]);

        error_log("Successfully deleted DID");

        return $result;
    }

    /**
     * Delegate a DID number to a subcustomer.
     *
     * Any delegated DID number can be re-delegated to the subcustomer, e.g. Customer A delegates a DID to its 
     * subcustomer B, then subcustomer B re-delegates the DID to its subcustomer C, and so on. 
     * Number of re-delegations is not limited.
     *
     * @param int $i_did Id of the delegated DID
     * @param int $delegated_to Id of the subcustomer the DID is delegated to
     * @param int|null $parent_i_did_delegation Id of the parent DID delegation entry (NULL for the first delegation)
     * @param int|null $i_dids_charging_group Charging group to be used
     * @param string|null $description Description
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     *               - i_did_delegation: Id of added DID delegation (Integer)
     */
    public function addDIDDelegation($i_did, $delegated_to, $parent_i_did_delegation = null, $i_dids_charging_group = null, $description = null) {
        $params = [
            'i_did' => $i_did,
            'delegated_to' => $delegated_to,
            'parent_i_did_delegation' => $parent_i_did_delegation
        ];

        if ($i_dids_charging_group !== null) $params['i_dids_charging_group'] = $i_dids_charging_group;
        if ($description !== null) $params['description'] = $description;

        error_log("Adding DID delegation with parameters: " . json_encode($params));

        $result = $this->call('addDIDDelegation', [$params]);

        error_log("Successfully added DID delegation");

        return $result;
    }

    /**
     * Update a DID delegation entry.
     *
     * @param int $i_did_delegation Id of DID delegation
     * @param int|null $i_dids_charging_group Charging group to be used
     * @param int|null $delegated_to Id of the subcustomer the DID is delegated to
     * @param string|null $description Description
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     *               - i_did_delegation: Id of updated DID delegation (Integer)
     * @throws Exception If no update parameters provided
     */
    public function updateDIDDelegation($i_did_delegation, $i_dids_charging_group = null, $delegated_to = null, $description = null) {
        $params = ['i_did_delegation' => $i_did_delegation];

        if ($i_dids_charging_group !== null) $params['i_dids_charging_group'] = $i_dids_charging_group;
        if ($delegated_to !== null) $params['delegated_to'] = $delegated_to;
        if ($description !== null) $params['description'] = $description;

        // Check if at least one update parameter is provided
        if (count($params) === 1) {
            throw new Exception("At least one parameter must be updated");
        }

        error_log("Updating DID delegation with parameters: " . json_encode($params));

        $result = $this->call('updateDIDDelegation', [$params]);

        error_log("Successfully updated DID delegation");

        return $result;
    }

    /**
     * Delete a DID delegation entry.
     *
     * @param int $i_did_delegation Id of DID delegation
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     */
    public function deleteDIDDelegation($i_did_delegation) {
        $params = ['i_did_delegation' => $i_did_delegation];

        error_log("Deleting DID delegation with parameters: " . json_encode($params));

        $result = $this->call('deleteDIDDelegation', [$params]);

        error_log("Successfully deleted DID delegation");

        return $result;
    }

    // Methods for managing authentication rules on the Switches

    /**
     * Add an authentication rule to an account.
     *
     * At least one of the following parameters must be specified: remote_ip, incoming_cli, incoming_cld, to_domain, from_domain.
     *
     * @param int $i_account Unique ID of Account to add Rule to
     * @param int $i_protocol Protocol ID (1=SIP, 2=H.323 [Deprecated from 2020], 3=IAX2, 4=Calling Card PIN)
     * @param string|null $remote_ip Caller's IP address
     * @param string|null $incoming_cli Caller's number (CLI)
     * @param string|null $incoming_cld Callee's number (CLD)
     * @param string|null $to_domain Hostname in To header of incoming INVITE (from Sippy 2020)
     * @param string|null $from_domain Hostname in From header of incoming INVITE (from Sippy 2020)
     * @param string|null $cli_translation_rule Caller's number (CLI) Translation Rule
     * @param string|null $cld_translation_rule Callee's number (CLD) Translation Rule
     * @param int|null $i_tariff Unique ID of existing Tariff (NULL means use tariff from Account's Service Plan)
     * @param int|null $i_routing_group Unique ID of existing Routing Group (NULL means use the Account's routing group)
     * @param int|null $max_sessions Concurrent calls limit for the rule (-1 means Unlimited, from Sippy 2020)
     * @param float|null $max_cps Call rate limit for the rule (NULL means Unlimited, from Sippy 2020)
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     *               - i_authentication: ID of created authentication rule (Integer)
     * @throws Exception If neither remote_ip, incoming_cli, incoming_cld, to_domain, nor from_domain is specified
     */
    public function addAuthRule($i_account, $i_protocol, $remote_ip = null, $incoming_cli = null, $incoming_cld = null, $to_domain = null, $from_domain = null, $cli_translation_rule = null, $cld_translation_rule = null, $i_tariff = null, $i_routing_group = null, $max_sessions = null, $max_cps = null) {
        // Validate that at least one identifier is provided
        if ($remote_ip === null && $incoming_cli === null && $incoming_cld === null && $to_domain === null && $from_domain === null) {
            throw new Exception("At least one of the following parameters must be specified: remote_ip, incoming_cli, incoming_cld, to_domain, from_domain");
        }

        $params = [
            'i_account' => $i_account,
            'i_protocol' => $i_protocol
        ];

        if ($remote_ip !== null) $params['remote_ip'] = $remote_ip;
        if ($incoming_cli !== null) $params['incoming_cli'] = $incoming_cli;
        if ($incoming_cld !== null) $params['incoming_cld'] = $incoming_cld;
        if ($to_domain !== null) $params['to_domain'] = $to_domain;
        if ($from_domain !== null) $params['from_domain'] = $from_domain;
        if ($cli_translation_rule !== null) $params['cli_translation_rule'] = $cli_translation_rule;
        if ($cld_translation_rule !== null) $params['cld_translation_rule'] = $cld_translation_rule;
        if ($i_tariff !== null) $params['i_tariff'] = $i_tariff;
        if ($i_routing_group !== null) $params['i_routing_group'] = $i_routing_group;
        if ($max_sessions !== null) $params['max_sessions'] = $max_sessions;
        if ($max_cps !== null) $params['max_cps'] = $max_cps;

        error_log("Adding authentication rule with parameters: " . json_encode($params));

        $result = $this->call('addAuthRule', [$params]);

        error_log("Successfully added authentication rule");

        return $result;
    }

    /**
     * Update an authentication rule.
     *
     * @param int $i_authentication Authentication rule ID
     * @param int|null $i_account Unique ID of Account (Optional, removed in >=5.2 version)
     * @param int|null $i_protocol Protocol ID (1=SIP, 2=H.323 [Deprecated from 2020], 3=IAX2, 4=Calling Card PIN)
     * @param string|null $remote_ip Caller's IP address
     * @param string|null $incoming_cli Caller's number (CLI)
     * @param string|null $incoming_cld Callee's number (CLD)
     * @param string|null $to_domain Hostname in To header of incoming INVITE (from Sippy 2020)
     * @param string|null $from_domain Hostname in From header of incoming INVITE (from Sippy 2020)
     * @param string|null $cli_translation_rule Caller's number (CLI) Translation Rule
     * @param string|null $cld_translation_rule Callee's number (CLD) Translation Rule
     * @param int|null $i_tariff Unique ID of existing Tariff (NULL means use tariff from Account's Service Plan)
     * @param int|null $i_routing_group Unique ID of existing Routing Group (NULL means use the Account's routing group)
     * @param int|null $max_sessions Concurrent calls limit for the rule (-1 means Unlimited, from Sippy 2020)
     * @param float|null $max_cps Call rate limit for the rule (NULL means Unlimited, from Sippy 2020)
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     * @throws Exception If no update parameters provided
     */
    public function updateAuthRule($i_authentication, $i_account = null, $i_protocol = null, $remote_ip = null, $incoming_cli = null, $incoming_cld = null, $to_domain = null, $from_domain = null, $cli_translation_rule = null, $cld_translation_rule = null, $i_tariff = null, $i_routing_group = null, $max_sessions = null, $max_cps = null) {
        $params = ['i_authentication' => $i_authentication];

        if ($i_account !== null) $params['i_account'] = $i_account;
        if ($i_protocol !== null) $params['i_protocol'] = $i_protocol;
        if ($remote_ip !== null) $params['remote_ip'] = $remote_ip;
        if ($incoming_cli !== null) $params['incoming_cli'] = $incoming_cli;
        if ($incoming_cld !== null) $params['incoming_cld'] = $incoming_cld;
        if ($to_domain !== null) $params['to_domain'] = $to_domain;
        if ($from_domain !== null) $params['from_domain'] = $from_domain;
        if ($cli_translation_rule !== null) $params['cli_translation_rule'] = $cli_translation_rule;
        if ($cld_translation_rule !== null) $params['cld_translation_rule'] = $cld_translation_rule;
        if ($i_tariff !== null) $params['i_tariff'] = $i_tariff;
        if ($i_routing_group !== null) $params['i_routing_group'] = $i_routing_group;
        if ($max_sessions !== null) $params['max_sessions'] = $max_sessions;
        if ($max_cps !== null) $params['max_cps'] = $max_cps;

        // Check if at least one update parameter is provided
        if (count($params) === 1) {
            throw new Exception("At least one parameter must be updated");
        }

        error_log("Updating authentication rule with parameters: " . json_encode($params));

        $result = $this->call('updateAuthRule', [$params]);

        error_log("Successfully updated authentication rule");

        return $result;
    }

    /**
     * Delete an authentication rule.
     *
     * @param int $i_authentication Authentication rule ID
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     */
    public function delAuthRule($i_authentication) {
        $params = ['i_authentication' => $i_authentication];

        error_log("Deleting authentication rule with parameters: " . json_encode($params));

        $result = $this->call('delAuthRule', [$params]);

        error_log("Successfully deleted authentication rule");

        return $result;
    }

    /**
     * Get authentication rule information.
     *
     * Available from version 4.5.
     *
     * @param int $i_authentication Authentication rule ID
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     *               - authrule: Structure with authentication rule details
     */
    public function getAuthRuleInfo($i_authentication) {
        $params = ['i_authentication' => $i_authentication];

        error_log("Getting authentication rule info with parameters: " . json_encode($params));

        $result = $this->call('getAuthRuleInfo', [$params]);

        error_log("Successfully retrieved authentication rule info");

        return $result;
    }

    /**
     * List authentication rules for an account.
     *
     * Available from version 2.0.
     *
     * @param int $i_account Unique ID of Account (Required since Sippy 2020)
     * @param int|null $i_authentication Authentication rule ID (Deprecated starting from Sippy 2020)
     * @param int|null $i_protocol Protocol ID (1=SIP, 2=H.323 [Deprecated from 2020], 3=IAX2, 4=Calling Card PIN)
     * @param string|null $remote_ip Caller's IP address
     * @param int|null $offset Skip first offset records
     * @param int|null $limit Return only limit records
     * @return array A dictionary containing:
     *               - result: "OK" if successful
     *               - authrules: Array of authentication rule structures with fields:
     *                 - i_authentication: Authentication rule ID (Integer)
     *                 - i_account: Account ID (removed since Sippy 2020)
     *                 - remote_ip: Caller's IP address (String)
     *                 - incoming_cli: Caller's number (String)
     *                 - incoming_cld: Callee's number (String)
     *                 - to_domain: To header hostname (String, from Sippy 2020)
     *                 - from_domain: From header hostname (String, from Sippy 2020)
     *                 - cli_translation_rule: CLI translation rule (String)
     *                 - cld_translation_rule: CLD translation rule (String)
     *                 - i_protocol: Protocol ID (Integer, from Sippy 2020)
     */
    public function listAuthRules($i_account, $i_authentication = null, $i_protocol = null, $remote_ip = null, $offset = null, $limit = null) {
        $params = ['i_account' => $i_account];

        if ($i_authentication !== null) $params['i_authentication'] = $i_authentication;
        if ($i_protocol !== null) $params['i_protocol'] = $i_protocol;
        if ($remote_ip !== null) $params['remote_ip'] = $remote_ip;
        if ($offset !== null) $params['offset'] = $offset;
        if ($limit !== null) $params['limit'] = $limit;

        error_log("Listing authentication rules with parameters: " . json_encode($params));

        $result = $this->call('listAuthRules', [$params]);

        error_log("Successfully retrieved authentication rules list");

        return $result;
    }

    // Utility methods

    /**
     * Get a dictionary of available values for various parameters.
     *
     * Supported dictionaries:
     * - 'languages' - List of defined languages (requires 'type' param: 'web' or 'ivr')
     * - 'export_types' - List of defined export types
     * - 'currencies' - List of available currencies for authenticated customer
     * - 'timezones' - List of available timezones
     * - 'media_relay_types' - List of available media-relay types
     * - 'media_relays' - List of available media-relays
     * - 'protocols' - List of available protocols
     * - 'proto_transports' - List of available protocol transports (since 2021)
     * - 'qmon_actions' - List of available actions for quality monitoring on vendor connection
     * - 'forward_did_modes' - List of possible DID Forward Modes for an incoming route
     * - 'upload_types' - List of supported upload types (since 5.1)
     * - 'privacy_modes' - List of possible privacy mode values on connection (since 5.1)
     * - 'tariff_types' - List of defined tariff types (since 2020)
     * - 'ssl_certificate_types' - List of SSL certificate types (since 2021)
     * - 'ca_list_types' - List of CA list types (since 2021)
     * - 'ssl_use_domain_types' - List of domain types used for CA list and SSL certificates (since 2023)
     * - 'trunk_policies' - List of available trunk policies (since 2023)
     *
     * @param string $name Name of dictionary to retrieve
     * @param array $additional_params Additional parameters (e.g., 'type' => 'web' for languages)
     * @return array Dictionary containing 'result' => 'OK' and 'dictionary' => [requested data]
     */
    public function getDictionary($name, $additional_params = []) {
        $params = array_merge(['name' => $name], $additional_params);

        error_log("Getting dictionary '{$name}' with parameters: " . json_encode($params));

        $result = $this->call('getDictionary', [$params]);

        error_log("Successfully retrieved dictionary '{$name}'");

        return $result;
    }
}
