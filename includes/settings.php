<?php
/**
 * Plugin settings
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display settings page
 */
// Add CSS for parsing test
function ps_admin_enqueue_parsing_test_styles() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'settings_page_primates-shoppers') {
        wp_enqueue_style('ps-style', PS_PLUGIN_URL . 'assets/css/style.css', [], PS_VERSION);
    }
}
add_action('admin_enqueue_scripts', 'ps_admin_enqueue_parsing_test_styles');

function ps_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }
    
    // Save settings if form was submitted
    if (isset($_POST['ps_settings'])) {
        check_admin_referer('ps_save_settings');
        
        $settings = array(
            'amazon_associate_tag' => sanitize_text_field($_POST['ps_settings']['amazon_associate_tag']),
            'amazon_associate_tag_ca' => sanitize_text_field($_POST['ps_settings']['amazon_associate_tag_ca']),
            'amazon_associate_tag_us' => sanitize_text_field($_POST['ps_settings']['amazon_associate_tag_us']),
            'cache_duration' => intval($_POST['ps_settings']['cache_duration'])
        );
        
        update_option('ps_settings', $settings);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    
    // Show success message if database was updated
    if (isset($_GET['updated']) && $_GET['updated'] == '1') {
        echo '<div class="updated"><p>Cache table recreated successfully.</p></div>';
    }
    
    // Show error message if update failed
    if (isset($_GET['update_failed']) && $_GET['update_failed'] == '1') {
        echo '<div class="error"><p>Unable to update the cache table structure. Please use the "Force Rebuild" option below.</p></div>';
    }
    
    // Get current settings
    $settings = get_option('ps_settings');
    $amazon_associate_tag = isset($settings['amazon_associate_tag']) ? $settings['amazon_associate_tag'] : PS_AFFILIATE_ID;
    $amazon_associate_tag_ca = isset($settings['amazon_associate_tag_ca']) ? $settings['amazon_associate_tag_ca'] : PS_AFFILIATE_ID;
    $amazon_associate_tag_us = isset($settings['amazon_associate_tag_us']) ? $settings['amazon_associate_tag_us'] : 'primatesshopp-20';
    $cache_duration = isset($settings['cache_duration']) ? $settings['cache_duration'] : 86400;
    
    // Display the settings form
    ?>
    <div class="wrap">
        <h1>Primates Shoppers Settings</h1>
        
            <form method="post" action="">
            <?php wp_nonce_field('ps_save_settings'); ?>
            
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ps_settings_amazon_associate_tag_ca">Canada Associate Tag</label></th>
                        <td>
                            <input type="text" id="ps_settings_amazon_associate_tag_ca" name="ps_settings[amazon_associate_tag_ca]" value="<?php echo esc_attr($amazon_associate_tag_ca); ?>" class="regular-text">
                            <p class="description">Your Amazon Canada Associate tag for affiliate links.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ps_settings_amazon_associate_tag_us">US Associate Tag</label></th>
                        <td>
                            <input type="text" id="ps_settings_amazon_associate_tag_us" name="ps_settings[amazon_associate_tag_us]" value="<?php echo esc_attr($amazon_associate_tag_us); ?>" class="regular-text">
                            <p class="description">Your Amazon US Associate tag for affiliate links.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ps_settings_cache_duration">Cache Duration</label></th>
                        <td>
                            <input type="number" id="ps_settings_cache_duration" name="ps_settings[cache_duration]" value="<?php echo esc_attr($cache_duration); ?>" class="regular-text">
                            <p class="description">How long to cache search results (in seconds). Default: 86400 (24 hours).</p>
                        </td>
                    </tr>
                </table>
            
                <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>
        
        <hr>
        
        <h2>Database Management</h2>
        <p>If you're experiencing database-related issues, you can use the options below:</p>
        <p>
            <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=primates-shoppers&action=ps_force_update_db'), 'ps_force_update_db'); ?>" class="button">Update Cache Table</a>
            <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=primates-shoppers&action=ps_force_update_db&force_rebuild=1'), 'ps_force_update_db'); ?>" class="button" style="margin-left: 10px;" onclick="return confirm('This will DELETE and recreate the cache table. All cached data will be lost. Continue?');">Force Rebuild Table</a>
        </p>
        <p class="description">Use "Update" to attempt to fix table issues. Use "Force Rebuild" only if update fails - this will delete all cached data.</p>
        
        <hr>
        
        <h2>Diagnostic Tools</h2>
        <p>Use these tools to troubleshoot issues with the plugin.</p>
            
        <table class="widefat" style="margin-top: 10px;">
            <thead>
                <tr>
                    <th>Tool</th>
                    <th>Description</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Test Connection</td>
                    <td>Test connection to Amazon.</td>
                    <td>
                        <button id="ps-test-connection" class="button">Run Test</button>
                        <span id="ps-test-connection-result" style="margin-left: 10px;"></span>
                    </td>
                </tr>
                <tr>
                    <td>Test DNS</td>
                    <td>Test DNS resolution for Amazon's domain.</td>
                    <td>
                        <button id="ps-test-dns" class="button">Run Test</button>
                        <span id="ps-test-dns-result" style="margin-left: 10px;"></span>
                    </td>
                </tr>
                <tr>
                    <td>View Error Log</td>
                    <td>View the plugin's error log.</td>
                    <td>
                        <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=primates-shoppers&action=view_error_log'), 'ps_view_log'); ?>" class="button">View Log</a>
                    </td>
                </tr>
                <tr>
                    <td>View Response Samples</td>
                    <td>View saved Amazon response samples.</td>
                    <td>
                        <a href="<?php echo wp_nonce_url(admin_url('options-general.php?page=primates-shoppers&action=view_samples'), 'ps_view_samples'); ?>" class="button">View Samples</a>
                    </td>
                </tr>
                <tr>
                    <td>Clear Cache</td>
                    <td>Clear all cached search results.</td>
                    <td>
                        <button id="ps-clear-cache" class="button">Clear Cache</button>
                        <span id="ps-clear-cache-result" style="margin-left: 10px;"></span>
                    </td>
                </tr>
                <tr>
                    <td>Amazon Proxy Test</td>
                    <td>Test live Amazon product search via proxy (recommended).</td>
                    <td>
                        <a href="<?php echo admin_url('options-general.php?page=ps-proxy-test'); ?>" class="button">Open Proxy Test Tool</a>
                    </td>
                </tr>
                <tr>
                    <td>HTML Parsing Test</td>
                    <td>Test the HTML parsing functionality for extracting products.</td>
                    <td>
                        <a href="#" id="ps-parsing-test-button" class="button">Run Parsing Test</a>
                        <span id="ps-parsing-test-result" style="margin-left: 10px;"></span>
                    </td>
                </tr>
                <tr>
                    <td>Check Cache Table</td>
                    <td>Analyze the cache table contents and user_id values.</td>
                    <td>
                        <button id="ps-check-cache-table" class="button">Check Table</button>
                        <div id="ps-check-cache-table-result" style="margin-top: 10px;"></div>
                    </td>
                </tr>
                <tr>
                    <td>Test Cache Insertion</td>
                    <td>Perform a test search to trigger cache insertion and check user_id handling.</td>
                    <td>
                        <button id="ps-test-cache-insertion" class="button">Test Cache</button>
                        <span id="ps-test-cache-insertion-result" style="margin-left: 10px;"></span>
                    </td>
                </tr>
                <tr>
                    <td>Check Cache Table</td>
                    <td>Analyze the cache table contents and user_id values.</td>
                    <td>
                        <button id="ps-check-cache-table" class="button">Check Table</button>
                        <div id="ps-check-cache-table-result" style="margin-top: 10px;"></div>
                    </td>
                </tr>
                <tr>
                    <td>Test Cache Insertion</td>
                    <td>Perform a test search to trigger cache insertion and check user_id handling.</td>
                    <td>
                        <button id="ps-test-cache-insertion" class="button">Test Cache</button>
                        <span id="ps-test-cache-insertion-result" style="margin-left: 10px;"></span>
                    </td>
                </tr>
            </tbody>
            </table>
    </div>
            
            <script>
                jQuery(document).ready(function($) {
                    $('#ps-test-connection').on('click', function() {
                        var $button = $(this);
            var $result = $('#ps-test-connection-result');
                        
            $button.prop('disabled', true);
            $result.html('Testing...');
                        
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                            action: 'ps_test_connection',
                            nonce: '<?php echo wp_create_nonce('ps_test_connection'); ?>'
                },
                success: function(response) {
                            if (response.success) {
                        $result.html('<span style="color: green;">' + response.data.message + '</span>');
                            } else {
                        $result.html('<span style="color: red;">' + response.data.message + '</span>');
                            }
                },
                error: function() {
                    $result.html('<span style="color: red;">AJAX error. Please try again.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
                        });
                    });
                    
                    $('#ps-test-dns').on('click', function() {
                        var $button = $(this);
            var $result = $('#ps-test-dns-result');
                        
            $button.prop('disabled', true);
            $result.html('Testing...');
                        
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                            action: 'ps_test_dns',
                            nonce: '<?php echo wp_create_nonce('ps_test_dns'); ?>'
                },
                success: function(response) {
                            if (response.success) {
                        $result.html('<span style="color: green;">' + response.data.message + ' (' + response.data.ip + ')</span>');
                            } else {
                        $result.html('<span style="color: red;">' + response.data.message + '</span>');
                            }
                },
                error: function() {
                    $result.html('<span style="color: red;">AJAX error. Please try again.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
                    });
                });
        
        $('#ps-clear-cache').on('click', function() {
            var $button = $(this);
            var $result = $('#ps-clear-cache-result');
            
            $button.prop('disabled', true);
            $result.html('Clearing cache...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_clear_cache',
                    nonce: '<?php echo wp_create_nonce('ps_clear_cache'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: red;">' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">AJAX error. Please try again.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
                 });
        // Parsing Test Button Handler
        $('#ps-parsing-test-button').on('click', function(e) {
            e.preventDefault();
            console.log('Parsing test button clicked');
            
            // Show a loading message
            $('#ps-parsing-test-result').html('<span style="color: blue;">Loading test UI...</span>');
            
            // Create a dedicated section for the parsing test below the table
            if ($('#ps-admin-parsing-test-container').length === 0) {
                var testContainer = $('<div id="ps-admin-parsing-test-container" style="margin-top: 20px; border: 1px solid #ddd; padding: 20px; background: #fff;">' + 
                                     '<h2>HTML Parsing Test Tool</h2>' +
                                     '<p>This tool allows you to test the HTML parsing functionality used to extract products from Amazon search results.</p>' +
                                     '</div>');
                
                testContainer.insertAfter('.widefat');
                
                // Add a simple form
                var formHtml = '<div class="ps-parsing-test-container">' +
                           '<div class="ps-test-options">' +
                           '<div class="ps-test-source-selector">' +
                           '<label>HTML Source:</label>' +
                           '<select id="ps-source-type">' +
                           '<option value="file">HTML File</option>' +
                           '<option value="url">Amazon URL</option>' +
                           '<option value="text">Custom HTML</option>' +
                           '</select>' +
                           '</div>' +
                           '<div id="ps-file-source" class="ps-source-option">' +
                           '<label>Select HTML File:</label>' +
                           '<select id="ps-file-path">';
                
                // Get HTML files from logs directory
                <?php 
                $logs_dir = PS_PLUGIN_DIR . "logs/";
                $html_files = [];
                if (is_dir($logs_dir)) {
                    $files = scandir($logs_dir);
                    foreach ($files as $file) {
                        if (strpos($file, ".html") !== false) {
                            echo "formHtml += '<option value=\"" . esc_attr($file) . "\">" . esc_html($file) . "</option>';\n";
                        }
                    }
                }
                ?>
                
                formHtml += '</select>' +
                           '</div>' +
                           '<div id="ps-url-source" class="ps-source-option" style="display:none;">' +
                           '<label>Amazon URL:</label>' +
                           '<input type="text" id="ps-url" placeholder="https://www.amazon.com/s?k=..." />' +
                           '</div>' +
                           '<div id="ps-text-source" class="ps-source-option" style="display:none;">' +
                           '<label>Custom HTML:</label>' +
                           '<textarea id="ps-html-content" rows="8" placeholder="Paste HTML content here..."></textarea>' +
                           '</div>' +
                           '<div class="ps-test-country">' +
                           '<label>Amazon Region:</label>' +
                           '<select id="ps-country">' +
                           '<option value="us">United States</option>' +
                           '<option value="ca">Canada</option>' +
                           '</select>' +
                           '</div>' +
                           '<div class="ps-test-actions">' +
                           '<button id="ps-run-test" class="button button-primary">Run Parsing Test</button>' +
                           '</div>' +
                           '</div>' +
                           '<div id="ps-parsing-results" style="display:none;"></div>' +
                           '</div>';
                
                testContainer.append(formHtml);
                
                // Hide the loading message
                $('#ps-parsing-test-result').html('');
                
                // Initialize the source type change handler
                $('#ps-source-type').on('change', function() {
                    var sourceType = $(this).val();
                    $('.ps-source-option').hide();
                    $('#ps-' + sourceType + '-source').show();
                });
                
                // Initialize the run test button handler with real parsing functionality
                $('#ps-run-test').on('click', function() {
                    var sourceType = $('#ps-source-type').val();
                    var country = $('#ps-country').val();
                    
                    var data = {
                        action: 'ps_test_parsing',
                        nonce: '<?php echo wp_create_nonce("ps_parsing_test_nonce"); ?>',
                        source_type: sourceType,
                        country: country
                    };
                    
                    // Add source-specific data
                    if (sourceType === 'file') {
                        var filePath = $('#ps-file-path').val();
                        if (!filePath) {
                            alert('Please select a file to test.');
                            return;
                        }
                        data.file_path = filePath;
                    } else if (sourceType === 'url') {
                        var url = $('#ps-url').val();
                        if (!url) {
                            alert('Please enter a URL to test.');
                            return;
                        }
                        data.url = url;
                    } else if (sourceType === 'text') {
                        var htmlContent = $('#ps-html-content').val();
                        if (!htmlContent) {
                            alert('Please enter HTML content to test.');
                            return;
                        }
                        data.html_content = htmlContent;
                    }
                    
                    // Show loading state
                    $('#ps-run-test').prop('disabled', true).text('Testing...');
                    $('#ps-parsing-results').html('<p>Processing... Please wait.</p>').show();
                    
                    // Send AJAX request to perform the actual parsing test
                    $.post(ajaxurl, data, function(response) {
                        $('#ps-run-test').prop('disabled', false).text('Run Parsing Test');
                        
                        if (response.success) {
                            displayResults(response.data);
                        } else {
                            $('#ps-parsing-results').html('<p style="color: red;">Error: ' + 
                                (response.data?.message || 'Unknown error occurred') + '</p>');
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        $('#ps-run-test').prop('disabled', false).text('Run Parsing Test');
                        $('#ps-parsing-results').html('<p style="color: red;">AJAX Error: ' + textStatus + ' - ' + errorThrown + '</p>');
                        console.error('Parsing test AJAX error:', {
                            jqXHR: jqXHR,
                            textStatus: textStatus,
                            errorThrown: errorThrown
                        });
                    });
                    
                    // Define the displayResults function to show parsing results
                    function displayResults(data) {
                        // Build the results HTML
                        var resultsHtml = '<h4>Parsing Test Results</h4>';
                        
                        // Amazon blocking detection
                        resultsHtml += '<div class="ps-results-summary">';
                        resultsHtml += '<div class="ps-result-item">';
                        resultsHtml += '<span class="ps-result-label">Amazon Blocking Detection:</span>';
                        var blockingStatus = data.amazon_blocking ? 
                            '<span style="color: red;">YES - Amazon is blocking requests</span>' : 
                            '<span style="color: green;">No - Amazon is not blocking</span>';
                        resultsHtml += '<span class="ps-result-value">' + blockingStatus + '</span>';
                        resultsHtml += '</div>';
                        
                        // XPath selectors
                        resultsHtml += '<div class="ps-result-item">';
                        resultsHtml += '<span class="ps-result-label">XPath Selectors:</span>';
                        resultsHtml += '<div class="ps-result-value">';
                        
                        for (var selector in data.xpath_results.selector_counts) {
                            var count = data.xpath_results.selector_counts[selector];
                            var isSelected = (selector === data.xpath_results.selected_selector);
                            var style = isSelected ? 'color: green; font-weight: bold;' : '';
                            resultsHtml += '<div style="' + style + '">' + 
                                selector + ': <span class="count">' + count + '</span>' +
                                (isSelected ? ' (Selected)' : '') +
                                '</div>';
                        }
                        
                        resultsHtml += '</div>'; // End ps-result-value
                        resultsHtml += '</div>'; // End ps-result-item
                        
                        // Alternative XPath selectors
                        resultsHtml += '<div class="ps-result-item">';
                        resultsHtml += '<span class="ps-result-label">Alternative XPath Selectors:</span>';
                        resultsHtml += '<div class="ps-result-value">';
                        
                        for (var selector in data.xpath_results.alternative_selector_counts) {
                            var count = data.xpath_results.alternative_selector_counts[selector];
                            resultsHtml += '<div>' + 
                                selector + ': <span class="count">' + count + '</span>' +
                                '</div>';
                        }
                        
                        resultsHtml += '</div>'; // End ps-result-value
                        resultsHtml += '</div>'; // End ps-result-item
                        
                        // XML parsing errors
                        resultsHtml += '<div class="ps-result-item">';
                        resultsHtml += '<span class="ps-result-label">XML Parsing Issues:</span>';
                        
                        var xmlErrorCount = data.xpath_results.xml_errors;
                        var xmlErrorsHtml = xmlErrorCount + ' errors';
                        
                        if (xmlErrorCount > 0 && data.xpath_results.xml_error_samples.length > 0) {
                            xmlErrorsHtml += ' (Samples: ';
                            xmlErrorsHtml += data.xpath_results.xml_error_samples.join(', ');
                            xmlErrorsHtml += ')';
                        }
                        
                        resultsHtml += '<span class="ps-result-value">' + xmlErrorsHtml + '</span>';
                        resultsHtml += '</div>'; // End ps-result-item
                        
                        resultsHtml += '</div>'; // End ps-results-summary
                        
                        // Sample product data (show all fields, not just titles)
                        if (data.xpath_results.sample_products && data.xpath_results.sample_products.length > 0) {
                            resultsHtml += '<div class="ps-sample-products">';
                            resultsHtml += '<h4>Sample Products</h4>';

                            // Build a table of all fields for all products
                            var products = data.xpath_results.sample_products;
                            var allKeys = new Set();
                            products.forEach(function(product) {
                                Object.keys(product).forEach(function(key) { allKeys.add(key); });
                            });
                            // Prioritize some fields
                            var priorityFields = ['title', 'title_extraction_method', 'brand'];
                            var otherFields = Array.from(allKeys).filter(function(key) { return priorityFields.indexOf(key) === -1; }).sort();
                            var orderedFields = priorityFields.concat(otherFields);

                            resultsHtml += '<table class="ps-product-table"><thead><tr><th>Field</th>';
                            products.forEach(function(_, idx) {
                                resultsHtml += '<th>Product ' + (idx + 1) + '</th>';
                            });
                            resultsHtml += '</tr></thead><tbody>';

                            orderedFields.forEach(function(key) {
                                var isHighlighted = (key === 'title' || key === 'title_extraction_method');
                                var rowStyle = isHighlighted ? 'background-color: #f8f9fa; font-weight: bold;' : '';
                                resultsHtml += '<tr style="' + rowStyle + '"><td><strong>' + key + '</strong></td>';
                                products.forEach(function(product) {
                                    var value = product[key] || '';
                                    if (key === 'image' && value) {
                                        value = '<img src="' + value + '" style="max-height: 50px;" alt="Product image">';
                                    } else if (key === 'link' && value) {
                                        value = '<a href="' + value + '" target="_blank">View</a>';
                                    } else if (key === 'price' && value) {
                                        value = '<span style="color: #e63946;">' + value + '</span>';
                                    } else if (key === 'title_extraction_method' && value) {
                                        value = '<span style="color: #2a9d8f; font-weight: bold;">' + value + '</span>';
                                    }
                                    resultsHtml += '<td>' + value + '</td>';
                                });
                                resultsHtml += '</tr>';
                            });

                            resultsHtml += '</tbody></table>';
                            resultsHtml += '</div>';
                        }
                        
                        // HTML sample
                        resultsHtml += '<div class="ps-html-sample">';
                        resultsHtml += '<h4>HTML Sample</h4>';
                        resultsHtml += '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 200px;">' + 
                            data.html_sample + '</pre>';
                        resultsHtml += '</div>';
                        
                        // Update the results container
                        $('#ps-parsing-results').html(resultsHtml);
                    }
                });
            } else {
                $('#ps-admin-parsing-test-container').toggle();
                $('#ps-parsing-test-result').html('');
            }
        });
        
        $('#ps-check-cache-table').on('click', function() {
            var $button = $(this);
            var $result = $('#ps-check-cache-table-result');
            
            $button.prop('disabled', true);
            $result.html('Checking cache table...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_check_cache_table',
                    nonce: '<?php echo wp_create_nonce('ps_check_cache_table'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html(response.data.html);
                    } else {
                        $result.html('<span style="color: red;">' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">Error checking cache table.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        $('#ps-check-cache-table').on('click', function() {
            var $button = $(this);
            var $result = $('#ps-check-cache-table-result');
            
            $button.prop('disabled', true);
            $result.html('Checking cache table...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_check_cache_table',
                    nonce: '<?php echo wp_create_nonce('ps_check_cache_table'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html(response.data.html);
                    } else {
                        $result.html('<span style="color: red;">' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">Error checking cache table.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        $('#ps-test-cache-insertion').on('click', function() {
            var $button = $(this);
            var $result = $('#ps-test-cache-insertion-result');
            
            $button.prop('disabled', true);
            $result.html('Testing cache insertion...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ps_test_cache_insertion',
                    nonce: '<?php echo wp_create_nonce('ps_test_cache_insertion'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">' + response.data.message + '</span>');
                    } else {
                        $result.html('<span style="color: red;">' + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">Error testing cache insertion.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Clear all logs
 */
function ps_clear_logs() {
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    
    // Clear error log
    $error_log_file = $logs_dir . '/error_log.txt';
    if (file_exists($error_log_file)) {
        unlink($error_log_file);
    }
    
    // Clear response samples
    $response_samples = glob($logs_dir . '/amazon_response_*.html');
    foreach ($response_samples as $file) {
        unlink($file);
    }
}

// The parsing test is now integrated directly into the main settings page