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
// Define a base for Decodo username if it's not set in wp-config.php or elsewhere
if (!defined('PS_DECODO_USER_BASE')) {
    define('PS_DECODO_USER_BASE', 'user-sptlq8hpk0');
}
if (!defined('PS_DECODO_PASSWORD')) {
    define('PS_DECODO_PASSWORD', 'c=mKkGh1o3lCd3Shm1'); // Example, should be in wp-config.php
}
if (!defined('PS_DECODO_PROXY_HOST')) {
    define('PS_DECODO_PROXY_HOST', 'gate.decodo.com');
}
if (!defined('PS_DECODO_PROXY_PORT')) {
    define('PS_DECODO_PROXY_PORT', 7000);
}

// Include required files
require_once PS_PLUGIN_DIR . 'includes/common-utils.php'; // Common utilities first
require_once PS_PLUGIN_DIR . 'includes/amazon-api.php';
require_once PS_PLUGIN_DIR . 'includes/ebay-api.php'; // Include eBay API functionality
require_once PS_PLUGIN_DIR . 'includes/bestbuy-api.php'; // Include Best Buy API functionality
require_once PS_PLUGIN_DIR . 'includes/walmart-api.php'; // Include Walmart API functionality
require_once PS_PLUGIN_DIR . 'includes/settings.php';
require_once PS_PLUGIN_DIR . 'includes/parsing-test.php'; // Include parsing test functionality
require_once PS_PLUGIN_DIR . 'includes/amazon-proxy-test.php'; // Include Amazon proxy test functionality
require_once PS_PLUGIN_DIR . 'includes/ps-cache-check.php'; // Add our new cache check file
// Ensure the table exists with the correct structure
// ps_create_cache_table(); // Removed direct call

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'ps_activate');
register_deactivation_hook(__FILE__, 'ps_deactivate');

/**
 * Update table structure to add user_id column
 */
function ps_update_table_structure() {
    // Check if user is admin
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Check if we have a valid nonce
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'ps_update_table_structure')) {
        wp_die('Security check failed.');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'ps_cache';
    $message = '';
    
    // Check if the table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if (!$table_exists) {
        // ps_log_error("Cache table doesn't exist, creating it with user_id column");
        ps_force_rebuild_table();
        $message = "Cache table created with user_id column.";
    } else {
        // Check if user_id column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'user_id'");
        
        if (empty($column_exists)) {
            // ps_log_error("Adding user_id column to cache table");
            
            // Add the user_id column
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN user_id varchar(50) DEFAULT NULL");
            
            // Add index on user_id
            $wpdb->query("ALTER TABLE $table_name ADD INDEX user_id (user_id)");
            
            if ($wpdb->last_error) {
                // ps_log_error("Error adding user_id column: " . $wpdb->last_error);
                $message = "Error adding user_id column: " . $wpdb->last_error;
            } else {
                // ps_log_error("Successfully added user_id column");
                $message = "Successfully added user_id column to cache table.";
            }
        } else {
            // ps_log_error("user_id column already exists");
            $message = "The user_id column already exists in the cache table.";
        }
    }
    
    // Redirect back to the settings page with a message
    wp_redirect(add_query_arg('ps_message', urlencode($message), admin_url('options-general.php?page=primates-shoppers')));
    exit;
}

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
        // ps_log_error("Admin requested force rebuild of cache table");
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
                // ps_log_error("Normal table update failed to add expires_at column");
                // Redirect to page with error and force rebuild option
                wp_redirect(admin_url('options-general.php?page=primates-shoppers&update_failed=1'));
                exit;
            }
        } else {
            // Table doesn't exist, create it
            // ps_log_error("Cache table doesn't exist, creating it");
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
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'ps_force_update_db') {
        ps_force_update_db();
        } elseif ($_GET['action'] === 'ps_update_table_structure') {
            ps_update_table_structure();
        }
    }
});

/**
 * Plugin activation function
 */
function ps_activate() {
    // Create default settings
    add_option('ps_settings', array(
        'amazon_associate_tag' => PS_AFFILIATE_ID, // CA tag
        'amazon_associate_tag_us' => 'primatesshopp-20', // US tag
    ));
    
    // Create cache table with updated structure
    ps_create_cache_table(); // Ensure table exists on activation
    ps_force_rebuild_table(); // Use force rebuild to ensure the table has the latest structure
    
    // Create logs directory
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
    
    // Log activation
    // ps_log_error("Plugin activated. Cache table created with user_id column.");
}

/**
 * Plugin deactivation function
 */
function ps_deactivate() {
    // Cleanup if needed
}

/**
 * Ensure the cache table exists when the plugin is loaded.
 * This is a fallback for ps_activate() in case of manual plugin installation or other edge cases.
 */
function ps_ensure_table_exists_on_load() {
    ps_create_cache_table();
}
add_action('plugins_loaded', 'ps_ensure_table_exists_on_load');

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
        // if (is_admin() && $debug) { ps_log_error("Checking cache table structure"); }
    }
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        query_hash varchar(32) NOT NULL,
        query_data text NOT NULL,
        results longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        expires_at datetime DEFAULT NULL,
        user_id varchar(50) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY query_hash (query_hash),
        KEY expires_at (expires_at),
        KEY user_id (user_id)
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
            // if (is_admin() && $debug) { ps_log_error("Current table columns: " . implode(', ', $column_names)); }
        }
        
        if (!in_array('expires_at', $column_names)) {
            // ps_log_error("expires_at column not found, adding it directly");
            
            $alter_result = $wpdb->query("ALTER TABLE $table_name ADD COLUMN expires_at datetime DEFAULT NULL");
            if ($alter_result === false) {
                // ps_log_error("Error adding expires_at column: " . $wpdb->last_error);
            } else {
                // ps_log_error("Successfully added expires_at column");
                
                $index_result = $wpdb->query("ALTER TABLE $table_name ADD INDEX expires_at (expires_at)");
                if ($index_result === false) {
                    // ps_log_error("Error adding expires_at index: " . $wpdb->last_error);
                } else {
                    // ps_log_error("Successfully added expires_at index");
                }
            }
        } else if ($debug) {
            // ps_log_error("expires_at column already exists");
        }
    } else if ($debug) {
        // ps_log_error("Error checking table columns: " . $wpdb->last_error);
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
    // ps_log_error("Force rebuilding cache table (dropping existing table)");
    
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
        user_id varchar(50) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY query_hash (query_hash),
        KEY expires_at (expires_at),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Verify the table exists and has the right structure
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    if ($columns) {
        $column_names = array_map(function($col) { return $col->Field; }, $columns);
        // ps_log_error("Table rebuilt. Columns: " . implode(', ', $column_names));
        return true;
    } else {
        // ps_log_error("Failed to rebuild table: " . $wpdb->last_error);
        return false;
    }
}

/**
 * Fix database errors by forcing a table rebuild
 * Can be called from admin or automatically when errors are detected
 */
function ps_fix_database() {
    // ps_log_error("Running database fix function");
    return ps_force_rebuild_table();
}

/**
 * Enqueue scripts and styles
 */
function ps_enqueue_scripts() {
    $version = '1.0.0.' . time(); // Use timestamp for cache busting during development
    
    // Enqueue the main search script
    wp_enqueue_script(
        'ps-search-js',
        plugins_url('assets/js/search.js', __FILE__),
        array('jquery'),
        $version,
        true
    );
    
    // Enqueue the CSS styles for frontend
    wp_enqueue_style(
        'ps-style',
        plugins_url('assets/css/style.css', __FILE__),
        array(),
        $version
    );
    
    // Pass AJAX URL and nonce to JavaScript
    wp_localize_script(
        'ps-search-js',
        'psData',
        array(
        'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ps-search-nonce'),
            'siteurl' => site_url(),
            'homeurl' => home_url(),
            'debug'   => defined('WP_DEBUG') && WP_DEBUG
        )
    );
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
    
    // Verify nonce - use check_ajax_referer as it's more robust
    check_ajax_referer('ps-search-nonce', 'nonce');

    // Start timing the request
    $start_time = microtime(true);
    
    // Set error handler to catch any fatal errors
    register_shutdown_function('ps_handle_shutdown');
    
    $search_query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    $exclude_keywords = isset($_POST['exclude']) ? sanitize_text_field($_POST['exclude']) : '';
    $sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'price';
    $min_rating = isset($_POST['min_rating']) ? floatval($_POST['min_rating']) : 4.0;
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'us';
    $filter_cached = isset($_POST['filter_cached']) && $_POST['filter_cached'] === 'true';
    $platforms = isset($_POST['platforms']) ? $_POST['platforms'] : array('amazon'); // Handle multiple platforms
    $user_id = ps_get_user_identifier();
    
    // Ensure platforms is an array
    if (!is_array($platforms)) {
        $platforms = array($platforms);
    }
    
    // Sanitize and filter platforms to only supported ones
    $platforms = array_map('sanitize_text_field', $platforms);
    $supported_platforms = array('amazon', 'ebay', 'bestbuy', 'walmart');
    $platforms = array_intersect($platforms, $supported_platforms);
    
    // Ensure at least one platform is selected
    if (empty($platforms)) {
        $platforms = array('amazon');
    }
    
    // Log the request
    $platforms_str = implode(', ', $platforms);
    ps_log_error("AJAX Search Request - User: {$user_id}, Query: '{$search_query}', Exclude: '{$exclude_keywords}', Sort: '{$sort_by}', Min Rating: '{$min_rating}', Country: '{$country}', Platforms: '{$platforms_str}', Filter Cached: " . ($filter_cached ? 'true' : 'false'));
    
    // If this is a page refresh (filter_cached true and query is empty), fetch the most recent cache entry for the user, regardless of query/country
    if ($filter_cached && $search_query === '') {
        // ps_log_error("Page refresh detected for user {$user_id}. Looking up most recent cache entry.");
        $cached_data = ps_get_most_recent_user_cache($user_id, true); // pass true for logging
        if ($cached_data) {
            // ps_log_error("Page refresh: Found cached entry for user {$user_id} with query: '" . ($cached_data['query'] ?? '') . "', country: '" . ($cached_data['country_code'] ?? '') . "'. Returning cached results.");
            wp_send_json_success($cached_data);
            return;
        } else {
            // ps_log_error("Page refresh: No cached entry found for user {$user_id}.");
            // No cached results found, return empty response
            ps_send_ajax_response(array(
                'success' => true,
                'items' => array(),
                'count' => 0,
                'query' => '',
                'exclude' => '',
                'sort_by' => $sort_by,
                'data_source' => 'no_cache'
        ));
        return;
    }
    }
    
    // Otherwise, use the normal cache lookup
    $cached_results = ps_get_cached_results($search_query, $country, $exclude_keywords, $sort_by);
    if ($cached_results) {
        // ps_log_error("Returning cached results");
            wp_send_json_success($cached_results);
            return;
        }
        
    // No cache hit, perform multi-platform search (should not happen on page refresh)
    try {
    $search_response = ps_search_multi_platform_products($search_query, $exclude_keywords, $sort_by, $country, $min_rating, $platforms);
    
    // Measure elapsed time
    $elapsed_time = microtime(true) - $start_time;
    // ps_log_error("Search completed in " . number_format($elapsed_time, 2) . " seconds");
    
        if (is_array($search_response)) {
            // Cache the RAW results if this is a successful full search and has items
            if (isset($search_response['success']) && $search_response['success'] && 
                isset($search_response['raw_items_for_cache']) && !empty($search_response['raw_items_for_cache'])) {
                
                // Prepare data for caching: use the raw items, but associate with the original search query, country, and user.
                // For the base cache of raw results, exclude and sort_by should be empty.
                $data_to_cache = array(
                    'success' => true, // Indicates the overall search was successful at fetching raw data
                    'items' => $search_response['raw_items_for_cache'],
                    'count' => $search_response['raw_items_count_for_cache'],
                    'base_items_count' => $search_response['raw_items_count_for_cache'], // For raw cache, count and base_items_count are the same
                    'query' => $search_query,
                    'country_code' => $country,
                    'exclude' => '', // Store raw results with no exclusion
                    'sort_by' => '', // Store raw results with no specific sort
                    'pagination_urls' => isset($search_response['pagination_urls']) ? $search_response['pagination_urls'] : array(), // Store pagination URLs
                    'data_source' => 'live_request_raw_cache' 
                );
                
                try {
                    // Cache these raw results using an empty exclude and sort_by for the hash
                    ps_cache_results($search_query, $country, '', '', $data_to_cache);
                    // ps_log_error("Successfully cached " . $search_response['raw_items_count_for_cache'] . " raw items for query '{$search_query}' and country '{$country}'.");
                } catch (Exception $cache_e) {
                    // ps_log_error("Error caching raw results: " . $cache_e->getMessage());
                    // Continue even if caching fails - we can still return the display results
                }
            }
        
            // Prepare the data for display (filtered items)
            $display_results = array(
                'success' => isset($search_response['success']) ? $search_response['success'] : false,
                'items' => isset($search_response['items']) ? $search_response['items'] : array(),
                'count' => isset($search_response['count']) ? $search_response['count'] : 0,
                'base_items_count' => isset($search_response['raw_items_count_for_cache']) ? $search_response['raw_items_count_for_cache'] : 0, // total before display filtering
                'query' => $search_query,
                'exclude' => $exclude_keywords,
                'sort_by' => $sort_by,
                'country_code' => $country,
                'platforms' => $platforms, // Include platforms in response
                'pagination_urls' => isset($search_response['pagination_urls']) ? $search_response['pagination_urls'] : array(), // Include pagination URLs
                'data_source' => 'live_request_filtered_display'
            );

            if (isset($search_response['message'])) {
                $display_results['message'] = $search_response['message'];
            }
            if (isset($search_response['error_type'])) {
                $display_results['error_type'] = $search_response['error_type'];
            }
             if (isset($search_response['debug_info'])) {
                $display_results['debug_info'] = $search_response['debug_info'];
            }

            // Return the DISPLAY results (success or error)
            if ($display_results['success']) {
                wp_send_json_success($display_results);
            } else {
                if (isset($display_results['amazon_search_url'])) {
                    ps_log_error("FRONTEND_ERROR_WITH_URL: " . json_encode($display_results));
                }
                wp_send_json_error($display_results);
            }
        } else {
            // ps_log_error("Invalid search results format: " . print_r($search_response, true));
            
            // Create search URLs for the user to continue searching manually
            $amazon_base = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
            $amazon_search_url = $amazon_base . '/s?k=' . urlencode($search_query);
            
            $ebay_base = ($country === 'ca') ? 'https://www.ebay.ca' : 'https://www.ebay.com';
            $ebay_search_url = $ebay_base . '/sch/i.html?_nkw=' . urlencode($search_query);
            
            $error_response = array(
                'success' => false,
                'message' => 'Invalid response format from search function.',
                'items' => array(),
                'count' => 0,
                'error_type' => 'invalid_response_format',
                'amazon_search_url' => $amazon_search_url,
                'ebay_search_url' => $ebay_search_url,
                'search_query' => $search_query,
                'country' => $country
            );
            
            wp_send_json_error($error_response);
        }
    } catch (Exception $e) {
        // ps_log_error("Exception during search: " . $e->getMessage());
        wp_send_json_error(array(
            'success' => false,
            'message' => 'An error occurred during search: ' . $e->getMessage(),
            'items' => array(),
            'count' => 0,
            'error_type' => 'search_exception'
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
    // ps_log_error("Sending AJAX response: " . round($response_size / 1024, 2) . " KB");
    
    // Check if the response is too large (> 500KB)
    if ($response_size > 500000) {
        // ps_log_error("Response too large (" . round($response_size / 1024, 2) . " KB), reducing");
        
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
        // ps_log_error("Fatal error during AJAX request: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
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
    // Verify nonce with better error handling
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ps-simple-test-nonce')) {
        $nonce_value = isset($_POST['nonce']) ? substr($_POST['nonce'], 0, 5) . '...' : 'not set';
        // ps_log_error("Security check failed in ps_handle_simple_ajax_test. Nonce: " . $nonce_value);
        wp_send_json_error(array(
            'message' => 'Security verification failed. Please refresh the page and try again.',
            'error_type' => 'security_error',
            'nonce_issue' => true,
            'nonce_partial' => $nonce_value
        ));
        return;
    }

    $payload = isset($_POST['test_payload']) ? sanitize_text_field($_POST['test_payload']) : 'No payload received';

    // Log to error_log
    // ps_log_error("Simple AJAX Test: " . $payload);

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
 * Get or create a unique user identifier for caching
 * Uses WordPress user ID for logged-in users, or a cookie for visitors
 */
function ps_get_user_identifier() {
    // For logged-in users, use their WordPress user ID if it is not 0
    if (is_user_logged_in()) {
        $wp_user_id = get_current_user_id();
        if ($wp_user_id && $wp_user_id !== 0) {
            $user_id = 'user_' . $wp_user_id;
            return $user_id;
        }
    }
    // For visitors, use/create a cookie-based identifier
    $cookie_name = 'ps_visitor_id';
    // Check if the visitor already has an ID cookie
    if (isset($_COOKIE[$cookie_name])) {
        $visitor_id = sanitize_text_field($_COOKIE[$cookie_name]);
        // Validate that it matches our expected format
        if (preg_match('/^visitor_[a-f0-9]{10}$/', $visitor_id)) {
            return $visitor_id;
        } 
    } else {
        
        // Check session as fallback
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['ps_visitor_id'])) {
            $visitor_id = sanitize_text_field($_SESSION['ps_visitor_id']);
            // Validate that it matches our expected format
            if (preg_match('/^visitor_[a-f0-9]{10}$/', $visitor_id)) {
                return $visitor_id;
            } 
        }
    }
    // If no valid cookie exists, create a new visitor ID
    $visitor_id = 'visitor_' . substr(md5(uniqid(mt_rand(), true)), 0, 10);
    
    // Set a cookie that lasts for 30 days, but only if headers haven't been sent yet
    if (!headers_sent()) {
        setcookie($cookie_name, $visitor_id, time() + (30 * DAY_IN_SECONDS), '/');
    } else {
        // Use session as fallback when cookies can't be set
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['ps_visitor_id'] = $visitor_id;
    }
    
    return $visitor_id;
}

/**
 * Get information about the latest cached base search query for a specific user
 *
 * @return array|null An array with 'query', 'country_code', 'base_items_count' or null.
 */
function ps_get_latest_cached_query_info($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ps_cache';
    
    // Get the most recent non-empty query for this user
    $latest_query = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT query_data, country_code 
            FROM $table_name 
            WHERE user_id = %s 
            AND query_data != '' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC 
            LIMIT 1",
            $user_id
        )
    );
    
    if ($latest_query) {
        $query_data = json_decode($latest_query->query_data, true);
        return (object)array(
            'query' => $query_data['query'] ?? '',
            'country_code' => $latest_query->country_code
        );
    }
    
    return null;
}

/**
 * Get cached search results for a specific user
 */
function ps_get_cached_results($query, $country_code, $exclude, $sort_by) {
    global $wpdb;
    
    // Get settings
    $settings = get_option('ps_settings');
    $cache_duration = isset($settings['cache_duration']) ? $settings['cache_duration'] : 3600;
    
    // Get the user identifier
    $user_id = ps_get_user_identifier();
    ps_log_error("ps_get_cached_results: Generated user_id = '{$user_id}' for query '{$query}'");
    
    // Create a unique hash including the user ID
    $query_hash = md5($query . '|' . $country_code . '|' . $exclude . '|' . $sort_by . '|' . $user_id);
    ps_log_error("ps_get_cached_results: Looking for query_hash = '{$query_hash}' with user_id = '{$user_id}'");
    
    // Table name
    $table_name = $wpdb->prefix . 'ps_cache';
    
    // Query using expires_at column and user_id
    $cached_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name 
            WHERE query_hash = %s 
            AND user_id = %s
            AND (
                (expires_at IS NOT NULL AND expires_at > DATE_SUB(NOW(), INTERVAL 300 SECOND))
                OR 
                (expires_at IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL %d SECOND))
            )
            ORDER BY created_at DESC 
            LIMIT 1",
            $query_hash,
            $user_id,
            $cache_duration
        )
        );
        
        if ($cached_data) {
        // Track cache hits in logs
        $age_seconds = time() - strtotime($cached_data->created_at);
        $age_minutes = round($age_seconds / 60);
        // ps_log_error("Cache hit for user {$user_id}: '{$query}' country '{$country_code}' (age: {$age_minutes} mins)" . 
                   // (isset($cached_data->expires_at) ? " with explicit expiry" : ""));
        
        // Parse the JSON data
        $results = json_decode($cached_data->results, true);
        
        // Check if this is a cached error response
        if (isset($results['success']) && $results['success'] === false) {
            // ps_log_error("Retrieved cached error response for user {$user_id}: " . (isset($results['message']) ? $results['message'] : 'Unknown error'));
        }
        
        return $results;
    }
    
    return false;
}

/**
 * Cache search results for a specific user
 */
function ps_cache_results($query, $country_code, $exclude, $sort_by, $results) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ps_cache'; // Define table name
    $cache_expiry = date('Y-m-d H:i:s', strtotime('+24 hours')); // Define cache expiry

    // Get the user identifier
    $user_id = ps_get_user_identifier();
    ps_log_error("ps_cache_results: Generated user_id = '{$user_id}' for query '{$query}'");
    
    // Create a unique hash including the user ID
    $query_hash = md5($query . '|' . $country_code . '|' . $exclude . '|' . $sort_by . '|' . $user_id);
    
    // Store query data for debugging
    $query_data = json_encode(array(
        'query' => $query,
        'country_code' => $country_code,
        'exclude' => $exclude,
        'sort_by' => $sort_by,
        'user_id' => $user_id  // Include user ID in the stored data
    ));
    
    // Clean up the results to prevent storing large objects
    $clean_results = $results;
    // Only remove large, unnecessary fields, but DO NOT truncate or slice the items array or remove product fields
    unset($clean_results['debug_post_data']);
    unset($clean_results['html_response']);
    unset($clean_results['debug_file']);
    unset($clean_results['raw_html']);
    unset($clean_results['trace']);
    unset($clean_results['raw_items_for_cache']);
    unset($clean_results['raw_items_count_for_cache']);
    // Add user ID to the results for reference (if not already there from ps_parse_amazon_results)
    if (!isset($clean_results['user_id'])) {
        $clean_results['user_id'] = $user_id;
    }
    // Truncate description to 250 chars for size, but keep all other fields
    if (isset($clean_results['items']) && is_array($clean_results['items'])) {
        foreach ($clean_results['items'] as $key => $item) {
            if (isset($item['description']) && strlen($item['description']) > 250) {
                $clean_results['items'][$key]['description'] = substr($item['description'], 0, 250) . '...';
            }
        }
    }
    // Convert to JSON and check size
    $json_results = json_encode($clean_results);
    $size_kb = strlen($json_results) / 1024;
    // Log the size for monitoring
    ps_log_error("Caching results for user {$user_id} (query: '{$query}', country: {$country_code}): {$size_kb} KB. Items: " . (isset($clean_results['count']) ? $clean_results['count'] : 'N/A'));
    
    // Check if record already exists for this query hash
    $existing_record = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$table_name} WHERE query_hash = %s AND user_id = %s", $query_hash, $user_id));
    
    if ($existing_record) {
        // Update existing record
        ps_log_error("ps_cache_results: Updating existing cache record ID {$existing_record->id} with user_id = '{$user_id}', query_hash = '{$query_hash}'");
        $update_result = $wpdb->update(
            $table_name,
            array(
                'query_data' => $query_data,
                'results' => $json_results,
                'created_at' => current_time('mysql'), // Update the creation time to reflect latest update
                'expires_at' => $cache_expiry
            ),
            array(
                'query_hash' => $query_hash,
                'user_id' => $user_id
            ),
            array(
                '%s', // query_data
                '%s', // results
                '%s', // created_at
                '%s'  // expires_at
            ),
            array(
                '%s', // query_hash
                '%s'  // user_id
            )
        );
        ps_log_error("ps_cache_results: Update result = " . ($update_result !== false ? 'SUCCESS' : 'FAILED') . " for record ID {$existing_record->id}");
        if ($wpdb->last_error) {
            ps_log_error("Database error when updating cache results for hash {$query_hash}: " . $wpdb->last_error);
        } else {
            ps_log_error("Successfully updated cache results with hash: " . $query_hash . ", User: " . $user_id);
        }
    } else {
        // Insert new record
        ps_log_error("ps_cache_results: Creating new cache record with user_id = '{$user_id}', query_hash = '{$query_hash}'");
        $insert_result = $wpdb->insert(
            $table_name,
            array(
                'query_hash' => $query_hash,
                'query_data' => $query_data,
                'results' => $json_results,
                'created_at' => current_time('mysql'),
                'expires_at' => $cache_expiry,
                'user_id' => $user_id // Store user_id directly in the table
            ),
            array(
                '%s', // query_hash
                '%s', // query_data
                '%s', // results
                '%s', // created_at
                '%s', // expires_at
                '%s'  // user_id - explicitly specify as string
            )
        );
        ps_log_error("ps_cache_results: Insert result = " . ($insert_result ? 'SUCCESS' : 'FAILED') . ", wpdb->insert_id = " . $wpdb->insert_id);
        if ($wpdb->last_error) {
            ps_log_error("Database error when caching results for hash {$query_hash}: " . $wpdb->last_error);
        } else {
            ps_log_error("Successfully cached results with hash: " . $query_hash . ", User: " . $user_id);
        }
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
    
    // Add a link to update the table structure directly from the plugin page
    add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ps_add_plugin_action_links');
}
add_action('admin_menu', 'ps_admin_menu');

/**
 * Add plugin action links
 */
function ps_add_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=primates-shoppers') . '">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}

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
    $cache_duration = get_option('ps_settings')['cache_duration'] ?? 86400;
    
    // Only delete entries older than the cache duration
    $deleted = $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $table_name 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d SECOND)",
            $cache_duration
        )
    );
    
    if ($deleted > 0) {
        // ps_log_error("Cache cleanup: removed {$deleted} expired entries");
    }
}
add_action('ps_cache_cleanup', 'ps_cleanup_cache');

/**
 * Clear all cache entries (for debugging price parsing issues)
 */
function ps_clear_all_cache() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ps_cache';
    
    $result = $wpdb->query("DELETE FROM $table_name");
    
    if ($result !== false) {
        ps_log_error("Cache cleared successfully. Deleted $result entries.");
        return $result;
    } else {
        ps_log_error("Error clearing cache: " . $wpdb->last_error);
        return false;
    }
}

// Register AJAX filter handler
add_action('wp_ajax_ps_filter', 'ps_ajax_filter');
add_action('wp_ajax_nopriv_ps_filter', 'ps_ajax_filter');

/**
 * Handle AJAX filter request
 * This only filters already fetched results, does not request new data from Amazon
 */
function ps_ajax_filter() {
    // Verify nonce with better error handling
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ps_filter_nonce')) {
        $nonce_value = isset($_POST['nonce']) ? substr($_POST['nonce'], 0, 5) . '...' : 'not set';
        // ps_log_error("Security check failed in ps_ajax_filter. Nonce: " . $nonce_value);
        wp_send_json_error(array(
            'message' => 'Security verification failed. Please refresh the page and try again.',
            'error_type' => 'security_error'
        ));
        return;
    }

    $last_search_query = isset($_POST['last_search_query']) ? sanitize_text_field($_POST['last_search_query']) : '';
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : 'us';
    $new_exclude_keywords = isset($_POST['exclude_keywords']) ? sanitize_text_field($_POST['exclude_keywords']) : '';
    $new_sort_by = isset($_POST['sort_by']) ? sanitize_text_field($_POST['sort_by']) : 'price';
    $new_min_rating = isset($_POST['min_rating']) ? floatval($_POST['min_rating']) : null;
    $user_id = ps_get_user_identifier(); // Get the user's identifier

    if (empty($last_search_query)) {
        ps_send_ajax_response(array('success' => false, 'message' => 'Original search query is missing for filtering.', 'items' => array(), 'count' => 0, 'error_type' => 'missing_original_query'));
        return;
    }

    // ps_log_error("AJAX Filter for user {$user_id}: OriginalQuery='{$last_search_query}', Country='{$country_code}', NewExclude='{$new_exclude_keywords}', NewSort='{$new_sort_by}', MinRating='{$new_min_rating}'");

    // First, try to get this specific filtered result from cache for this user
    $cached_filtered_results = ps_get_cached_results($last_search_query, $country_code, $new_exclude_keywords, $new_sort_by);

    if ($cached_filtered_results !== false && isset($cached_filtered_results['items']) && isset($cached_filtered_results['count']) && isset($cached_filtered_results['base_items_count'])) {
        // ps_log_error("AJAX Filter: Found fully matching filtered results in cache for user {$user_id}, query '{$last_search_query}'");
        $response_data = array(
            'success' => true,
            'items' => $cached_filtered_results['items'],
            'count' => $cached_filtered_results['count'],
            'base_items_count' => $cached_filtered_results['base_items_count'],
            'query' => $last_search_query, // Original query
            'exclude' => $new_exclude_keywords, // Applied filters
            'sort_by' => $new_sort_by,         // Applied sort
            'data_source' => 'cache',
            'country_code' => $country_code,
            'user_id' => $user_id // Include user ID in response
        );
        ps_send_ajax_response($response_data);
        return;
    }

    // ps_log_error("AJAX Filter: No exact match in cache for user {$user_id}, query '{$last_search_query}'. Fetching base items.");

    // Get the BASE items from cache for this user
    $base_cache_key_exclude = '';
    $base_cache_key_sort = '';
    $cached_base_data = ps_get_cached_results($last_search_query, $country_code, $base_cache_key_exclude, $base_cache_key_sort);

    if ($cached_base_data === false || !isset($cached_base_data['items']) || !isset($cached_base_data['count'])) {
        // ps_log_error("AJAX Filter: Base items not found in cache for user {$user_id}, query '{$last_search_query}'. This is unexpected if a search was just performed.");
        ps_send_ajax_response(array(
            'success' => false, 
            'message' => 'We cannot find your original search data. Please perform a new search before filtering.', 
            'items' => array(), 
            'count' => 0, 
            'base_items_count' => 0,
            'error_type' => 'base_cache_miss',
            'user_id' => $user_id
        ));
        return;
    }

    $base_items = $cached_base_data['items'];
    $base_items_count = $cached_base_data['count']; // This is the 'y' in 'x of y'
    // ps_log_error("AJAX Filter: Retrieved " . count($base_items) . " base items (count from cache: {$base_items_count}) for '{$last_search_query}' from base cache.");

    // Filter these base items with the new filter parameters
    // Note: ps_filter_amazon_products expects the main query ($last_search_query) for potential title matching
    $filtered_result = ps_filter_amazon_products($base_items, $last_search_query, $new_exclude_keywords, $new_sort_by, $new_min_rating);
    
    $final_items = array_values($filtered_result['items']);
    $final_count = $filtered_result['count'];

    // ps_log_error("AJAX Filter: Applied new filters. New count: {$final_count} out of {$base_items_count} base items.");

    // Cache this newly filtered result for future identical filter requests
    $newly_filtered_cache_data = array(
        'items' => $final_items,
        'count' => $final_count,
        'base_items_count' => $base_items_count, // Crucial for UI
        'query' => $last_search_query,
        'country_code' => $country_code,
        'exclude' => $new_exclude_keywords,
        'sort_by' => $new_sort_by,
        'data_source' => 'derived_from_cache'
    );
    ps_cache_results($last_search_query, $country_code, $new_exclude_keywords, $new_sort_by, $newly_filtered_cache_data);
    // ps_log_error("AJAX Filter: Cached newly filtered items for '{$last_search_query}', Exclude='{$new_exclude_keywords}', Sort='{$new_sort_by}'.");

    $response_data = array(
        'success' => true,
        'items' => $final_items,
        'count' => $final_count,
        'base_items_count' => $base_items_count, // Total items before these specific filters
        'query' => $last_search_query,          // Original query
        'exclude' => $new_exclude_keywords,     // Filters applied in this step
        'sort_by' => $new_sort_by,             // Sort applied in this step
        'data_source' => 'filtered_base_cache',
        'country_code' => $country_code,
        'user_id' => $user_id // Include user ID in response
    );
    ps_send_ajax_response($response_data);
}

/**
 * Get all unique search queries available in the cache
 *
 * @return array Array of unique query/country combinations with their item counts
 */
function ps_get_available_cached_queries() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ps_cache';
    $queries = array();

    // Get all unique query/country combinations from the cache
    $cache_entries = $wpdb->get_results(
        "SELECT DISTINCT query_data FROM {$table_name} ORDER BY created_at DESC LIMIT 20"
    );

    if ($cache_entries) {
        foreach ($cache_entries as $entry) {
            $query_params = json_decode($entry->query_data, true);
            
            if ($query_params && isset($query_params['query']) && isset($query_params['country_code'])) {
                $key = $query_params['query'] . '|' . $query_params['country_code'];
                
                // Only add each unique query/country combination once
                if (!isset($queries[$key])) {
                    // Get the base items for this query/country (empty exclude/sort)
                    $base_items = ps_get_cached_results($query_params['query'], $query_params['country_code'], '', '');
                    
                    // If we found base items, use their count, otherwise mark as unknown
                    $count = ($base_items && isset($base_items['count'])) ? $base_items['count'] : 
                            (($base_items && isset($base_items['items'])) ? count($base_items['items']) : '?');
                    
                    $queries[$key] = array(
                        'query' => $query_params['query'],
                        'country_code' => $query_params['country_code'],
                        'base_items_count' => $count
                    );
                }
            }
        }
    }

    // ps_log_error("Found " . count($queries) . " unique cached queries");
    return array_values($queries); // Return as indexed array
}

// Add this new function to fetch the most recent cache entry for a user
function ps_get_most_recent_user_cache($user_id, $log_row = false) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ps_cache';
    $cached_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %s ORDER BY created_at DESC LIMIT 1",
            $user_id
        ),
        ARRAY_A
    );
    if ($log_row) {
        // ps_log_error("ps_get_most_recent_user_cache raw row: " . print_r($cached_data, true));
    }
    if ($cached_data) {
        $results = json_decode($cached_data['results'], true);
        // Attach query/country/exclude for frontend use
        $query_data = json_decode($cached_data['query_data'], true);
        if (is_array($query_data)) {
            $results['query'] = $query_data['query'] ?? '';
            $results['exclude'] = $query_data['exclude'] ?? '';
            $results['country_code'] = $query_data['country_code'] ?? '';
        }
        return $results;
    }
    return false;
}

// --- Add this utility function for server-side filtering to match JS logic ---
/**
 * Filter Amazon products by include (search) and exclude terms, with wildcard logic.
 * All terms are treated as wildcards (substring match, not whole word).
 * If include is '*', return all items. Exclude always filters out matching substrings.
 *
 * @param array $items Array of product items
 * @param string $includeText Search/include terms (space-separated)
 * @param string $excludeText Exclude terms (space-separated)
 * @param string $sortBy Sort criteria ('price' or 'price_per_unit')
 * @return array ['items' => [...], 'count' => int]
 */
function ps_filter_amazon_products($items, $includeText = '', $excludeText = '', $sortBy = 'price', $minRating = null) {
    if (!is_array($items)) return ['items' => [], 'count' => 0];
    $filtered = $items;

    // Exclude filter: remove any item whose title contains any exclude term (case-insensitive, substring)
    if (!empty($excludeText)) {
        $excludeTerms = preg_split('/\s+/', strtolower($excludeText), -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($excludeTerms)) {
            $filtered = array_filter($filtered, function($item) use ($excludeTerms) {
                $title = isset($item['title']) ? strtolower($item['title']) : '';
                foreach ($excludeTerms as $term) {
                    if ($term !== '' && strpos($title, $term) !== false) {
                        return false; // Exclude this item
                    }
                }
                return true;
            });
        }
    }

    // Include filter: keep only items whose title contains ALL include terms (case-insensitive, substring)
    if (!empty($includeText) && trim($includeText) !== '*') {
        $includeTerms = preg_split('/\s+/', strtolower($includeText), -1, PREG_SPLIT_NO_EMPTY);
        if (!empty($includeTerms)) {
            $filtered = array_filter($filtered, function($item) use ($includeTerms) {
                $title = isset($item['title']) ? strtolower($item['title']) : '';
                foreach ($includeTerms as $term) {
                    if ($term !== '' && strpos($title, $term) === false) {
                        return false; // Must match all include terms
                    }
                }
                return true;
            });
        }
    }
    // else: if includeText is '*' or empty, do not filter by include

    // Rating filter: filter by minimum rating
    if ($minRating !== null && $minRating > 0) {
        $filtered = array_filter($filtered, function($item) use ($minRating) {
            // Exclude products with no rating when minimum rating filter is applied
            if (!isset($item['rating_number']) || empty($item['rating_number']) || $item['rating_number'] === 'N/A') {
                return false; // Exclude products with no rating when filtering by rating
            }
            
            $itemRating = floatval($item['rating_number']);
            return $itemRating >= $minRating;
        });
    }

    // Sorting
    $filtered = array_values($filtered); // reindex
    if ($sortBy === 'price_per_unit') {
        usort($filtered, function($a, $b) {
            $aVal = isset($a['price_per_unit_value']) ? floatval($a['price_per_unit_value']) : 0;
            $bVal = isset($b['price_per_unit_value']) ? floatval($b['price_per_unit_value']) : 0;
            return $aVal <=> $bVal;
        });
    } else { // default to price
        usort($filtered, function($a, $b) {
            $aVal = isset($a['price_value']) ? floatval($a['price_value']) : 0;
            $bVal = isset($b['price_value']) ? floatval($b['price_value']) : 0;
            return $aVal <=> $bVal;
        });
    }
    return ['items' => $filtered, 'count' => count($filtered)];
}

/**
 * AJAX handler for loading more results (pagination)
 */
function ps_ajax_load_more() {
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
    $min_rating = isset($_POST['min_rating']) ? floatval($_POST['min_rating']) : 4.0;
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'us';
    $page = isset($_POST['page']) ? intval($_POST['page']) : 2; // Default to page 2 for "load more"
    
    // Get platforms from the request (prioritize this over cached platforms)
    $requested_platforms = isset($_POST['platforms']) ? $_POST['platforms'] : array();
    if (!is_array($requested_platforms)) {
        $requested_platforms = array($requested_platforms);
    }
    
    $user_id = ps_get_user_identifier();
    
    // Log the request
    ps_log_error("AJAX Load More Request - User: {$user_id}, Query: '{$search_query}', Page: {$page}, Country: '{$country}', Requested Platforms: " . implode(', ', $requested_platforms));
    
    if (empty($search_query)) {
        ps_send_ajax_response(array(
            'success' => false,
            'message' => 'Search query is required for loading more results.'
        ));
        return;
    }
    
    // Only allow pages 2 and 3
    if ($page < 2 || $page > 3) {
        ps_send_ajax_response(array(
            'success' => false,
            'message' => 'Invalid page number. Only pages 2 and 3 are supported.'
        ));
        return;
    }
    
    // Check if we have existing cached results for this query (page 1)
    $existing_cache = ps_get_cached_results($search_query, $country, '', '');
    if (!$existing_cache || empty($existing_cache['items'])) {
        ps_send_ajax_response(array(
            'success' => false,
            'message' => 'No existing results found. Please perform a new search first.'
        ));
        return;
    }
    
    // Determine which platforms to use for load more
    // Priority: 1. Requested platforms 2. Cached platforms 3. Default to amazon
    $platforms = array();
    
    if (!empty($requested_platforms)) {
        // Use platforms requested by the client
        $platforms = $requested_platforms;
        ps_log_error("Load More: Using requested platforms: " . implode(', ', $platforms));
    } elseif (isset($existing_cache['platforms']) && !empty($existing_cache['platforms'])) {
        // Fall back to platforms from cache
        $platforms = is_array($existing_cache['platforms']) ? $existing_cache['platforms'] : array($existing_cache['platforms']);
        ps_log_error("Load More: Using cached platforms: " . implode(', ', $platforms));
    } else {
        // Default fallback
        $platforms = array('amazon');
        ps_log_error("Load More: Using default platforms: " . implode(', ', $platforms));
    }
    
    // Filter platforms to only supported ones (for load more, only Amazon and eBay currently support pagination)
    $load_more_supported_platforms = array('amazon', 'ebay');
    $platforms = array_intersect($platforms, $load_more_supported_platforms);
    
    if (empty($platforms)) {
        ps_send_ajax_response(array(
            'success' => false,
            'message' => 'No supported platforms selected for load more. Currently supported: ' . implode(', ', $load_more_supported_platforms)
        ));
        return;
    }
    
    ps_log_error("Load More: Loading page {$page} for supported platforms: " . implode(', ', $platforms));
    
    // Collect new items from all platforms
    $all_new_items = array();
    $platform_errors = array();
    $has_more_pages = false;
    
    // Load next page from each platform
    foreach ($platforms as $platform) {
        $platform = sanitize_text_field($platform);
        
        try {
            $platform_new_items = array();
            $platform_has_more = false;
            
            if ($platform === 'amazon') {
                // Amazon pagination uses stored URLs
                $pagination_urls = isset($existing_cache['pagination_urls']) ? $existing_cache['pagination_urls'] : array();
                $page_key = 'page_' . $page;
                
                if (!empty($pagination_urls) && isset($pagination_urls[$page_key])) {
                    $page_url = $pagination_urls[$page_key];
                    ps_log_error("Load More Amazon: Using pagination URL for page {$page}: {$page_url}");
                    
                    // Fetch the page using the parsed URL
                    $html_content = ps_fetch_amazon_search_results($page_url, $country);
                    
                    // Check for errors
                    if (is_array($html_content) && isset($html_content['error']) && $html_content['error'] === true) {
                        $platform_errors[] = "Amazon: HTTP " . $html_content['http_code'];
                        continue;
                    }
                    
                    if (empty($html_content)) {
                        $platform_errors[] = "Amazon: No response received";
                        continue;
                    }
                    
                    if (ps_is_amazon_blocking($html_content)) {
                        $platform_errors[] = "Amazon: Blocking detected";
                        continue;
                    }
                    
                    if (!ps_is_valid_search_page($html_content)) {
                        $platform_errors[] = "Amazon: Invalid page format";
                        continue;
                    }
                    
                    // Parse results
                    $associate_tag = ps_get_associate_tag($country);
                    $parse_results = ps_parse_amazon_results($html_content, $associate_tag, $min_rating);
                    
                    if (isset($parse_results['success']) && $parse_results['success'] && !empty($parse_results['items'])) {
                        $platform_new_items = $parse_results['items'];
                        // Add platform identifier
                        foreach ($platform_new_items as &$item) {
                            $item['platform'] = 'amazon';
                        }
                        
                        // Check if Amazon has more pages available
                        $platform_has_more = ($page < 3) && isset($pagination_urls['page_' . ($page + 1)]);
                        
                        ps_log_error("Load More Amazon: Found " . count($platform_new_items) . " new items, has more: " . ($platform_has_more ? 'yes' : 'no'));
                    }
                } else {
                    ps_log_error("Load More Amazon: No pagination URL for page {$page}");
                    $platform_errors[] = "Amazon: No pagination URL available for page {$page}";
                }
                
            } elseif ($platform === 'ebay') {
                // eBay pagination uses page numbers
                ps_log_error("Load More eBay: Searching page {$page}");
                
                $ebay_results = ps_search_ebay_products($search_query, '', $sort_by, $country, $min_rating, $page);
                
                if (isset($ebay_results['success']) && $ebay_results['success'] && !empty($ebay_results['items'])) {
                    $platform_new_items = $ebay_results['items'];
                    // Add platform identifier
                    foreach ($platform_new_items as &$item) {
                        $item['platform'] = 'ebay';
                    }
                    
                    // eBay has more pages if we got results (assume 3 page limit for consistency)
                    $platform_has_more = ($page < 3) && count($platform_new_items) > 0;
                    
                    ps_log_error("Load More eBay: Found " . count($platform_new_items) . " new items, has more: " . ($platform_has_more ? 'yes' : 'no'));
                } else {
                    if (isset($ebay_results['message'])) {
                        $platform_errors[] = "eBay: " . $ebay_results['message'];
                    } else {
                        $platform_errors[] = "eBay: No results found";
                    }
                }
                
            } else {
                // Future platform support (Best Buy, Walmart, etc.)
                ps_log_error("Load More: Platform '{$platform}' not yet implemented for pagination");
                $platform_errors[] = ucfirst($platform) . ": Not yet supported for load more";
            }
            
            // Add platform results to the total
            if (!empty($platform_new_items)) {
                $all_new_items = array_merge($all_new_items, $platform_new_items);
            }
            
            // Update has_more_pages if any platform has more
            if ($platform_has_more) {
                $has_more_pages = true;
            }
            
        } catch (Exception $e) {
            ps_log_error("Error loading more from {$platform}: " . $e->getMessage());
            $platform_errors[] = ucfirst($platform) . ": Error - " . $e->getMessage();
        }
    }
    
    // Check if we got any new items
    if (empty($all_new_items)) {
        $error_message = 'No more results available.';
        if (!empty($platform_errors)) {
            $error_message .= '<br><br>Platform issues: ' . implode(', ', $platform_errors);
        }
        
        ps_send_ajax_response(array(
            'success' => false,
            'message' => $error_message,
            'page_loaded' => $page,
            'platforms_attempted' => $platforms,
            'platform_errors' => $platform_errors
        ));
        return;
    }
    
    // Merge new items with existing cached items
    $existing_items = $existing_cache['items'];
    $merged_items = array_merge($existing_items, $all_new_items);
    
    // Remove duplicates based on product link
    $unique_items = array();
    $seen_links = array();
    
    foreach ($merged_items as $item) {
        $link = isset($item['link']) ? $item['link'] : '';
        if (!empty($link) && !in_array($link, $seen_links)) {
            $unique_items[] = $item;
            $seen_links[] = $link;
        } elseif (empty($link)) {
            // Keep items without links (shouldn't happen, but be safe)
            $unique_items[] = $item;
        }
    }
    
    // Update the cache with merged results and extended expiry
    $updated_cache_data = array(
        'success' => true,
        'items' => $unique_items,
        'count' => count($unique_items),
        'base_items_count' => count($unique_items),
        'query' => $search_query,
        'country_code' => $country,
        'exclude' => '',
        'sort_by' => '',
        'platforms' => $platforms, // Keep original platforms
        'pagination_urls' => isset($existing_cache['pagination_urls']) ? $existing_cache['pagination_urls'] : array(), // Keep Amazon pagination URLs
        'data_source' => 'load_more_merged',
        'last_page_loaded' => $page
    );
    
    // Cache the updated results with extended expiry (24 hours from now)
    ps_cache_results($search_query, $country, '', '', $updated_cache_data);
    
    ps_log_error("Load More: Successfully merged " . count($all_new_items) . " new items with existing cache. Total items: " . count($unique_items));
    
    // Measure elapsed time
    $elapsed_time = microtime(true) - $start_time;
    ps_log_error("Load more page {$page} completed in " . number_format($elapsed_time, 2) . " seconds");
    
    // Return the complete merged dataset (unfiltered) for frontend filtering
    // The frontend will apply all current filters via applyAllFilters()
    ps_send_ajax_response(array(
        'success' => true,
        'items' => $unique_items,  // Return all items (existing + new), unfiltered for frontend processing
        'count' => count($unique_items),
        'base_items_count' => count($unique_items),
        'new_items_count' => count($all_new_items),
        'query' => $search_query,
        'exclude' => $exclude_keywords,
        'sort_by' => $sort_by,
        'country_code' => $country,
        'platforms' => $platforms,
        'has_more_pages' => $has_more_pages,
        'page_loaded' => $page,
        'data_source' => 'load_more_merged',
        'platform_errors' => $platform_errors
    ));
}
add_action('wp_ajax_ps_load_more', 'ps_ajax_load_more');
add_action('wp_ajax_nopriv_ps_load_more', 'ps_ajax_load_more');

/**
 * AJAX handler for testing network detection settings
 */
function ps_ajax_test_network_detection() {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'ps_network_test')) {
        wp_die('Security check failed');
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions');
    }
    
    try {
        // Get test parameters from POST data
        $use_network_detection = isset($_POST['use_network_detection']) && $_POST['use_network_detection'] == '1';
        $current_network_range = sanitize_text_field($_POST['current_network_range']);
        $current_network_hostnames = sanitize_text_field($_POST['current_network_hostnames']);
        
        // Create temporary settings for testing
        $temp_settings = array(
            'use_network_detection' => $use_network_detection ? 1 : 0,
            'current_network_range' => $current_network_range,
            'current_network_hostnames' => $current_network_hostnames
        );
        
        // Store current settings and temporarily replace them
        $original_settings = get_option('ps_settings', array());
        $test_settings = array_merge($original_settings, $temp_settings);
        
        // Temporarily update the settings for testing
        update_option('ps_settings', $test_settings);
        
        // Run the network detection test
        $detection_details = array();
        $on_current_network = false;
        
        if ($use_network_detection) {
            $on_current_network = ps_is_on_current_network();
            
            // Get detailed information for the test result
            $server_ip = ps_get_server_ip();
            
            // Check IP range detection
            if (!empty($current_network_range)) {
                $in_range = ps_ip_in_range($server_ip, $current_network_range);
                $detection_details[] = "IP range check ({$current_network_range}): " . ($in_range ? "✓ Match" : "✗ No match") . " for IP {$server_ip}";
            } else {
                $detection_details[] = "IP range check: Disabled (no range specified)";
            }
            
            // Check hostname detection
            if (!empty($current_network_hostnames)) {
                $hostname = gethostname() ?: $_SERVER['SERVER_NAME'] ?? 'unknown';
                $hostnames = array_map('trim', explode(',', $current_network_hostnames));
                $hostname_match = false;
                
                foreach ($hostnames as $pattern) {
                    if (fnmatch($pattern, $hostname)) {
                        $hostname_match = true;
                        break;
                    }
                }
                
                $detection_details[] = "Hostname check: " . ($hostname_match ? "✓ Match" : "✗ No match") . " for hostname '{$hostname}' against patterns: " . $current_network_hostnames;
            } else {
                $detection_details[] = "Hostname check: Disabled (no patterns specified)";
            }
            
            // Check for private/local IP
            $is_private = ps_is_local_or_private_ip($server_ip);
            $detection_details[] = "Private/Local IP detection: " . ($is_private ? "✓ Private/Local IP detected" : "✗ Public IP detected") . " ({$server_ip})";
            
        } else {
            $detection_details[] = "Network detection is disabled";
            $server_ip = ps_get_server_ip();
        }
        
        // Determine if proxy would be used
        $will_use_proxy = $use_network_detection && $on_current_network;
        
        // Restore original settings
        update_option('ps_settings', $original_settings);
        
        // Return test results
        wp_send_json_success(array(
            'network_detection_enabled' => $use_network_detection,
            'on_current_network' => $on_current_network,
            'will_use_proxy' => $will_use_proxy,
            'server_ip' => $server_ip,
            'detection_details' => $detection_details
        ));
        
    } catch (Exception $e) {
        // Restore original settings in case of error
        if (isset($original_settings)) {
            update_option('ps_settings', $original_settings);
        }
        
        wp_send_json_error(array(
            'message' => 'Network detection test failed: ' . $e->getMessage()
        ));
    }
}
add_action('wp_ajax_ps_test_network_detection', 'ps_ajax_test_network_detection');

/**
 * Search multiple platforms and combine results
 */
function ps_search_multi_platform_products($query, $exclude_keywords = '', $sort_by = 'price', $country = 'us', $min_rating = 4.0, $platforms = array('amazon')) {
    $combined_results = array();
    $all_items = array();
    $total_count = 0;
    $success = false;
    $messages = array();
    
    ps_log_error("Multi-platform search for platforms: " . implode(', ', $platforms));
    
    // Search each platform
    $pagination_urls = array(); // Collect pagination URLs from platforms
    
    foreach ($platforms as $platform) {
        $platform = sanitize_text_field($platform);
        
        try {
            $platform_results = array();
            
            if ($platform === 'amazon') {
                ps_log_error("Searching Amazon...");
                $platform_results = ps_search_amazon_products($query, $exclude_keywords, $sort_by, $country, $min_rating);
            } elseif ($platform === 'ebay') {
                ps_log_error("Searching eBay...");
                $platform_results = ps_search_ebay_products($query, $exclude_keywords, $sort_by, $country, $min_rating);
            } elseif ($platform === 'bestbuy') {
                ps_log_error("Searching Best Buy...");
                $platform_results = ps_search_bestbuy_products($query, $exclude_keywords, $sort_by, $country, $min_rating);
            } elseif ($platform === 'walmart') {
                ps_log_error("Searching Walmart...");
                $platform_results = ps_search_walmart_products($query, $exclude_keywords, $sort_by, $country, $min_rating);
            }
            
            if (!empty($platform_results) && is_array($platform_results)) {
                if (isset($platform_results['success']) && $platform_results['success']) {
                    $success = true;
                    
                    // Capture pagination URLs from Amazon
                    if ($platform === 'amazon' && isset($platform_results['pagination_urls'])) {
                        $pagination_urls = $platform_results['pagination_urls'];
                        ps_log_error("Multi-platform: Captured Amazon pagination URLs: " . json_encode(array_keys($pagination_urls)));
                    }
                    
                    if (isset($platform_results['items']) && is_array($platform_results['items'])) {
                        // Add platform identifier to each item
                        foreach ($platform_results['items'] as $item) {
                            $item['platform'] = $platform;
                            $all_items[] = $item;
                        }
                        $total_count += count($platform_results['items']);
                    }
                } else {
                    // Platform failed, collect error message
                    if (isset($platform_results['message'])) {
                        $messages[] = ucfirst($platform) . ': ' . $platform_results['message'];
                    }
                }
            }
            
        } catch (Exception $e) {
            ps_log_error("Error searching {$platform}: " . $e->getMessage());
            $messages[] = ucfirst($platform) . ': Error - ' . $e->getMessage();
        }
    }
    
    // Apply filtering and sorting to combined results
    if (!empty($all_items)) {
        $filtered_items = ps_filter_multi_platform_products($all_items, '', $exclude_keywords, $sort_by, $min_rating);
        
        $combined_results = array(
            'success' => true,
            'items' => $filtered_items,
            'count' => count($filtered_items),
            'raw_items_for_cache' => $all_items, // Store all items for cache
            'raw_items_count_for_cache' => count($all_items),
            'platforms' => $platforms,
            'pagination_urls' => $pagination_urls // Include pagination URLs for load more
        );
        
        if (!empty($messages)) {
            $combined_results['platform_messages'] = $messages;
        }
        
    } else {
        // No results from any platform
        $combined_results = array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => !empty($messages) ? implode('<br>', $messages) : 'No results found from any platform.',
            'platforms' => $platforms
        );
    }
    
    ps_log_error("Multi-platform search completed. Total items: " . count($all_items) . ", Filtered items: " . (isset($filtered_items) ? count($filtered_items) : 0));
    
    return $combined_results;
}

/**
 * Filter products from multiple platforms
 */
function ps_filter_multi_platform_products($items, $includeText = '', $excludeText = '', $sortBy = 'price', $minRating = null) {
    if (empty($items) || !is_array($items)) {
        return array();
    }
    
    $filtered_items = array();
    
    foreach ($items as $item) {
        // Apply text filtering
        if (!empty($excludeText)) {
            $exclude_terms = array_map('trim', explode(',', strtolower($excludeText)));
            $item_text = strtolower($item['title'] . ' ' . (isset($item['brand']) ? $item['brand'] : ''));
            
            $skip_item = false;
            foreach ($exclude_terms as $exclude_term) {
                if (!empty($exclude_term) && strpos($item_text, $exclude_term) !== false) {
                    $skip_item = true;
                    break;
                }
            }
            
            if ($skip_item) {
                continue;
            }
        }
        
        // Apply rating filtering (for platforms that support it)
        if ($minRating !== null && isset($item['rating_numeric']) && is_numeric($item['rating_numeric'])) {
            if (floatval($item['rating_numeric']) < floatval($minRating)) {
                continue;
            }
        }
        
        $filtered_items[] = $item;
    }
    
    // Sort items
    if ($sortBy === 'price' && !empty($filtered_items)) {
        usort($filtered_items, function($a, $b) {
            $price_a = isset($a['price_numeric']) ? floatval($a['price_numeric']) : PHP_FLOAT_MAX;
            $price_b = isset($b['price_numeric']) ? floatval($b['price_numeric']) : PHP_FLOAT_MAX;
            return $price_a - $price_b;
        });
    } elseif ($sortBy === 'price_per_unit' && !empty($filtered_items)) {
        usort($filtered_items, function($a, $b) {
            $ppu_a = isset($a['price_per_unit_numeric']) ? floatval($a['price_per_unit_numeric']) : PHP_FLOAT_MAX;
            $ppu_b = isset($b['price_per_unit_numeric']) ? floatval($b['price_per_unit_numeric']) : PHP_FLOAT_MAX;
            return $ppu_a - $ppu_b;
        });
    }
    
    return $filtered_items;
}

/**
 * Handle debug log AJAX requests
 */
function ps_ajax_debug_log() {
    // Verify nonce
    check_ajax_referer('ps-search-nonce', 'nonce');
    
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    $data = isset($_POST['data']) ? $_POST['data'] : '';
    
    if (!empty($message)) {
        $log_entry = "DEBUG: " . $message;
        if (!empty($data)) {
            $log_entry .= " | Data: " . $data;
        }
        ps_log_error($log_entry);
    }
    
    wp_send_json_success(array('logged' => true));
}
add_action('wp_ajax_ps_debug_log', 'ps_ajax_debug_log');
add_action('wp_ajax_nopriv_ps_debug_log', 'ps_ajax_debug_log');

