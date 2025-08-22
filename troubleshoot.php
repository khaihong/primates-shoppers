<?php
/**
 * Simple troubleshooting script for Primates Shoppers
 * Test if the plugin can load without causing critical errors
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Primates Shoppers Troubleshooting</h1>";

// Test 1: Basic WordPress functions
echo "<h2>Test 1: WordPress Environment</h2>";
if (function_exists('wp_get_version')) {
    echo "✅ WordPress version: " . wp_get_version() . "<br>";
} else {
    echo "❌ WordPress not properly loaded<br>";
}

// Test 2: Plugin constants
echo "<h2>Test 2: Plugin Constants</h2>";
if (defined('PS_PLUGIN_DIR')) {
    echo "✅ PS_PLUGIN_DIR: " . PS_PLUGIN_DIR . "<br>";
} else {
    echo "❌ PS_PLUGIN_DIR not defined<br>";
}

if (defined('PS_PLUGIN_URL')) {
    echo "✅ PS_PLUGIN_URL: " . PS_PLUGIN_URL . "<br>";
} else {
    echo "❌ PS_PLUGIN_URL not defined<br>";
}

// Test 3: Required functions
echo "<h2>Test 3: Required WordPress Functions</h2>";
$required_functions = array(
    'register_block_type',
    'register_block_pattern',
    'wp_enqueue_script',
    'add_action',
    'wp_get_version'
);

foreach ($required_functions as $function) {
    if (function_exists($function)) {
        echo "✅ $function exists<br>";
    } else {
        echo "❌ $function missing<br>";
    }
}

// Test 4: Check if files exist
echo "<h2>Test 4: Required Files</h2>";
$required_files = array(
    'includes/debug-helper.php',
    'includes/blocks.php',
    'includes/block-patterns.php',
    'blocks/blocks.css',
    'blocks/index-built.js',
);

foreach ($required_files as $file) {
    $full_path = PS_PLUGIN_DIR . $file;
    if (file_exists($full_path)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

// Test 5: Try to load debug helper
echo "<h2>Test 5: Load Debug Helper</h2>";
try {
    if (function_exists('ps_check_wordpress_compatibility')) {
        echo "✅ Debug helper functions already loaded<br>";
        $issues = ps_check_wordpress_compatibility();
        if (empty($issues)) {
            echo "✅ No WordPress compatibility issues<br>";
        } else {
            echo "⚠️ WordPress compatibility issues:<br>";
            foreach ($issues as $issue) {
                echo "- " . esc_html($issue) . "<br>";
            }
        }
    } else {
        echo "⚠️ Debug helper functions not loaded yet<br>";
    }
} catch (Exception $e) {
    echo "❌ Error loading debug helper: " . $e->getMessage() . "<br>";
}

// Test 6: Check for block support
echo "<h2>Test 6: Block Editor Support</h2>";
if (function_exists('register_block_type')) {
    echo "✅ Block registration supported<br>";
} else {
    echo "❌ Block registration not supported<br>";
}

if (function_exists('register_block_pattern')) {
    echo "✅ Block patterns supported<br>";
} else {
    echo "❌ Block patterns not supported<br>";
}

// Test 7: Recent error log entries
echo "<h2>Test 7: Recent Error Log</h2>";
$debug_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log)) {
    $log_content = file_get_contents($debug_log);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -10); // Last 10 lines
    
    echo "<pre style='background: #f0f0f0; padding: 10px; max-height: 200px; overflow-y: scroll;'>";
    foreach ($recent_lines as $line) {
        if (stripos($line, 'primates') !== false || stripos($line, 'fatal') !== false || stripos($line, 'error') !== false) {
            echo "<strong style='color: red;'>" . esc_html($line) . "</strong>\n";
        } else {
            echo esc_html($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "ℹ️ No debug log found at $debug_log<br>";
}

echo "<h2>Troubleshooting Complete</h2>";
echo "<p>If you see critical errors when editing pages/posts, the issue might be:</p>";
echo "<ul>";
echo "<li>JavaScript errors in the browser console</li>";
echo "<li>Block editor conflicts with other plugins</li>";
echo "<li>Theme compatibility issues</li>";
echo "<li>WordPress version too old for Gutenberg blocks</li>";
echo "</ul>";
echo "<p><strong>Next steps:</strong> Check browser developer console for JavaScript errors when editing pages.</p>";
?> 