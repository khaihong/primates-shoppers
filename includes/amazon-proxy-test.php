<?php
/**
 * Amazon Proxy Test
 * 
 * This file provides a simple test for Amazon proxy connectivity and product retrieval.
 * It doesn't use any cached or fallback data, only live requests.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX handlers for testing
 */
function ps_register_proxy_test_handlers() {
    add_action('wp_ajax_ps_test_proxy_connection', 'ps_ajax_test_proxy_connection');
    add_action('wp_ajax_ps_test_proxy_search', 'ps_ajax_test_proxy_search');
}
add_action('init', 'ps_register_proxy_test_handlers');

/**
 * Add a test page to the admin menu
 */
function ps_add_proxy_test_page() {
    add_submenu_page(
        'options-general.php',
        'Amazon Proxy Test',
        'Amazon Proxy Test',
        'manage_options',
        'ps-proxy-test',
        'ps_display_proxy_test_page'
    );
}
add_action('admin_menu', 'ps_add_proxy_test_page');

/**
 * Display the proxy test admin page
 */
function ps_display_proxy_test_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    ?>
    <div class="wrap">
        <h1>Amazon Proxy Connection Test</h1>
        <p>Use this page to test the proxy connection to Amazon and verify product retrieval functionality.</p>
        
        <div class="card">
            <h2>Connection Test</h2>
            <p>Test basic connectivity to Amazon via proxy.</p>
            <button id="ps-test-connection" class="button button-primary">Test Connection</button>
            <div id="ps-connection-result" class="ps-result-box"></div>
        </div>
        
        <div class="card" style="margin-top: 20px;">
            <h2>Product Search Test</h2>
            <p>Test retrieving actual product data from Amazon via proxy.</p>
            <div class="form-field">
                <label for="ps-test-query">Search Query:</label>
                <input type="text" id="ps-test-query" value="protein powder" style="width: 300px;">
            </div>
            <div class="form-field">
                <label for="ps-test-country">Country:</label>
                <select id="ps-test-country">
                    <option value="us">United States (amazon.com)</option>
                    <option value="ca">Canada (amazon.ca)</option>
                </select>
            </div>
            <button id="ps-test-search" class="button button-primary">Test Search</button>
            <div id="ps-search-result" class="ps-result-box"></div>
            <div id="ps-search-products"></div>
        </div>
    </div>
    
    <style>
        .ps-result-box {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f8f8;
            border-left: 4px solid #ccc;
            display: none;
        }
        .ps-error {
            border-left-color: #dc3232;
        }
        .ps-success {
            border-left-color: #46b450;
        }
        .form-field {
            margin: 10px 0;
        }
        #ps-search-products {
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        .ps-product-card {
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }
        .ps-product-image {
            display: block;
            max-width: 100%;
            height: auto;
            margin: 0 auto 10px auto;
            max-height: 150px;
        }
        .ps-product-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .ps-product-price {
            color: #B12704;
            font-weight: bold;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Connection Test
        $('#ps-test-connection').on('click', function() {
            const $button = $(this);
            const $result = $('#ps-connection-result');
            
            $button.prop('disabled', true);
            $button.text('Testing...');
            $result.removeClass('ps-error ps-success').hide();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_test_proxy_connection',
                    nonce: '<?php echo wp_create_nonce('ps_proxy_test'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<p>✅ ' + response.data.message + '</p>').addClass('ps-success').show();
                        if (response.data.details) {
                            $result.append('<p><strong>Details:</strong> ' + response.data.details + '</p>');
                        }
                    } else {
                        $result.html('<p>❌ ' + response.data.message + '</p>').addClass('ps-error').show();
                        if (response.data.details) {
                            $result.append('<p><strong>Details:</strong> ' + response.data.details + '</p>');
                        }
                    }
                },
                error: function() {
                    $result.html('<p>❌ Server error. The request failed.</p>').addClass('ps-error').show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.text('Test Connection');
                }
            });
        });
        
        // Product Search Test
        $('#ps-test-search').on('click', function() {
            const $button = $(this);
            const $result = $('#ps-search-result');
            const $products = $('#ps-search-products');
            const query = $('#ps-test-query').val();
            const country = $('#ps-test-country').val();
            
            if (!query) {
                $result.html('<p>❌ Please enter a search query.</p>').addClass('ps-error').show();
                return;
            }
            
            $button.prop('disabled', true);
            $button.text('Searching...');
            $result.removeClass('ps-error ps-success').hide();
            $products.empty();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_test_proxy_search',
                    nonce: '<?php echo wp_create_nonce('ps_proxy_test'); ?>',
                    query: query,
                    country: country
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<p>✅ ' + response.data.message + '</p>').addClass('ps-success').show();
                        
                        // Display products if any
                        if (response.data.products && response.data.products.length > 0) {
                            response.data.products.forEach(function(product) {
                                $products.append(`
                                    <div class="ps-product-card">
                                        <img src="${product.image || '<?php echo plugins_url('assets/img/no-image.png', dirname(__FILE__)); ?>'}" class="ps-product-image">
                                        <div class="ps-product-title">${product.title}</div>
                                        <div class="ps-product-price">${product.price}</div>
                                        <a href="${product.link}" target="_blank" class="button button-small">View on Amazon</a>
                                    </div>
                                `);
                            });
                        } else {
                            $products.html('<p>No products found.</p>');
                        }
                        
                        // Show request details
                        if (response.data.url) {
                            $result.append('<p><strong>Request URL:</strong> ' + response.data.url + '</p>');
                        }
                        if (response.data.time) {
                            $result.append('<p><strong>Request Time:</strong> ' + response.data.time + ' seconds</p>');
                        }
                    } else {
                        $result.html('<p>❌ ' + response.data.message + '</p>').addClass('ps-error').show();
                        if (response.data.details) {
                            $result.append('<p><strong>Error Details:</strong> ' + response.data.details + '</p>');
                        }
                    }
                },
                error: function() {
                    $result.html('<p>❌ Server error. The request failed.</p>').addClass('ps-error').show();
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $button.text('Test Search');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * AJAX handler for testing proxy connection to Amazon
 */
function ps_ajax_test_proxy_connection() {
    // Verify nonce
    check_ajax_referer('ps_proxy_test', 'nonce');
    
    // Only allow admins to run this test
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }
    
    // Start timing
    $start_time = microtime(true);
    
    // Test URL - Amazon product page to verify full page loading
    $test_url = 'https://www.amazon.com/Best-Sellers-Health-Personal-Care/zgbs/hpc/';
    
    ps_log_error("Running proxy connection test to Amazon Best Sellers page");
    
    // Fetch the page using our proxy function
    $response = ps_fetch_amazon_page($test_url);
    
    // If we got a response, check if it's valid
    if ($response !== false) {
        // Check if Amazon is blocking
        if (ps_is_amazon_blocking($response)) {
            ps_log_error("Proxy connection test: Amazon is blocking requests");
            wp_send_json_error(array(
                'message' => 'Amazon is blocking the request.',
                'details' => 'The connection was established, but Amazon is returning a blocking page.'
            ));
            return;
        }
        
        // Look for expected content to verify it's a valid Amazon page
        $expected_content = array(
            'Best Sellers',
            'Amazon Best Sellers',
            'Today\'s Deals',
            'Customer Service'
        );
        
        $found_content = false;
        foreach ($expected_content as $content) {
            if (strpos($response, $content) !== false) {
                $found_content = true;
                break;
            }
        }
        
        if (!$found_content) {
            ps_log_error("Proxy connection test: Received unexpected content");
            wp_send_json_error(array(
                'message' => 'Received unexpected content from Amazon.',
                'details' => 'The connection was established, but the response did not contain expected Amazon content.'
            ));
            return;
        }
        
        // Check if the response contains product listings
        $has_products = (strpos($response, 'data-component-type="s-search-results"') !== false) || 
                        (strpos($response, 'class="s-result-item"') !== false) ||
                        (strpos($response, 'class="a-carousel-card"') !== false);
        
        if (!$has_products) {
            ps_log_error("Proxy connection test: No product listings found in response");
            wp_send_json_error(array(
                'message' => 'No product listings found in response.',
                'details' => 'The connection was established, but no product listings were found in the response.'
            ));
            return;
        }
        
        // If we get here, we have a successful response
        $request_time = microtime(true) - $start_time;
        ps_log_error("Proxy connection test successful - found product listings");
        wp_send_json_success(array(
            'message' => 'Successfully connected to Amazon via proxy and found product listings.',
            'details' => 'Response time: ' . round($request_time, 2) . ' seconds. Response size: ' . strlen($response) . ' bytes.'
        ));
        return;
    }
    
    // If we get here, the request failed
    $request_time = microtime(true) - $start_time;
    ps_log_error("Proxy connection test failed");
    wp_send_json_error(array(
        'message' => 'Failed to connect to Amazon via proxy.',
        'details' => 'Total time: ' . round($request_time, 2) . ' seconds. Please check your proxy settings and try again.'
    ));
}

/**
 * AJAX handler for testing proxy product search
 */
function ps_ajax_test_proxy_search() {
    // Verify nonce
    check_ajax_referer('ps_proxy_test', 'nonce');
    
    // Only allow admins to run this test
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'You do not have permission to perform this action.'));
        return;
    }
    
    // Get search parameters
    $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'us';
    
    if (empty($query)) {
        wp_send_json_error(array('message' => 'Search query is required.'));
        return;
    }
    
    // Start timing
    $start_time = microtime(true);
    
    ps_log_error("Running proxy search test for query: '{$query}' in country: {$country}");
    
    // Construct the search URL
    $url = ps_construct_amazon_search_url($query, $country);
    
    // Fetch the search results page
    $response = ps_fetch_amazon_page($url, $country);
    
    // Calculate request time
    $request_time = microtime(true) - $start_time;
    
    if ($response === false) {
        ps_log_error("Proxy search test failed for query: '{$query}' - No response received");
        wp_send_json_error(array(
            'message' => 'Failed to fetch search results from Amazon.',
            'details' => 'The proxy connection attempt failed. No response received from Amazon.',
            'url' => $url,
            'time' => round($request_time, 2)
        ));
        return;
    }
    
    // Check if Amazon is blocking
    if (ps_is_amazon_blocking($response)) {
        ps_log_error("Proxy search test: Amazon is blocking search for query: '{$query}' - Blocking page detected");
        wp_send_json_error(array(
            'message' => 'Amazon is blocking the search request.',
            'details' => 'The connection was established, but Amazon is returning a blocking page. This usually means Amazon has detected automated access.',
            'url' => $url,
            'time' => round($request_time, 2)
        ));
        return;
    }
    
    // Check if we got a valid search results page
    if (strpos($response, 's-result-item') === false && strpos($response, 'a-carousel-card') === false) {
        ps_log_error("Proxy search test: Invalid search results page for query: '{$query}' - No product elements found");
        wp_send_json_error(array(
            'message' => 'Invalid search results page received.',
            'details' => 'The response does not contain expected product elements. This could indicate Amazon has changed their page structure or is returning an error page.',
            'url' => $url,
            'time' => round($request_time, 2)
        ));
        return;
    }
    
    // Parse the search results
    $associate_tag = defined('PS_AFFILIATE_ID') ? PS_AFFILIATE_ID : '';
    $products = ps_parse_amazon_results($response, $associate_tag);
    
    if (empty($products)) {
        ps_log_error("Proxy search test: No products found for query: '{$query}' - Primary parsing failed");
        
        // Try alternative parsing methods
        $products = ps_try_alternative_parsing($response, $associate_tag);
        
        if (empty($products)) {
            ps_log_error("Proxy search test: Alternative parsing also failed for query: '{$query}'");
            wp_send_json_error(array(
                'message' => 'No products found in search results.',
                'details' => 'Connected to Amazon successfully, but could not extract any products from the search results. This could indicate Amazon has changed their page structure.',
                'url' => $url,
                'time' => round($request_time, 2)
            ));
            return;
        }
    }
    
    // Limit to first 10 products for display
    $products = array_slice($products, 0, 10);
    
    ps_log_error("Proxy search test successful - found " . count($products) . " products for query: '{$query}'");
    
    wp_send_json_success(array(
        'message' => 'Successfully retrieved ' . count($products) . ' products from Amazon via proxy.',
        'products' => $products,
        'url' => $url,
        'time' => round($request_time, 2)
    ));
} 