<?php
// Simple database check script
// This will be run from WordPress admin area

// Add this as a WordPress admin page or run via AJAX

function check_cache_table_contents() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ps_cache';
    
    echo "<h3>Cache Table Analysis</h3>";
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        echo "<p style='color: red;'>ERROR: Cache table '$table_name' does not exist!</p>";
        return;
    }
    
    echo "<p style='color: green;'>Table exists: $table_name</p>";
    
    // Get total count
    $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p>Total records: $total_count</p>";
    
    if ($total_count > 0) {
        // Get recent records
        $recent_records = $wpdb->get_results("SELECT id, user_id, query_hash, created_at FROM $table_name ORDER BY created_at DESC LIMIT 10");
        
        echo "<h4>Recent Records:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Query Hash</th><th>Created At</th></tr>";
        
        foreach ($recent_records as $record) {
            $user_id_display = $record->user_id === null ? 'NULL' : "'" . $record->user_id . "'";
            echo "<tr>";
            echo "<td>{$record->id}</td>";
            echo "<td>{$user_id_display}</td>";
            echo "<td>{$record->query_hash}</td>";
            echo "<td>{$record->created_at}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for problematic user_ids
        $zero_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = '0' OR user_id = 0");
        $null_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id IS NULL");
        $empty_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = ''");
        
        echo "<h4>User ID Analysis:</h4>";
        echo "<p>Records with user_id = '0' or 0: <strong>$zero_count</strong></p>";
        echo "<p>Records with user_id IS NULL: <strong>$null_count</strong></p>";
        echo "<p>Records with user_id = '': <strong>$empty_count</strong></p>";
        
        // Get unique user_ids
        $unique_users = $wpdb->get_results("SELECT DISTINCT user_id, COUNT(*) as count FROM $table_name GROUP BY user_id ORDER BY count DESC LIMIT 10");
        
        echo "<h4>Top User IDs:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>User ID</th><th>Record Count</th></tr>";
        
        foreach ($unique_users as $user) {
            $user_id_display = $user->user_id === null ? 'NULL' : "'" . $user->user_id . "'";
            echo "<tr>";
            echo "<td>{$user_id_display}</td>";
            echo "<td>{$user->count}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

// If this is being called directly via WordPress admin
if (isset($_GET['action']) && $_GET['action'] === 'check_cache_table' && current_user_can('manage_options')) {
    check_cache_table_contents();
    exit;
}
?> 