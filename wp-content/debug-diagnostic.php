<?php
/**
 * Diagnostic script for AI Engine constant conflicts
 * REMOVE THIS FILE AFTER DIAGNOSIS
 */

// Security check
if (!defined('ABSPATH')) {
    exit('Direct access not allowed');
}

echo "<h2>AI Engine Plugin Diagnostic</h2>";

// Check if constants are already defined
$constants_to_check = [
    'MWAI_VERSION',
    'MWAI_PREFIX', 
    'MWAI_DOMAIN',
    'MWAI_ENTRY',
    'MWAI_PATH',
    'MWAI_URL',
    'MWAI_ITEM_ID',
    'MWAI_FALLBACK_MODEL',
    'MWAI_FALLBACK_MODEL_VISION',
    'MWAI_FALLBACK_MODEL_JSON'
];

echo "<h3>Constant Status:</h3>";
foreach ($constants_to_check as $constant) {
    $status = defined($constant) ? 'DEFINED' : 'NOT DEFINED';
    $value = defined($constant) ? constant($constant) : 'N/A';
    echo "<p><strong>{$constant}:</strong> {$status} (Value: {$value})</p>";
}

// Check active plugins
echo "<h3>Active Plugins:</h3>";
$active_plugins = get_option('active_plugins');
foreach ($active_plugins as $plugin) {
    echo "<p>{$plugin}</p>";
    if (strpos($plugin, 'ai-engine') !== false) {
        echo "<strong>↑ AI Engine plugin found</strong><br>";
    }
}

// Check if AI Engine is loaded multiple times
echo "<h3>AI Engine File Checks:</h3>";
$ai_engine_path = WP_PLUGIN_DIR . '/ai-engine/ai-engine.php';
echo "<p>AI Engine main file exists: " . (file_exists($ai_engine_path) ? 'YES' : 'NO') . "</p>";

if (file_exists($ai_engine_path)) {
    echo "<p>AI Engine file size: " . filesize($ai_engine_path) . " bytes</p>";
    echo "<p>AI Engine last modified: " . date('Y-m-d H:i:s', filemtime($ai_engine_path)) . "</p>";
}

// Check for duplicate plugin directories
$plugin_dirs = glob(WP_PLUGIN_DIR . '/*ai-engine*', GLOB_ONLYDIR);
echo "<h3>AI Engine Directories Found:</h3>";
foreach ($plugin_dirs as $dir) {
    echo "<p>" . basename($dir) . "</p>";
}

echo "<hr><p><small>Diagnostic complete. Please remove this file after reviewing.</small></p>";
?> 