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
    // Credentials are loaded from config.php
    $url = 'https://' . $username . ':' . $password . '@' . $host . '/xmlapi/xmlapi';
    $client = new SippySoftClient($url, true); // Verify SSL

    echo "SippySoft Client initialized successfully!\n";

    // Example: List Account Auth Rules
    try {
        $response = $client->listAuthRules(2130);
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error loading authrules : " . $e->getMessage() . "\n";
    }

    // Example: Add Account Auth Rule
    
    try {
        $response = $client->addAuthRule(2130,1,'8.8.8.8');
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
        $authrule_id = $response['i_authentication'];
        echo "Added authrule ID: " . $authrule_id . "\n";
    } catch (Exception $e) {
        echo "Error Adding authrules : " . $e->getMessage() . "\n";
    }
    
    // Example: Remove Account Auth Rule
    try {
        $response = $client->delAuthRule($authrule_id);
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error removing authrule : " . $e->getMessage() . "\n";
    }
    
    
} catch (SippySoftError $e) {
    echo "SippySoft Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

?>
