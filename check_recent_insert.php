<?php
// Check the most recent insert to see what user_id was stored
require_once('../../../wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'ps_cache';

echo "Checking most recent cache insert...\n";

// Get the most recent record (should be ID 7 based on logs)
$recent_record = $wpdb->get_row("SELECT id, user_id, query_hash, created_at FROM $table_name ORDER BY id DESC LIMIT 1");

if ($recent_record) {
    echo "Most recent record:\n";
    echo "  ID: " . $recent_record->id . "\n";
    echo "  User ID: '" . $recent_record->user_id . "'\n";
    echo "  Query Hash: " . $recent_record->query_hash . "\n";
    echo "  Created: " . $recent_record->created_at . "\n";
    
    // Check if user_id is 0, NULL, or empty
    if ($recent_record->user_id === '0' || $recent_record->user_id === 0) {
        echo "ERROR: user_id is 0!\n";
    } elseif ($recent_record->user_id === null) {
        echo "ERROR: user_id is NULL!\n";
    } elseif ($recent_record->user_id === '') {
        echo "ERROR: user_id is empty!\n";
    } else {
        echo "SUCCESS: user_id is properly set to '" . $recent_record->user_id . "'\n";
    }
} else {
    echo "No records found in cache table.\n";
}

// Also check record ID 7 specifically (from the logs)
echo "\nChecking record ID 7 specifically:\n";
$record_7 = $wpdb->get_row("SELECT id, user_id, query_hash, created_at FROM $table_name WHERE id = 7");

if ($record_7) {
    echo "Record ID 7:\n";
    echo "  ID: " . $record_7->id . "\n";
    echo "  User ID: '" . $record_7->user_id . "'\n";
    echo "  Query Hash: " . $record_7->query_hash . "\n";
    echo "  Created: " . $record_7->created_at . "\n";
} else {
    echo "Record ID 7 not found.\n";
}

// Check all records with user_id = 0
echo "\nChecking for records with user_id = 0:\n";
$zero_records = $wpdb->get_results("SELECT id, user_id, created_at FROM $table_name WHERE user_id = '0' OR user_id = 0 ORDER BY id DESC LIMIT 5");

if ($zero_records) {
    echo "Found " . count($zero_records) . " records with user_id = 0:\n";
    foreach ($zero_records as $record) {
        echo "  ID: " . $record->id . ", User ID: '" . $record->user_id . "', Created: " . $record->created_at . "\n";
    }
} else {
    echo "No records found with user_id = 0.\n";
}
?> 