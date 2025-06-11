<?php

// Include necessary constants and WordPress mocks first
define('PS_PLUGIN_DIR', __DIR__ . '/');

// Override ps_log_error to output to console instead of file (only if not already defined)
if (!function_exists('ps_log_error')) {
    function ps_log_error($message) {
        echo "[LOG] " . $message . PHP_EOL;
    }
}

// Mock some WordPress functions
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) { return true; }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return $str; }
}
if (!function_exists('get_option')) {
    function get_option($option, $default = false) { return $default; }
}

// Include the Amazon API functions
require_once 'includes/amazon-api.php';

echo 'Testing carousel parsing for Coody product...' . PHP_EOL;

// Test with the actual HTML content
$html_file = 'logs/amazon_response_2025-05-29_23-16-47.html';
if (!file_exists($html_file)) {
    echo 'ERROR: HTML file not found: ' . $html_file . PHP_EOL;
    exit;
}

$html = file_get_contents($html_file);
echo 'HTML file size: ' . strlen($html) . ' bytes' . PHP_EOL;

// Look specifically for carousel elements in the HTML
echo PHP_EOL . 'Checking for carousel elements in HTML...' . PHP_EOL;
$carousel_count = substr_count($html, 'a-carousel-card');
echo "Found 'a-carousel-card' mentions: $carousel_count" . PHP_EOL;

$sponsored_count = substr_count($html, 'Sponsored Ad');
echo "Found 'Sponsored Ad' mentions: $sponsored_count" . PHP_EOL;

$coody_mentions = substr_count($html, 'Coody');
echo "Found 'Coody' mentions: $coody_mentions" . PHP_EOL;

// Parse the results
$products = ps_parse_amazon_results($html, 'test-tag', 0, 'ca');

echo PHP_EOL . '=== PARSING RESULTS ===' . PHP_EOL;
echo 'Success: ' . ($products['success'] ? 'YES' : 'NO') . PHP_EOL;
echo 'Total products found: ' . count($products['items']) . PHP_EOL;

// Look specifically for Coody product
$coody_found = false;
foreach ($products['items'] as $product) {
    if (stripos($product['title'], 'Coody') !== false || stripos($product['title'], 'Aurora Dome') !== false) {
        $coody_found = true;
        echo PHP_EOL . '🎉 FOUND Coody product!' . PHP_EOL;
        echo 'Title: ' . $product['title'] . PHP_EOL;
        echo 'Price: ' . $product['price'] . PHP_EOL;
        echo 'ASIN: ' . $product['asin'] . PHP_EOL;
        echo 'Parsing method: ' . $product['parsing_method'] . PHP_EOL;
        echo 'Link: ' . substr($product['link'], 0, 80) . '...' . PHP_EOL;
        break;
    }
}

if (!$coody_found) {
    echo PHP_EOL . '❌ Coody product NOT found in results' . PHP_EOL;
    echo 'First few products found:' . PHP_EOL;
    $count = 0;
    foreach ($products['items'] as $product) {
        if ($count >= 5) break;
        echo sprintf("  %d. %s... [%s]", 
            $count + 1, 
            substr($product['title'], 0, 60), 
            $product['parsing_method']
        ) . PHP_EOL;
        $count++;
    }
}

// Show parsing methods used
$methods = array();
foreach ($products['items'] as $product) {
    $method = $product['parsing_method'] ?? 'unknown';
    if (!isset($methods[$method])) {
        $methods[$method] = 0;
    }
    $methods[$method]++;
}

echo PHP_EOL . 'Parsing methods used:' . PHP_EOL;
foreach ($methods as $method => $count) {
    echo "  $method: $count products" . PHP_EOL;
}

echo PHP_EOL . 'Test completed.' . PHP_EOL;
?> 