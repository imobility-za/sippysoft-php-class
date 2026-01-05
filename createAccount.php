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
    $client = new SippySoftClient($url, true); // Verify SSL

    echo "SippySoft Client initialized successfully!\n";


    // Example: Create an account
    // Note: All required parameters must be provided as per SippySoft API documentation
    try {
        $accountParams = [
            'username' => 'testuser123',
            'web_password' => 'SecureWebPass123!',
            'authname' => 'testuser123',
            'voip_password' => 'SecureVoIPPass123!',
            'max_sessions' => 10,
            'max_credit_time' => 3600,
            'translation_rule' => '',
            'cli_translation_rule' => '',
            'credit_limit' => 1000.0,
            'i_billing_plan' => 816,
            'i_time_zone' => 367,
            'balance' => 0.0,
            'cpe_number' => '',
            'vm_enabled' => 0,
            'vm_password' => '1234',
            'blocked' => 0,
            'i_lang' => 'en',
            'payment_currency' => 'ZAR',
            'payment_method' => 1,
            'i_export_type' => 1,
            'lifetime' => -1,
            'preferred_codec' => null,
            'use_preferred_codec_only' => false,
            'reg_allowed' => 0,
            'welcome_call_ivr' => 0,
            'on_payment_action' => null,
            'min_payment_amount' => 0.0,
            'trust_cli' => true,
            'disallow_loops' => false,
            'vm_notify_emails' => '',
            'vm_forward_emails' => '',
            'vm_del_after_fwd' => false,
            'company_name' => '',
            'salutation' => '',
            'first_name' => '',
            'last_name' => '',
            'mid_init' => '',
            'street_addr' => '',
            'state' => '',
            'postal_code' => '',
            'city' => '',
            'country' => '',
            'contact' => '',
            'phone' => '',
            'fax' => '',
            'alt_phone' => '',
            'alt_contact' => '',
            'email' => '',
            'cc' => '',
            'bcc' => '',
            'i_password_policy' => 1,
            'i_media_relay_type' => 2
        ];
        
        $result = $client->createAccount($accountParams);
        echo "Account created: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error creating account: " . $e->getMessage() . "\n";
    }
    
} catch (SippySoftError $e) {
    echo "SippySoft Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
