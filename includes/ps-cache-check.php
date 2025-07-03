<?php
/**
 * Cache check functions for Primates Shoppers
 *
 * This file implements the functionality for checking if cached data exists
 * for a specific search query and country.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX handler for checking if base cache exists
 * 
 * This is called from the search.js frontend to check if there's
 * cached data available for filtering without requiring a new search.
 */
function ps_ajax_check_base_cache() {
    // Verify nonce for security - support both nonce field names
    if (
        (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ps_filter_nonce')) && 
        (!isset($_POST['check_cache_nonce']) || !wp_verify_nonce($_POST['check_cache_nonce'], 'ps_filter_nonce'))
    ) {
        $nonce_value = isset($_POST['nonce']) ? substr($_POST['nonce'], 0, 5) . '...' : 'not set';
        // ps_log_error("Security check failed in ps_ajax_check_base_cache. Nonce: " . $nonce_value);
        wp_send_json_error(array('message' => 'Security check failed.'));
    }

    // Get the parameters from the request
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    $country_code = isset($_POST['country_code']) ? sanitize_text_field($_POST['country_code']) : 'us';

    if (empty($query)) {
        wp_send_json_error(array('message' => 'Search query is required.'));
    }

    // Get user ID for cache keys (matching the logic in ps_ajax_filter)
    $user_id = ps_get_user_identifier();
    
    // Log the check attempt for debugging
    // ps_log_error("Checking base cache for query '{$query}', country '{$country_code}', user '{$user_id}'");
    
    // Try to get the cached base results (using empty exclude/sort parameters as ps_ajax_filter does)
    $base_cache_data = ps_get_cached_results($query, $country_code, '', '');
    
    if ($base_cache_data && !empty($base_cache_data['items'])) {
        // The base cache exists and has items
        $count = count($base_cache_data['items']);
        // ps_log_error("Base cache found with {$count} items for query '{$query}', country '{$country_code}'");
        wp_send_json_success(array(
            'cache_exists' => true,
            'base_items_count' => $count,
            'user_id' => $user_id
        ));
    } else {
        // No valid base cache found
        // ps_log_error("No suitable base cache found for query '{$query}', country '{$country_code}'");
        wp_send_json_success(array(
            'cache_exists' => false,
            'message' => 'No cached results found. Please perform a new search.'
        ));
    }
}

// Register the AJAX handlers for both logged in and non-logged in users
add_action('wp_ajax_ps_check_base_cache', 'ps_ajax_check_base_cache');
add_action('wp_ajax_nopriv_ps_check_base_cache', 'ps_ajax_check_base_cache'); 
