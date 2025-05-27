<?php
/**
 * Debug Cache Query - Add this to WordPress temporarily
 * You can add this code to your theme's functions.php or run it via WordPress admin
 */

function debug_cache_table_contents() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ps_cache';
    
    // Include the logging function
    if (!function_exists('ps_log_error')) {
        function ps_log_error($message) {
            $log_file = WP_CONTENT_DIR . '/plugins/primates-shoppers/logs/error_log.txt';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    ps_log_error("=== DEBUG CACHE TABLE QUERY START ===");
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        ps_log_error("ERROR: Cache table '$table_name' does not exist!");
        return;
    }
    
    ps_log_error("Table exists: $table_name");
    
    // Get recent records
    $recent_records = $wpdb->get_results("SELECT id, user_id, query_hash, created_at FROM $table_name ORDER BY id DESC LIMIT 10");
    
    ps_log_error("Total records found: " . count($recent_records));
    
    if (count($recent_records) > 0) {
        ps_log_error("Recent records:");
        foreach ($recent_records as $record) {
            $user_id_display = $record->user_id === null ? 'NULL' : $record->user_id;
            $hash_short = substr($record->query_hash, 0, 16) . '...';
            ps_log_error("  ID: {$record->id}, User ID: '{$user_id_display}', Hash: {$hash_short}, Created: {$record->created_at}");
        }
        
        // Check for problematic user_ids
        $zero_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = '0' OR user_id = 0");
        $null_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id IS NULL");
        $empty_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = ''");
        
        ps_log_error("USER_ID ANALYSIS:");
        ps_log_error("  Records with user_id = '0' or 0: $zero_count");
        ps_log_error("  Records with user_id IS NULL: $null_count");
        ps_log_error("  Records with user_id = '': $empty_count");
        
        // Show unique user_ids
        $unique_users = $wpdb->get_results("SELECT DISTINCT user_id, COUNT(*) as count FROM $table_name GROUP BY user_id ORDER BY count DESC");
        
        ps_log_error("UNIQUE USER_IDS:");
        foreach ($unique_users as $user) {
            $user_id_display = $user->user_id === null ? 'NULL' : $user->user_id;
            ps_log_error("  User ID: '{$user_id_display}' - Count: {$user->count}");
        }
        
        // Test current user identifier if function exists
        if (function_exists('ps_get_user_identifier')) {
            $current_user_id = ps_get_user_identifier();
            ps_log_error("CURRENT USER IDENTIFIER TEST:");
            ps_log_error("  Current user identifier: '{$current_user_id}'");
            
            if (is_user_logged_in()) {
                $wp_user_id = get_current_user_id();
                ps_log_error("  WordPress user logged in with ID: $wp_user_id");
            } else {
                ps_log_error("  No WordPress user logged in");
            }
        }
    } else {
        ps_log_error("No records found in cache table.");
    }
    
    ps_log_error("=== DEBUG CACHE TABLE QUERY END ===");
}

// If this file is being included in WordPress context, run the debug function
if (function_exists('add_action')) {
    // Add a temporary admin action to trigger this debug
    add_action('wp_loaded', function() {
        if (is_admin() && current_user_can('manage_options') && isset($_GET['debug_cache_table'])) {
            debug_cache_table_contents();
            wp_die('Debug cache table query completed. Check the error log.');
        }
    });
}

// If running directly, output instructions
if (!function_exists('add_action')) {
    echo "To use this debug script:\n";
    echo "1. Copy the debug_cache_table_contents() function to your theme's functions.php\n";
    echo "2. Call debug_cache_table_contents() from WordPress\n";
    echo "3. Or visit: your-site.com/wp-admin/?debug_cache_table=1\n";
    echo "4. Check the error log for results\n";
}
?> 