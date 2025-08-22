<?php
/**
 * Debug Helper for Primates Shoppers
 * Helps identify and fix critical errors
 */



// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if all required files exist
 */
function ps_check_required_files() {
    $required_files = array(
        'blocks/blocks.css',
        'blocks/index-built.js',
        'blocks/hero-section/block.json',
        'blocks/value-proposition/block.json',
        'blocks/search-section/block.json',
        'blocks/testimonials/block.json',
    );
    
    $missing_files = array();
    
    foreach ($required_files as $file) {
        $full_path = PS_PLUGIN_DIR . $file;
        if (!file_exists($full_path)) {
            $missing_files[] = $file;
        }
    }
    
    return $missing_files;
}

/**
 * Check WordPress version and required functions
 */
function ps_check_wordpress_compatibility() {
    $requirements = array(
        'wordpress_version' => '5.0',
        'required_functions' => array(
            'register_block_type',
            'register_block_pattern',
            'wp_enqueue_script',
            'add_action',
        ),
    );
    
    $issues = array();
    
    // Check WordPress version
    global $wp_version;
    if (version_compare($wp_version, $requirements['wordpress_version'], '<')) {
        $issues[] = "WordPress version {$wp_version} is too old. Requires {$requirements['wordpress_version']} or higher.";
    }
    
    // Check required functions
    foreach ($requirements['required_functions'] as $function) {
        if (!function_exists($function)) {
            $issues[] = "Required function '{$function}' is not available.";
        }
    }
    
    return $issues;
}

/**
 * Safe initialization with error handling
 */
function ps_safe_init() {
    try {
        // Check compatibility first
        $compatibility_issues = ps_check_wordpress_compatibility();
        if (!empty($compatibility_issues)) {
            error_log('Primates Shoppers: Compatibility issues found: ' . implode(', ', $compatibility_issues));
            return false;
        }
        
        // Check required files
        $missing_files = ps_check_required_files();
        if (!empty($missing_files)) {
            error_log('Primates Shoppers: Missing files: ' . implode(', ', $missing_files));
            // Continue anyway - blocks may still work partially
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log('Primates Shoppers: Initialization error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Admin notice for critical errors - fixed to prevent headers already sent
 */
function ps_show_admin_notice() {
    // Only show notices in admin area and after WordPress is fully loaded
    if (!is_admin() || !function_exists('current_user_can') || !current_user_can('manage_options')) {
        return;
    }
    
    // Prevent output if headers have already been sent
    if (headers_sent()) {
        error_log('Primates Shoppers: Cannot show admin notice - headers already sent');
        return;
    }
    
    try {
        $compatibility_issues = ps_check_wordpress_compatibility();
        $missing_files = ps_check_required_files();
        
        if (!empty($compatibility_issues) || !empty($missing_files)) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Primates Shoppers:</strong> Some features may not work properly.</p>';
            
            if (!empty($compatibility_issues)) {
                echo '<ul>';
                foreach ($compatibility_issues as $issue) {
                    echo '<li>' . esc_html($issue) . '</li>';
                }
                echo '</ul>';
            }
            
            if (!empty($missing_files)) {
                echo '<p>Missing files: ' . esc_html(implode(', ', $missing_files)) . '</p>';
            }
            
            echo '</div>';
        }
    } catch (Exception $e) {
        error_log('Primates Shoppers: Error in admin notice: ' . $e->getMessage());
    }
}

// Initialize debug helper safely - DISABLED FOR NOW TO PREVENT HEADER ISSUES
// if (function_exists('add_action') && is_admin()) {
//     // Use a late hook to ensure WordPress is fully loaded
//     add_action('admin_notices', 'ps_show_admin_notice', 20);
// }
?>