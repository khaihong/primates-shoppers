<?php
// Load WordPress
require_once('/volume1/docker/primates/wp-load.php');
require_once('/volume1/docker/primates/wp-content/plugins/primates-shoppers/includes/amazon-api.php');

global $wpdb;
$table_name = $wpdb->prefix . 'ps_cache';

echo 'Checking cache table...' . PHP_EOL;

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
if (!$table_exists) {
    echo 'ERROR: Cache table does not exist!' . PHP_EOL;
    exit;
}

echo 'Table exists: ' . $table_name . PHP_EOL;

// Get recent records
$recent_records = $wpdb->get_results("SELECT id, user_id, query_hash, created_at FROM $table_name ORDER BY created_at DESC LIMIT 5");

echo 'Recent records:' . PHP_EOL;
foreach ($recent_records as $record) {
    echo '  ID: ' . $record->id . ', user_id: "' . $record->user_id . '", created: ' . $record->created_at . PHP_EOL;
}

// Check for problematic user_ids
$zero_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = '0' OR user_id = 0");
$null_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id IS NULL");
$empty_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = ''");

echo 'Records with user_id = 0: ' . $zero_count . PHP_EOL;
echo 'Records with user_id IS NULL: ' . $null_count . PHP_EOL;
echo 'Records with user_id = empty: ' . $empty_count . PHP_EOL;

// Test user identifier function
echo PHP_EOL . 'Testing user identifier function...' . PHP_EOL;
if (function_exists('ps_get_user_identifier')) {
    $user_id = ps_get_user_identifier();
    echo 'Generated user_id: "' . $user_id . '"' . PHP_EOL;
} else {
    echo 'ERROR: ps_get_user_identifier function not found!' . PHP_EOL;
} 