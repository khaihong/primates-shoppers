<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting sponsored product test...\n";

// Test script to verify sponsored product parsing
require_once 'includes/amazon-api.php';

echo "Included amazon-api.php\n";

// Read the HTML file
$html_file = 'logs/amazon_response_2025-05-30_20-47-26.html';
echo "Looking for file: $html_file\n";

if (!file_exists($html_file)) {
    echo "HTML file not found: $html_file\n";
    exit(1);
}

$html = file_get_contents($html_file);
echo "Loaded HTML file: " . strlen($html) . " characters\n";

echo "Starting parsing...\n";

// Test parsing
$products = ps_parse_amazon_results($html, 'test-affiliate-tag', 4.0, 'us');

echo "\nTotal products parsed: " . count($products) . "\n\n";

// Look for the specific products mentioned
$found_vidalido = false;
$found_fanttik = false;

foreach ($products as $product) {
    if (stripos($product['title'], 'Vidalido') !== false) {
        echo "✓ FOUND Vidalido product:\n";
        echo "  Title: " . $product['title'] . "\n";
        echo "  Price: " . $product['price'] . "\n";
        echo "  Link: " . substr($product['link'], 0, 80) . "...\n";
        echo "  Method: " . ($product['parsing_method'] ?? 'unknown') . "\n\n";
        $found_vidalido = true;
    }
    
    if (stripos($product['title'], 'FanttikOutdoor') !== false) {
        echo "✓ FOUND FanttikOutdoor product:\n";
        echo "  Title: " . $product['title'] . "\n";
        echo "  Price: " . $product['price'] . "\n";
        echo "  Link: " . substr($product['link'], 0, 80) . "...\n";
        echo "  Method: " . ($product['parsing_method'] ?? 'unknown') . "\n\n";
        $found_fanttik = true;
    }
}

if (!$found_vidalido) {
    echo "❌ Vidalido product NOT found\n";
}

if (!$found_fanttik) {
    echo "❌ FanttikOutdoor product NOT found\n";
}

echo "\n=== Summary ===\n";
echo "Vidalido: " . ($found_vidalido ? "FOUND" : "NOT FOUND") . "\n";
echo "FanttikOutdoor: " . ($found_fanttik ? "FOUND" : "NOT FOUND") . "\n";
echo "Total products: " . count($products) . "\n";
?> 