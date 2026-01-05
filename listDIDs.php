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

    // Example: Get DID list
    try {
        $dids = $client->getDIDsList();
        echo "DIDs List: " . json_encode($dids, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error getting did list: " . $e->getMessage() . "\n";
    }
    
} catch (SippySoftError $e) {
    echo "SippySoft Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}

?>