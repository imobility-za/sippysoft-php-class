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

    // Example: Add Customer Funds
    try {
        $response = $client->customerAddFunds(190,10,'ZAR','Adding funds via API'); // Remember to change customer ID, amount, currency, and description as needed
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error loading funds : " . $e->getMessage() . "\n";
    }

    // Example: Remove Customer Funds
    try {
        $response = $client->customerDebit(190,10,'ZAR','Removing funds via API'); // Remember to change customer ID, amount, currency, and description as needed
        echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error loading funds : " . $e->getMessage() . "\n";
    }
    
} catch (SippySoftError $e) {
    echo "SippySoft Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

?>
