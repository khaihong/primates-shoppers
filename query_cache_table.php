<?php
/**
 * Query Cache Table Script
 * This script queries the cache table to show user_id values
 */

// Try to find WordPress installation
$wp_paths = [
    '/var/www/html/wp-load.php',
    '../../../wp-load.php',
    '../../../../wp-load.php',
    '/volume1/docker/primates/wp-load.php'
];

$wp_loaded = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        echo "WordPress loaded from: $path\n";
        break;
    }
}

if (!$wp_loaded) {
    echo "Could not find WordPress installation. Trying direct database connection...\n";
    
    // Try direct database connection using compose.yaml settings
    $host = 'localhost';
    $port = 3338;
    $dbname = 'wp';
    $username = 'Khai';
    $password = 'Newpass!239';
    
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "Connected to database directly via PDO\n";
        
        // Query the cache table
        $stmt = $pdo->query("SELECT id, user_id, query_hash, created_at FROM wp_ps_cache ORDER BY id DESC LIMIT 10");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\n=== CACHE TABLE QUERY RESULTS ===\n";
        echo "Total records found: " . count($results) . "\n\n";
        
        if (count($results) > 0) {
            printf("%-4s %-15s %-20s %-20s\n", "ID", "USER_ID", "QUERY_HASH", "CREATED_AT");
            echo str_repeat("-", 70) . "\n";
            
            foreach ($results as $row) {
                $user_id_display = $row['user_id'] === null ? 'NULL' : "'" . $row['user_id'] . "'";
                $hash_short = substr($row['query_hash'], 0, 16) . '...';
                printf("%-4s %-15s %-20s %-20s\n", 
                    $row['id'], 
                    $user_id_display, 
                    $hash_short, 
                    $row['created_at']
                );
            }
            
            // Check for problematic user_ids
            echo "\n=== USER_ID ANALYSIS ===\n";
            
            $zero_count = $pdo->query("SELECT COUNT(*) FROM wp_ps_cache WHERE user_id = '0' OR user_id = 0")->fetchColumn();
            $null_count = $pdo->query("SELECT COUNT(*) FROM wp_ps_cache WHERE user_id IS NULL")->fetchColumn();
            $empty_count = $pdo->query("SELECT COUNT(*) FROM wp_ps_cache WHERE user_id = ''")->fetchColumn();
            
            echo "Records with user_id = '0' or 0: $zero_count\n";
            echo "Records with user_id IS NULL: $null_count\n";
            echo "Records with user_id = '': $empty_count\n";
            
            // Show unique user_ids
            echo "\n=== UNIQUE USER_IDS ===\n";
            $unique_stmt = $pdo->query("SELECT DISTINCT user_id, COUNT(*) as count FROM wp_ps_cache GROUP BY user_id ORDER BY count DESC");
            $unique_results = $unique_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($unique_results as $row) {
                $user_id_display = $row['user_id'] === null ? 'NULL' : "'" . $row['user_id'] . "'";
                echo "User ID: $user_id_display - Count: " . $row['count'] . "\n";
            }
        } else {
            echo "No records found in cache table.\n";
        }
        
    } catch (PDOException $e) {
        echo "Database connection failed: " . $e->getMessage() . "\n";
    }
    
    exit;
}

// If WordPress is loaded, use WordPress database functions
global $wpdb;
$table_name = $wpdb->prefix . 'ps_cache';

echo "\n=== QUERYING CACHE TABLE VIA WORDPRESS ===\n";
echo "Table name: $table_name\n";

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
if (!$table_exists) {
    echo "ERROR: Cache table '$table_name' does not exist!\n";
    exit;
}

// Get recent records
$recent_records = $wpdb->get_results("SELECT id, user_id, query_hash, created_at FROM $table_name ORDER BY id DESC LIMIT 10");

echo "Total records found: " . count($recent_records) . "\n\n";

if (count($recent_records) > 0) {
    printf("%-4s %-15s %-20s %-20s\n", "ID", "USER_ID", "QUERY_HASH", "CREATED_AT");
    echo str_repeat("-", 70) . "\n";
    
    foreach ($recent_records as $record) {
        $user_id_display = $record->user_id === null ? 'NULL' : "'" . $record->user_id . "'";
        $hash_short = substr($record->query_hash, 0, 16) . '...';
        printf("%-4s %-15s %-20s %-20s\n", 
            $record->id, 
            $user_id_display, 
            $hash_short, 
            $record->created_at
        );
    }
    
    // Check for problematic user_ids
    echo "\n=== USER_ID ANALYSIS ===\n";
    
    $zero_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = '0' OR user_id = 0");
    $null_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id IS NULL");
    $empty_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE user_id = ''");
    
    echo "Records with user_id = '0' or 0: $zero_count\n";
    echo "Records with user_id IS NULL: $null_count\n";
    echo "Records with user_id = '': $empty_count\n";
    
    // Show unique user_ids
    echo "\n=== UNIQUE USER_IDS ===\n";
    $unique_users = $wpdb->get_results("SELECT DISTINCT user_id, COUNT(*) as count FROM $table_name GROUP BY user_id ORDER BY count DESC");
    
    foreach ($unique_users as $user) {
        $user_id_display = $user->user_id === null ? 'NULL' : "'" . $user->user_id . "'";
        echo "User ID: $user_id_display - Count: " . $user->count . "\n";
    }
    
    // Test current user identifier if function exists
    if (function_exists('ps_get_user_identifier')) {
        echo "\n=== CURRENT USER IDENTIFIER TEST ===\n";
        $current_user_id = ps_get_user_identifier();
        echo "Current user identifier: '" . $current_user_id . "'\n";
        
        if (function_exists('is_user_logged_in') && is_user_logged_in()) {
            $wp_user_id = get_current_user_id();
            echo "WordPress user logged in with ID: $wp_user_id\n";
        } else {
            echo "No WordPress user logged in\n";
        }
    }
} else {
    echo "No records found in cache table.\n";
}
?> 