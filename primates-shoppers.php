<?php
/**
 * Plugin Name: Primates Shoppers
 * Description: Search Amazon products with filtering by keywords and sorting by price or price per unit.
 * Version: 1.0.0
 * Author: Primates Shopper
 * Text Domain: primates-shoppers
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PS_VERSION', '1.0.0');
define('PS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PS_AFFILIATE_ID', 'primatessho0c-20');

// Include required files
require_once PS_PLUGIN_DIR . 'includes/amazon-api.php';
require_once PS_PLUGIN_DIR . 'includes/settings.php';

// Ensure the table exists with the correct structure
ps_create_cache_table();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'ps_activate');
register_deactivation_hook(__FILE__, 'ps_deactivate');

/**
 * Force database update
 * This can be triggered by an admin to recreate the cache table
 */
function ps_force_update_db() {
    // Only allow admins to trigger this
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Check if we have a valid nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ps_force_update_db')) {
        wp_die('Security check failed.');
    }
    
    // Check for full rebuild flag
    $force_rebuild = isset($_GET['force_rebuild']) && $_GET['force_rebuild'] === '1';
    
    if ($force_rebuild) {
        // If forced, do a complete rebuild
        ps_log_error("Admin requested force rebuild of cache table");
        ps_force_rebuild_table();
    } else {
        // First try to update normally
        ps_create_cache_table();
        
        // Check if column exists after update attempt
        global $wpdb;
        $table_name = $wpdb->prefix . 'ps_cache';
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        
        if ($columns) {
            $column_names = array_map(function($col) { return $col->Field; }, $columns);
            
            // If expires_at still doesn't exist, advise admin to use force rebuild
            if (!in_array('expires_at', $column_names)) {
                ps_log_error("Normal table update failed to add expires_at column");
                // Redirect to page with error and force rebuild option
                wp_redirect(admin_url('options-general.php?page=primates-shoppers&update_failed=1'));
                exit;
            }
        } else {
            // Table doesn't exist, create it
            ps_log_error("Cache table doesn't exist, creating it");
            ps_force_rebuild_table();
        }
    }
    
    // Redirect back to the settings page
    wp_redirect(admin_url('options-general.php?page=primates-shoppers&updated=1'));
    exit;
}

/**
 * Add admin handler for database update
 */
add_action('admin_init', function() {
    if (isset($_GET['action']) && $_GET['action'] === 'ps_force_update_db') {
        ps_force_update_db();
    }
});

/**
 * Plugin activation function
 */
function ps_activate() {
    // Create default settings
    add_option('ps_settings', array(
        'amazon_associate_tag' => PS_AFFILIATE_ID,
        'cache_duration' => 3600, // 1 hour
    ));
    
    // Create cache table
    ps_create_cache_table();
    
    // Create logs directory
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
}

/**
 * Plugin deactivation function
 */
function ps_deactivate() {
    // Cleanup if needed
}

/**
 * Create cache table for storing search results
 */
function ps_create_cache_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ps_cache';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Use static variable to track if we've already tried this session
    static $attempted = false;
    if ($attempted) {
        return; // Skip repeat attempts in the same page load
    }
    $attempted = true;
    
    // Only log the action on admin pages to reduce log spam
    if (is_admin()) {
        ps_log_error("Checking cache table structure");
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        query_hash varchar(32) NOT NULL,
        query_data text NOT NULL,
        results longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        expires_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY query_hash (query_hash),
        KEY expires_at (expires_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    
    // Only attempt to alter table on admin pages or activation
    if (!is_admin() && !defined('WP_INSTALLING')) {
        return;
    }
    
    // Check for debug flag to reduce log spam
    $debug = isset($_GET['ps_debug']) && current_user_can('manage_options');
    
    // Check if the table exists and column structure
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    if ($columns) {
        $column_names = array_map(function($col) { return $col->Field; }, $columns);
        
        if ($debug) {
            ps_log_error("Current table columns: " . implode(', ', $column_names));
        }
        
        if (!in_array('expires_at', $column_names)) {
            ps_log_error("expires_at column not found, adding it directly");
            
            $alter_result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN expires_at datetime DEFAULT NULL");
            if ($alter_result === false) {
                ps_log_error("Error adding expires_at column: " . $wpdb->last_error);
            } else {
                ps_log_error("Successfully added expires_at column");
                
                $index_result = $wpdb->query("ALTER TABLE $table_name ADD INDEX expires_at (expires_at)");
                if ($index_result === false) {
                    ps_log_error("Error adding expires_at index: " . $wpdb->last_error);
                } else {
                    ps_log_error("Successfully added expires_at index");
                }
            }
        } else if ($debug) {
            ps_log_error("expires_at column already exists");
        }
    } else if ($debug) {
        ps_log_error("Error checking table columns: " . $wpdb->last_error);
    }
}

/**
 * Force rebuild the cache table (DROPS the existing table)
 * Use this as a last resort when other methods fail
 */
function ps_force_rebuild_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ps_cache';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Log the action
    ps_log_error("Force rebuilding cache table (dropping existing table)");
    
    // Drop the table first
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    // Create fresh table with correct schema
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        query_hash varchar(32) NOT NULL,
        query_data text NOT NULL,
        results longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        expires_at datetime DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY query_hash (query_hash),
        KEY expires_at (expires_at)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Verify the table exists and has the right structure
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    if ($columns) {
        $column_names = array_map(function($col) { return $col->Field; }, $columns);
        ps_log_error("Table rebuilt. Columns: " . implode(', ', $column_names));
        return true;
    } else {
        ps_log_error("Failed to rebuild table: " . $wpdb->last_error);
        return false;
    }
}

/**
 * Fix database errors by forcing a table rebuild
 * Can be called from admin or automatically when errors are detected
 */
function ps_fix_database() {
    ps_log_error("Running database fix function");
    return ps_force_rebuild_table();
}

/**
 * Enqueue scripts and styles
 */
function ps_enqueue_scripts() {
    // Add timestamp to version to break cache
    $cache_buster = PS_VERSION . '.' . time();
    
    wp_enqueue_style('ps-styles', PS_PLUGIN_URL . 'assets/css/style.css', array(), $cache_buster);
    wp_enqueue_script('ps-search', PS_PLUGIN_URL . 'assets/js/search.js', array('jquery'), $cache_buster, true);
    
    // Pass variables to script
    wp_localize_script('ps-search', 'psData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ps-search-nonce')
    ));

    // Enqueue and localize the simple AJAX test script
    wp_enqueue_script('ps-simple-test-script', PS_PLUGIN_URL . 'assets/js/simple-ajax-test.js', array('jquery'), PS_VERSION, true);
    wp_localize_script('ps-simple-test-script', 'psSimpleTestData', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('ps-simple-test-nonce'),
        'test_action' => 'ps_simple_ajax_test'
    ));
}
add_action('wp_enqueue_scripts', 'ps_enqueue_scripts');

/**
 * Register shortcode for the search form
 */
function ps_search_shortcode($atts) {
    // Buffer output
    ob_start();
    include PS_PLUGIN_DIR . 'templates/search-form.php';
    return ob_get_clean();
}
add_shortcode('primates_shoppers', 'ps_search_shortcode');

/**
 * Register shortcode for the simple AJAX test trigger button
 */
function ps_simple_ajax_trigger_shortcode() {
    // Buffer output
    ob_start();
    echo '<button id="ps-simple-ajax-test-button" class="button button-primary">Run Simple AJAX Test</button>';
    echo '<div id="ps-simple-ajax-test-results" style="margin-top: 10px; padding: 10px; border: 1px solid #ccc;">Test results will appear here.</div>';
    return ob_get_clean();
}
add_shortcode('primates_simple_ajax_trigger', 'ps_simple_ajax_trigger_shortcode');

/**
 * Handle AJAX search request
 */
function ps_ajax_search() {
    // Set time limit to prevent timeouts
    set_time_limit(60);
    
    // Verify nonce
    check_ajax_referer('ps-search-nonce', 'nonce');

    // Start timing the request
    $start_time = microtime(true);
    
    // Set error handler to catch any fatal errors
    register_shutdown_function('ps_handle_shutdown');
    
    $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    $exclude_keywords = isset($_POST['exclude']) ? sanitize_text_field($_POST['exclude']) : '';
    $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'price';
    
    // Log the request
    ps_log_error("AJAX Search Request - Query: '{$search_query}', Exclude: '{$exclude_keywords}', Sort: '{$sort_by}'");
    
    try {
        // Set a smaller output buffer to prevent large responses
        if (ob_get_level()) ob_end_clean();
        ob_start();
    
    // Check cache first
    $cached_results = ps_get_cached_results($search_query, $exclude_keywords, $sort_by);
    
    if ($cached_results !== false) {
            ps_log_error("Cache hit for query '{$search_query}' - Found cached results");
            
            // Prepare minimal response
            $processed_file_info = isset($cached_results['data_source']) ? $cached_results['data_source'] : (isset($cached_results['debug_file']) ? 'Error: ' . $cached_results['debug_file'] : 'N/A');
            $minimal_response = array(
                'success' => isset($cached_results['success']) ? $cached_results['success'] : true,
                'message' => isset($cached_results['message']) ? $cached_results['message'] : '',
                'items' => array(),
                'count' => 0,
                'source' => 'cache',
                'timing' => round((microtime(true) - $start_time) * 1000) . 'ms',
                'processed_file' => $processed_file_info
            );
            
            // If the cached result has products, add up to 10
            if (isset($cached_results['items']) && is_array($cached_results['items'])) {
                $minimal_response['items'] = array_slice($cached_results['items'], 0, 10);
                $minimal_response['count'] = count($minimal_response['items']);
                
                if (count($cached_results['items']) > 10) {
                    $minimal_response['truncated'] = true;
                    ps_log_error("Truncated cached results to 10 items to reduce response size");
                }
            }
            
            // Return the minimal response
            ps_send_ajax_response($minimal_response);
        return;
    }
        
        ps_log_error("Cache miss for query '{$search_query}' - Fetching fresh results");
    
    // Get search results from Amazon scraper
    $results = ps_search_amazon_products($search_query, $exclude_keywords, $sort_by);
        $processed_file_info = isset($results['data_source']) ? $results['data_source'] : (isset($results['debug_file']) ? 'Error: ' . $results['debug_file'] : 'Live Fetch (No File)');
        
        // Check if parsing was successful
        if (!isset($results['success']) || $results['success'] !== true) {
            // If there was an error, log it
            ps_log_error("Error searching Amazon: " . (isset($results['message']) ? $results['message'] : 'Unknown error'));
            
            // Create a minimal error response
            $error_response = array(
                'success' => false,
                'message' => isset($results['message']) ? $results['message'] : 'Error retrieving products. Please try again later.',
                'items' => array(),
                'count' => 0,
                'timing' => round((microtime(true) - $start_time) * 1000) . 'ms',
                'error_type' => isset($results['error_type']) ? $results['error_type'] : 'unknown_error',
                'processed_file' => $processed_file_info
            );
            
            // Cache the error too, but for a shorter time (5 minutes)
            if (isset($results['error_type']) && $results['error_type'] !== 'no_response_files') {
                // Only cache certain types of errors, not missing response files
                $error_response['cached_error'] = true;
                ps_cache_results($search_query, $exclude_keywords, $sort_by, $error_response);
                ps_log_error("Cached error result for 5 minutes to prevent repeated failures");
            }
            
            // Send the minimal error response
            ps_send_ajax_response($error_response);
            return;
        }
        
        // Prepare minimal successful response
        $success_response = array(
            'success' => true,
            'items' => array(),
            'count' => 0,
            'timing' => round((microtime(true) - $start_time) * 1000) . 'ms',
            'processed_file' => $processed_file_info
        );
        
        // Add products if available (up to 10)
        if (isset($results['items']) && is_array($results['items'])) {
            // Debug info about the first item structure
            if (!empty($results['items'])) {
                $first_item = $results['items'][0];
                $keys = array_keys($first_item);
                ps_log_error("First product structure: " . implode(', ', $keys));
            }
            
            $success_response['items'] = $results['items']; // Use all items, don't limit
            $success_response['count'] = count($results['items']);
        }
    
    // Cache the results
        ps_cache_results($search_query, $exclude_keywords, $sort_by, $success_response);
        ps_log_error("Successfully cached " . $success_response['count'] . " products for query '{$search_query}'");
        
        // Send the minimal successful response
        ps_send_ajax_response($success_response);
        
    } catch (Exception $e) {
        // Catch any unexpected errors
        ps_log_error("Critical AJAX error: " . $e->getMessage());
        
        // Send back absolute minimal response
        ps_send_ajax_response(array(
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again later.',
            'items' => array(),
            'count' => 0,
            'processed_file' => 'N/A'
        ));
    }
}
add_action('wp_ajax_ps_search', 'ps_ajax_search');
add_action('wp_ajax_nopriv_ps_search', 'ps_ajax_search');

/**
 * Safely send AJAX response with proper headers and error handling
 */
function ps_send_ajax_response($data) {
    // Clean output buffer
    if (ob_get_level()) ob_end_clean();
    
    // Set headers to prevent caching
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    
    // Limit response size
    $json_response = json_encode($data);
    $response_size = strlen($json_response);
    
    // Log the response size
    ps_log_error("Sending AJAX response: " . round($response_size / 1024, 2) . " KB");
    
    // Check if the response is too large (> 500KB)
    if ($response_size > 500000) {
        ps_log_error("Response too large (" . round($response_size / 1024, 2) . " KB), reducing");
        
        // Create a minimal response
        $minimal = array(
            'success' => isset($data['success']) ? $data['success'] : false,
            'message' => 'Response too large, data truncated.',
            'items' => array(),
            'count' => 0,
            'error_type' => 'response_too_large',
            'processed_file' => 'N/A'
        );
        
        // If we have items, keep only a few essential ones
        if (isset($data['items']) && is_array($data['items'])) {
            $minimal['items'] = array_slice($data['items'], 0, 3);
            $minimal['count'] = count($minimal['items']);
            $minimal['truncated'] = true;
        }
        
        echo json_encode($minimal);
    } else {
        // Normal response
        echo $json_response;
    }
    
    // Ensure all output is sent
    wp_die();
}

/**
 * Handle fatal errors during AJAX request
 */
function ps_handle_shutdown() {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR))) {
        ps_log_error("Fatal error during AJAX request: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        // Clean any output
        if (ob_get_level()) ob_end_clean();
        
        // Send error response
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'success' => false,
            'message' => 'A server error occurred. Please try again later.',
            'items' => array(),
            'count' => 0,
            'error_type' => 'server_error',
            'processed_file' => 'N/A'
        ));
        exit;
    }
}

/**
 * Handle Simple AJAX Test Request
 */
function ps_handle_simple_ajax_test() {
    // Verify nonce
    check_ajax_referer('ps-simple-test-nonce', 'nonce');

    $payload = isset($_POST['test_payload']) ? sanitize_text_field($_POST['test_payload']) : 'No payload received';

    // Process the request and send a JSON response
    wp_send_json_success(array(
        'message'   => 'Simple AJAX Test Successful!',
        'timestamp' => current_time('mysql'),
        'received_payload' => $payload,
        'action_triggered' => isset($_POST['action']) ? sanitize_text_field($_POST['action']) : 'N/A'
    ));
}
add_action('wp_ajax_ps_simple_ajax_test', 'ps_handle_simple_ajax_test');
add_action('wp_ajax_nopriv_ps_simple_ajax_test', 'ps_handle_simple_ajax_test'); // Allow for non-logged-in users too for testing

/**
 * Handle AJAX test connection request
 */
function ps_ajax_test_connection() {
    // Verify nonce
    check_ajax_referer('ps_test_connection', 'nonce');
    
    // Only allow admins to run this test
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }
    
    // Test URL - Amazon homepage is less likely to trigger blocking
    $test_url = 'https://www.amazon.com/';
    
    // Fetch the page
    $response = ps_fetch_amazon_page($test_url);
    
    if ($response === false) {
        wp_send_json_error(array('message' => 'Failed to connect to Amazon. There may be a network issue.'));
        return;
    }
    
    // Check if Amazon is blocking
    if (ps_is_amazon_blocking($response)) {
        wp_send_json_error(array('message' => 'Amazon is currently blocking requests from your server. This is likely due to excessive scraping.'));
        return;
    }
    
    // Check if the response contains expected content
    if (strpos($response, 'amazon') === false) {
        wp_send_json_error(array('message' => 'Received unexpected response from Amazon. The response does not appear to be from Amazon.'));
        return;
    }
    
    wp_send_json_success(array('message' => 'Connection to Amazon is working properly.'));
}
add_action('wp_ajax_ps_test_connection', 'ps_ajax_test_connection');

/**
 * Handle AJAX test DNS request
 */
function ps_ajax_test_dns() {
    // Verify nonce
    check_ajax_referer('ps_test_dns', 'nonce');
    
    // Only allow admins to run this test
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }
    
    // Try to resolve Amazon's domain
    $ip = gethostbyname('www.amazon.com');
    
    // Check if resolution failed (gethostbyname returns the hostname on failure)
    if ($ip === 'www.amazon.com') {
        wp_send_json_error(array('message' => 'Failed to resolve Amazon\'s domain name. There may be a DNS issue.'));
        return;
    }
    
    wp_send_json_success(array('message' => 'DNS resolution successful.', 'ip' => $ip));
}
add_action('wp_ajax_ps_test_dns', 'ps_ajax_test_dns');

/**
 * Get cached search results
 */
function ps_get_cached_results($query, $exclude, $sort_by) {
    global $wpdb;
    
    // Get settings
    $settings = get_option('ps_settings');
    $cache_duration = isset($settings['cache_duration']) ? $settings['cache_duration'] : 3600;
    
    // Create a unique hash for this search query
    $query_hash = md5($query . '|' . $exclude . '|' . $sort_by);
    
    // Table name
    $table_name = $wpdb->prefix . 'ps_cache';
    
    // Query using expires_at column
    $cached_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE query_hash = %s 
            AND (
                (expires_at IS NOT NULL AND expires_at > NOW())
                OR 
                (expires_at IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND))
            )
            ORDER BY created_at DESC 
            LIMIT 1",
            $query_hash,
            $cache_duration
        )
    );
    
    if ($cached_data) {
        // Track cache hits in logs
        $age_seconds = time() - strtotime($cached_data->created_at);
        $age_minutes = round($age_seconds / 60);
        ps_log_error("Cache hit for '{$query}' (age: {$age_minutes} mins)" . 
                   (isset($cached_data->expires_at) ? " with explicit expiry" : ""));
        
        // Parse the JSON data
        $results = json_decode($cached_data->results, true);
        
        // Check if this is a cached error response
        if (isset($results['success']) && $results['success'] === false) {
            ps_log_error("Retrieved cached error response: " . (isset($results['message']) ? $results['message'] : 'Unknown error'));
        }
        
        return $results;
    }
    
    return false;
}

/**
 * Cache search results
 */
function ps_cache_results($query, $exclude, $sort_by, $results) {
    global $wpdb;
    
    // Create a unique hash for this search query
    $query_hash = md5($query . '|' . $exclude . '|' . $sort_by);
    
    // Store query data for debugging
    $query_data = json_encode(array(
        'query' => $query,
        'exclude' => $exclude,
        'sort_by' => $sort_by
    ));
    
    // Clean up the results to prevent storing large objects
    $clean_results = $results;
    
    // Remove potentially large fields
    unset($clean_results['debug_post_data']);
    unset($clean_results['html_response']);
    unset($clean_results['debug_file']);
    unset($clean_results['raw_html']);
    unset($clean_results['trace']);
    
    // Ensure descriptions are limited in size
    if (isset($clean_results['items']) && is_array($clean_results['items'])) {
        foreach ($clean_results['items'] as $key => $item) {
            if (isset($item['description']) && strlen($item['description']) > 200) {
                $clean_results['items'][$key]['description'] = substr($item['description'], 0, 200) . '...';
            }
        }
    }
    
    // Convert to JSON and check size
    $json_results = json_encode($clean_results);
    $size_kb = strlen($json_results) / 1024;
    
    // Log the size for monitoring
    ps_log_error("Caching results: {$size_kb} KB");
    
    // If the results are too large, truncate them further
    if ($size_kb > 100) { // More than 100KB is too large
        ps_log_error("Cache data too large ({$size_kb} KB), truncating further");
        
        // If we have items, keep only essential info for first 5 items
        if (isset($clean_results['items']) && is_array($clean_results['items'])) {
            $minimal_items = array();
            $count = 0;
            
            foreach (array_slice($clean_results['items'], 0, 5) as $item) {
                $minimal_items[] = array(
                    'title' => isset($item['title']) ? $item['title'] : '',
                    'link' => isset($item['link']) ? $item['link'] : '',
                    'price' => isset($item['price']) ? $item['price'] : '',
                    'price_value' => isset($item['price_value']) ? $item['price_value'] : 0,
                );
                $count++;
            }
            
            $clean_results['items'] = $minimal_items;
            $clean_results['count'] = $count;
            $clean_results['truncated_cache'] = true;
        }
        
        // Re-encode and check size again
        $json_results = json_encode($clean_results);
        $new_size_kb = strlen($json_results) / 1024;
        ps_log_error("Truncated cache size: {$new_size_kb} KB");
    }
    
    // Determine cache duration
    $short_cache = isset($clean_results['short_cache']) && $clean_results['short_cache'] === true;
    $cache_expiry = $short_cache ? date('Y-m-d H:i:s', strtotime('+5 minutes')) : null;
    
    // Table name
    $table_name = $wpdb->prefix . 'ps_cache';
    
    // Store the results with the expires_at column
    $insert_result = $wpdb->insert(
        $table_name,
        array(
            'query_hash' => $query_hash,
            'query_data' => $query_data,
            'results' => $json_results,
            'created_at' => current_time('mysql'),
            'expires_at' => $cache_expiry
        )
    );
    
    if ($wpdb->last_error) {
        ps_log_error("Database error when caching results: " . $wpdb->last_error);
    } else {
        ps_log_error("Successfully cached results with hash: " . $query_hash);
    }
}

/**
 * Add admin menu
 */
function ps_admin_menu() {
    add_options_page(
        'Primates Shoppers Settings',
        'Primates Shoppers',
        'manage_options',
        'primates-shoppers',
        'ps_settings_page'
    );
}
add_action('admin_menu', 'ps_admin_menu');

/**
 * Handle admin actions
 */
function ps_handle_admin_actions() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'primates-shoppers') {
        return;
    }
    
    if (isset($_GET['action']) && isset($_GET['_wpnonce'])) {
        $action = $_GET['action'];
        $nonce = $_GET['_wpnonce'];
        
        if ($action === 'view_error_log' && wp_verify_nonce($nonce, 'ps_view_log')) {
            ps_display_error_log();
            exit;
        } elseif ($action === 'view_samples' && wp_verify_nonce($nonce, 'ps_view_samples')) {
            ps_display_response_samples();
            exit;
        }
    }
}
add_action('admin_init', 'ps_handle_admin_actions');

/**
 * Display error log
 */
function ps_display_error_log() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    $error_log_file = PS_PLUGIN_DIR . 'logs/error_log.txt';
    
    if (!file_exists($error_log_file)) {
        wp_die('Error log file not found.');
    }
    
    $log_content = file_get_contents($error_log_file);
    
    echo '<div style="padding: 20px;">';
    echo '<h1>Error Log</h1>';
    echo '<a href="' . admin_url('options-general.php?page=primates-shoppers') . '">&laquo; Back to Settings</a>';
    echo '<div style="background: #f5f5f5; padding: 15px; margin-top: 15px; border: 1px solid #ddd; overflow: auto; max-height: 600px;">';
    echo '<pre style="margin: 0; white-space: pre-wrap;">' . esc_html($log_content) . '</pre>';
    echo '</div>';
    echo '</div>';
}

/**
 * Display response samples
 */
function ps_display_response_samples() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    $samples_dir = PS_PLUGIN_DIR . 'logs';
    $samples = glob($samples_dir . '/amazon_response_*.html');
    
    if (empty($samples)) {
        wp_die('No response samples found.');
    }
    
    // Sort by modification time (newest first)
    usort($samples, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    echo '<div style="padding: 20px;">';
    echo '<h1>Amazon Response Samples</h1>';
    echo '<a href="' . admin_url('options-general.php?page=primates-shoppers') . '">&laquo; Back to Settings</a>';
    
    foreach ($samples as $index => $sample_file) {
        $filename = basename($sample_file);
        $modified_time = filemtime($sample_file);
        $sample_content = file_get_contents($sample_file);
        
        // Check if the sample contains blocking indicators
        $is_blocked = false;
        $blocking_indicators = array(
            'robot check',
            'captcha',
            'verify you\'re a human',
            'automated access',
            'unusual activity',
            'sorry, we just need to make sure you\'re not a robot',
            'to discuss automated access to amazon data please contact'
        );
        
        $sample_lower = strtolower($sample_content);
        foreach ($blocking_indicators as $indicator) {
            if (strpos($sample_lower, $indicator) !== false) {
                $is_blocked = true;
                break;
            }
        }
        
        echo '<div style="margin-top: 20px; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">';
        echo '<div style="padding: 10px; background: ' . ($is_blocked ? '#f8d7da' : '#d4edda') . '; border-bottom: 1px solid #ddd;">';
        echo '<strong>' . esc_html($filename) . '</strong> - ' . date('Y-m-d H:i:s', $modified_time);
        if ($is_blocked) {
            echo ' <span style="color: #721c24; font-weight: bold;">(BLOCKED)</span>';
        } else {
            echo ' <span style="color: #155724; font-weight: bold;">(OK)</span>';
        }
        echo '</div>';
        echo '<div style="padding: 15px; background: #f5f5f5; overflow: auto; max-height: 300px;">';
        echo '<pre style="margin: 0; white-space: pre-wrap;">' . esc_html($sample_content) . '</pre>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '</div>';
}

/**
 * Add scheduled event to clean up old cache entries
 */
function ps_schedule_cache_cleanup() {
    if (!wp_next_scheduled('ps_cache_cleanup')) {
        wp_schedule_event(time(), 'daily', 'ps_cache_cleanup');
    }
}
add_action('wp', 'ps_schedule_cache_cleanup');

/**
 * Clean up old cache entries
 */
function ps_cleanup_cache() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ps_cache';
    
    // Get settings
    $settings = get_option('ps_settings');
    $cache_duration = isset($settings['cache_duration']) ? $settings['cache_duration'] : 3600;
    
    // Delete expired entries first
    $deleted_explicit = $wpdb->query(
        "DELETE FROM $table_name 
        WHERE expires_at IS NOT NULL AND expires_at < NOW()"
    );
    
    // Then delete old entries based on the standard cache duration
    $deleted_standard = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name 
            WHERE expires_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $cache_duration * 2 // Use double the cache duration for cleanup to avoid edge cases
        )
    );
    
    // Log the cleanup activity
    ps_log_error("Cache cleanup: Removed {$deleted_explicit} explicitly expired entries and {$deleted_standard} standard expired entries");
    
    // Always delete any really old entries (safety cleanup)
    $deleted_old = $wpdb->query(
        "DELETE FROM $table_name 
        WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
    );
    
    if ($deleted_old > 0) {
        ps_log_error("Cache cleanup: Removed {$deleted_old} entries older than 7 days");
    }
}
add_action('ps_cache_cleanup', 'ps_cleanup_cache');

/**
 * Clear cache AJAX handler
 */
function ps_ajax_clear_cache() {
    // Verify nonce
    check_ajax_referer('ps_clear_cache', 'nonce');
    
    // Only allow admins to clear cache
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'ps_cache';
    
    // Delete all cache entries
    $deleted = $wpdb->query("DELETE FROM {$table_name}");
    
    if ($deleted === false) {
        wp_send_json_error(array('message' => 'Failed to clear cache. Error: ' . $wpdb->last_error));
    } else {
        ps_log_error("Cache cleared manually. Deleted {$deleted} entries.");
        wp_send_json_success(array('message' => "Cache cleared successfully. Deleted {$deleted} entries."));
    }
}
add_action('wp_ajax_ps_clear_cache', 'ps_ajax_clear_cache');