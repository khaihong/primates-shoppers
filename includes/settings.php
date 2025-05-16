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
    $cache_duration = isset($settings['cache_duration']) ? $settings['cache_duration'] : 3600;
    
    // Display the settings form
    ?>
    <div class="wrap">
        <h1>Primates Shoppers Settings</h1>
        
            <form method="post" action="">
            <?php wp_nonce_field('ps_save_settings'); ?>
            
                <table class="form-table">
                    <tr>
                    <th scope="row"><label for="ps_settings_amazon_associate_tag">Amazon Associate Tag</label></th>
                        <td>
                        <input type="text" id="ps_settings_amazon_associate_tag" name="ps_settings[amazon_associate_tag]" value="<?php echo esc_attr($amazon_associate_tag); ?>" class="regular-text">
                        <p class="description">Your Amazon Associate tag for affiliate links.</p>
                        </td>
                    </tr>
                    <tr>
                    <th scope="row"><label for="ps_settings_cache_duration">Cache Duration</label></th>
                        <td>
                        <input type="number" id="ps_settings_cache_duration" name="ps_settings[cache_duration]" value="<?php echo esc_attr($cache_duration); ?>" class="regular-text">
                        <p class="description">How long to cache search results (in seconds). Default: 3600 (1 hour).</p>
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