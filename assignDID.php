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
    
    // Example: Get DID information by DID id
    try {
        $dids = $client->getDIDInfo(155800); // Replace with actual DID id
        echo "DID Details: " . json_encode($dids, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error getting did list: " . $e->getMessage() . "\n";
    }

    // Example: Get DID information by DID number
    try {
        $dids = $client->getDIDInfo(did:'27100146474'); // Replace with actual DID number
        echo "DID Details: " . json_encode($dids, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error getting did list: " . $e->getMessage() . "\n";
    }

    // Example: Assign DID to account
    try {
        $dids = $client->updateDID(['did' => '27100146474', 'i_account' => 2130]); // Replace with actual DID number and account id
        echo "Response: " . json_encode($dids, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error assigning: " . $e->getMessage() . "\n";
    }

    // Example: Remove DID from account (must include i_ivr_application: null when using i_did)
    try {
        $dids = $client->updateDID(['i_did' => 155800, 'i_account' => null, 'i_ivr_application' => null]); // Replace with actual DID id
        echo "Response: " . json_encode($dids, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error removing: " . $e->getMessage() . "\n";
    }

    
} catch (SippySoftError $e) {
    echo "SippySoft Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

?>
