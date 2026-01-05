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

    // Example: Get dictionaries to find valid values for parameters

    try {
        // Get available timezones
        $timezones = $client->getDictionary('timezones');
        echo "Available timezones: " . json_encode($timezones, JSON_PRETTY_PRINT) . "\n\n";

        // Get available currencies
        $currencies = $client->getDictionary('currencies');
        echo "Available currencies: " . json_encode($currencies, JSON_PRETTY_PRINT) . "\n\n";

        // Get available export types
        $exportTypes = $client->getDictionary('export_types');
        echo "Available export types: " . json_encode($exportTypes, JSON_PRETTY_PRINT) . "\n\n";

        // Get available languages for web interface
        $languages = $client->getDictionary('languages', ['type' => 'web']);
        echo "Available web languages: " . json_encode($languages, JSON_PRETTY_PRINT) . "\n\n";

        // Get available media relay types
        $mediaRelayTypes = $client->getDictionary('media_relay_types');
        echo "Available media relay types: " . json_encode($mediaRelayTypes, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Exception $e) {
        echo "Error getting dictionaries: " . $e->getMessage() . "\n";
    }
    
} catch (SippySoftError $e) {
    echo "SippySoft Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}