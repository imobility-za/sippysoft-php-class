<?php

/**
 * iMobility SippySoft PHP Wrapper
 *
 * This script demonstrates how to use the SippySoftClient class.
 * Make sure to update the credentials and host with your actual values.
 */

require_once 'vendors/imobility-sippy.php';
require_once 'config.php';

try {
    // Initialize the client with your SippySoft server URL containing embedded credentials
    // Replace these with your actual server details
    $url = 'https://' . $username . ':' . $password . '@' . $host . '/xmlapi/xmlapi';
    $client = new SippySoftClient($url, true, true); // Verify SSL

    echo "SippySoft Client initialized successfully!\n";


    // Example: Create a customer
    // Note: All required parameters must be provided as per SippySoft API documentation
    try {
        $customerParams = [
            // Mandatory parameters
            'name' => 'testcustomer123',
            'web_password' => 'SecureWebPass123!',
            'i_tariff' => 736, // null to assign own tariff, or ID of existing tariff
            
            // Optional parameters - Authentication & Access
            //'web_login' => 'testcustomer123',
            //'api_access' => 0,
            //'api_password' => 'SecureWebPass123!',
            //'api_mgmt' => 0,
            
            // Optional parameters - Financial
            //'balance' => 0.0,
            //'credit_limit' => 1000.0,
            //'payment_currency' => 'ZAR',
            //'payment_method' => 1,
            //'min_payment_amount' => 0.0,
            
            // Optional parameters - Management Rights (bitmask: bit 0=add, bit 1=edit, bit 2=delete)
            //'accounts_mgmt' => 7, // 7 = can add, edit, delete (111 in binary)
            //'customers_mgmt' => 7, // 7 = can add, edit, delete
            //'tariffs_mgmt' => 0, // 0 = no rights
            //'vouchers_mgmt' => 0, // 0 = no rights
            
            // Optional parameters - Routing & Rules
            //'accounts_matching_rule' => '',
            
            // Optional parameters - Contact Information
            //'company_name' => '',
            //'salutation' => '',
            //'first_name' => '',
            //'last_name' => '',
            //'mid_init' => '',
            //'street_addr' => '',
            //'state' => '',
            //'postal_code' => '',
            //'city' => '',
            //'country' => '',
            //'contact' => '',
            //'phone' => '',
            //'fax' => '',
            //'alt_phone' => '',
            //'alt_contact' => '',
            //'email' => '',
            //'cc' => '',
            //'bcc' => '',
            //'mail_from' => '',
            
            // Optional parameters - Localization & Display
            //'i_time_zone' => 367,
            //'i_lang' => 'en',
            //'i_export_type' => 1,
            //'start_page' => null,
            //'css' => '',
            //'dns_alias' => '',
            
            // Optional parameters - Limits & Restrictions
            //'max_sessions' => null, // null means Unlimited
            //'max_calls_per_second' => null, // null means Unlimited
            //'max_depth' => null,
            
            // Optional parameters - Features & Capabilities
            //'use_own_tariff' => 0,
            //'callshop_enabled' => false,
            //'overcommit_protection' => false,
            //'overcommit_limit' => 0.0,
            //'did_pool_enabled' => false,
            //'ivr_apps_enabled' => false,
            //'asr_acd_enabled' => false,
            //'debit_credit_cards_enabled' => false,
            //'conferencing_enabled' => false,
            //'share_payment_processors' => false,
            //'dncl_enabled' => false,
            
            // Optional parameters - Commission
            //'i_commission_agent' => null,
            //'commission_size' => 0.0,
            
            // Optional parameters - Security
            //'i_password_policy' => 1,
            
            // Optional parameters - Description
            'description' => ''
        ];
        
        $result = $client->createCustomer($customerParams);
        echo "Customer created: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error creating customer: " . $e->getMessage() . "\n";
    }
    
} catch (SippySoftError $e) {
    echo "SippySoft Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
