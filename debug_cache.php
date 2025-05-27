<?php
/**
 * Debug script to check cache table contents
 * Run this from WordPress admin or via WP-CLI
 */

// Make sure we're in WordPress context
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        '../../../wp-load.php',
        '../../wp-load.php', 
        '../wp-load.php',
        'wp-load.php'
    ];
    
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            break;
        }
    }
    
    if (!defined('ABSPATH')) {
        die('WordPress not found. Please run this script from the WordPress directory or adjust the path.');
    }
}

// Include the logging function
require_once(plugin_dir_path(__FILE__) . 'includes/amazon-api.php');

function debug_cache_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ps_cache';
    
    ps_log_error("=== DEBUG CACHE TABLE START ===");
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        ps_log_error("ERROR: Cache table '$table_name' does not exist!");
        return;
    }
    
    ps_log_error("Cache table '$table_name' exists");
    
    // Check table structure
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    ps_log_error("Table columns:");
    foreach ($columns as $column) {
        ps_log_error("  - {$column->Field} ({$column->Type}) - Default: {$column->Default} - Null: {$column->Null}");
    }
    
    // Get total count
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    ps_log_error("Total records in cache table: $total_count");
    
    if ($total_count > 0) {
        // Get recent records
        $recent_records = $wpdb->get_results("SELECT id, user_id, query_hash, created_at, expires_at FROM $table_name ORDER BY created_at DESC LIMIT 10");
        
        ps_log_error("Recent records:");
        foreach ($recent_records as $record) {
            ps_log_error("  ID: {$record->id}, user_id: '{$record->user_id}', query_hash: {$record->query_hash}, created: {$record->created_at}");
        }
        
        // Check for records with user_id = 0 or NULL
        $zero_user_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = '0' OR user_id = 0");
        $null_user_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id IS NULL");
        $empty_user_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = ''");
        
        ps_log_error("Records with user_id = '0' or 0: $zero_user_count");
        ps_log_error("Records with user_id IS NULL: $null_user_count");
        ps_log_error("Records with user_id = '': $empty_user_count");
        
        // Get unique user_ids
        $unique_users = $wpdb->get_results("SELECT DISTINCT user_id, COUNT(*) as count FROM $table_name GROUP BY user_id ORDER BY count DESC");
        ps_log_error("Unique user_ids in cache:");
        foreach ($unique_users as $user) {
            ps_log_error("  user_id: '{$user->user_id}' - {$user->count} records");
        }
    }
    
    ps_log_error("=== DEBUG CACHE TABLE END ===");
}

function test_user_identifier() {
    ps_log_error("=== TESTING USER IDENTIFIER ===");
    
    // Test the user identifier function
    if (function_exists('ps_get_user_identifier')) {
        $user_id = ps_get_user_identifier();
        ps_log_error("Current user identifier: '$user_id'");
        
        // Test if user is logged in
        if (is_user_logged_in()) {
            $wp_user_id = get_current_user_id();
            ps_log_error("WordPress user is logged in with ID: $wp_user_id");
        } else {
            ps_log_error("No WordPress user logged in");
        }
        
        // Check cookies
        ps_log_error("Available cookies:");
        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'ps_') === 0 || strpos($name, 'visitor') !== false) {
                ps_log_error("  $name = $value");
            }
        }
    } else {
        ps_log_error("ERROR: ps_get_user_identifier function not found!");
    }
    
    ps_log_error("=== USER IDENTIFIER TEST END ===");
}

// Run the debug functions
debug_cache_table();
test_user_identifier();

echo "Debug complete. Check the error log for results.\n"; 