<?php
/**
 * Parsing test functionality for Primates Shoppers plugin
 * Allows testing of the HTML parsing functionality used to extract products
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register AJAX action for parsing test
 */
function ps_register_parsing_test_action() {
    add_action('wp_ajax_ps_test_parsing', 'ps_ajax_test_parsing');
    add_action('wp_ajax_nopriv_ps_test_parsing', 'ps_ajax_test_parsing');
}
add_action('init', 'ps_register_parsing_test_action');

/**
 * Handle AJAX request for parsing test
 */
function ps_ajax_test_parsing() {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    // Log the start of the function
    error_log('Starting parsing test...');
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ps_parsing_test_nonce')) {
        error_log('Security check failed - nonce verification failed');
        wp_send_json_error(['message' => 'Security check failed.']);
    }
    
    // Get source type and content
    $source_type = isset($_POST['source_type']) ? sanitize_text_field($_POST['source_type']) : 'file';
    $html = '';
    
    error_log('Source type: ' . $source_type);
    
    if ($source_type === 'file' && isset($_POST['file_path'])) {
        $file_path = sanitize_text_field($_POST['file_path']);
        $full_path = PS_PLUGIN_DIR . 'logs/' . $file_path;
        error_log('Attempting to read file: ' . $full_path);
        
        if (file_exists($full_path) && is_readable($full_path)) {
            $html = file_get_contents($full_path);
            error_log('File read successfully, length: ' . strlen($html));
        } else {
            error_log('File not found or not readable: ' . $full_path);
            wp_send_json_error(['message' => 'File not found or not readable.']);
        }
    } elseif ($source_type === 'url' && isset($_POST['url'])) {
        $url = sanitize_url($_POST['url']);
        error_log('Attempting to fetch URL: ' . $url);
        
        $response = wp_remote_get($url);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $html = wp_remote_retrieve_body($response);
            error_log('URL fetched successfully, length: ' . strlen($html));
        } else {
            error_log('Failed to fetch URL: ' . $url);
            wp_send_json_error(['message' => 'Failed to fetch URL.']);
        }
    } elseif ($source_type === 'text' && isset($_POST['html_content'])) {
        $html = $_POST['html_content']; // Deliberately not sanitizing to preserve HTML
        error_log('Received HTML content, length: ' . strlen($html));
    } else {
        error_log('Invalid source type or missing required parameters');
        wp_send_json_error(['message' => 'Invalid source type or missing required parameters.']);
    }
    
    if (empty($html)) {
        error_log('No HTML content to parse');
        wp_send_json_error(['message' => 'No HTML content to parse.']);
    }
    
    // Get the affiliate tag
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : 'us';
    $affiliate_id = ps_get_associate_tag($country);
    error_log('Using affiliate ID for country ' . $country . ': ' . $affiliate_id);
    
    // Save the original libxml error state
    $previous_error_state = libxml_use_internal_errors(true);
    
    // Try to improve HTML by fixing encoding issues
    $html = ps_sanitize_html_for_parsing($html);
    error_log('HTML sanitized, new length: ' . strlen($html));
    
    // Apply bandwidth optimization if enabled (for testing purposes)
    $settings = get_option('ps_settings');
    $bandwidth_optimization = isset($settings['bandwidth_optimization']) ? $settings['bandwidth_optimization'] : 1;
    
    if ($bandwidth_optimization && $source_type !== 'text') {
        // Only apply optimization for file/URL sources, not custom text
        $original_size = strlen($html);
        $html = ps_extract_product_html($html);
        $optimized_size = strlen($html);
        error_log('Bandwidth optimization applied in parsing test - Original: ' . number_format($original_size) . ' bytes, Optimized: ' . number_format($optimized_size) . ' bytes');
    }
    
    // Load HTML into DOMDocument for XPath parsing
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    // Get libxml errors
    $xml_errors = libxml_get_errors();
    error_log('XML parsing errors: ' . count($xml_errors));
    foreach ($xml_errors as $error) {
        error_log('XML Error: ' . $error->message);
    }
    libxml_clear_errors();
    libxml_use_internal_errors($previous_error_state);
    
    // Use enhanced selectors for testing including US-specific sections
    $selectors = [
        '//div[@role="listitem"]', // Primary selector for regular results
        '//div[contains(@class, "a-section") and contains(@class, "a-spacing-base")]/div[contains(@class, "s-product-image-container")]/..' // US sections selector (trending/influencers)
    ];
    
    $xpath_product_counts = [];
    $selected_products_selector = '';
    $all_product_elements = [];
    
    foreach ($selectors as $selector) {
        $productElements = $xpath->query($selector);
        $count = ($productElements) ? $productElements->length : 0;
        $xpath_product_counts[$selector] = $count;
        error_log("Selector '$selector' found $count products");
        
        if ($count > 0) {
            if (empty($selected_products_selector)) {
                $selected_products_selector = $selector;
                // Always convert to array for consistency
                $all_product_elements = [];
                for ($i = 0; $i < $productElements->length; $i++) {
                    $all_product_elements[] = $productElements->item($i);
                }
            } else {
                // For US sections, add to existing products if we only had regular products
                if (strpos($selector, 's-product-image-container') !== false) {
                    // Add US section products to the existing array
                    for ($i = 0; $i < $productElements->length; $i++) {
                        $all_product_elements[] = $productElements->item($i);
                    }
                    $selected_products_selector .= " + US sections";
                }
            }
        }
    }
    
    // Define alternative selector sets
    $alt_selector_sets = [
        [
            'product' => '//div[@role="listitem"]', // Primary selector for regular results
            'title' => './/span[contains(@class, "a-text-normal")]',
            'link' => './/a[contains(@class, "a-link-normal")]/@href',
            'price' => './/span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]',
            'image' => './/img[contains(@class, "s-image")]/@src'
        ],
        [
            'product' => '//div[contains(@class, "a-section") and contains(@class, "a-spacing-base")]/div[contains(@class, "s-product-image-container")]/..', // US sections selector
            'title' => './/span[contains(@class, "a-text-normal")]',
            'link' => './/a[contains(@class, "a-link-normal")]/@href',
            'price' => './/span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]',
            'image' => './/img[contains(@class, "s-image")]/@src'
        ]
    ];
    
    $alt_xpath_product_counts = [];
    
    foreach ($alt_selector_sets as $index => $selectors) {
        $productElements = $xpath->query($selectors['product']);
        $count = ($productElements) ? $productElements->length : 0;
        $alt_xpath_product_counts["Alt Set #" . ($index + 1)] = $count;
    }
    
    // Check for Amazon blocking
    $is_blocking = ps_is_amazon_blocking($html);
    
    // Initialize response data
    $response = [
        'success' => true,
        'source_type' => $source_type,
        'debug_extraction' => [],
        'xpath_results' => [
            'selector_counts' => $xpath_product_counts,
            'alternative_selector_counts' => $alt_xpath_product_counts,
            'selected_selector' => $selected_products_selector,
            'xml_errors' => count($xml_errors),
            'xml_error_samples' => array_slice(array_map(function($error) {
                return $error->message;
            }, $xml_errors), 0, 5),
            'sample_products' => [],
            'debug_info' => isset($debug_info) ? $debug_info : []
        ],
        'amazon_blocking' => $is_blocking,
        'html_sample' => substr($html, 0, 500) . '...' // First 500 chars of HTML for debug
    ];
    
    // Extract some product titles for demonstration
    $sample_products = [];
    
    if (!empty($selected_products_selector)) {
        // $all_product_elements is always an array now
        $productCount = count($all_product_elements);
        
        if ($productCount > 0) {
            $count = min(10, $productCount); // Get up to 10 products for better testing
            error_log("Found $count products to process");
            
            // Attempt to extract real products instead of using test data
            for ($i = 0; $i < $count; $i++) {
                $element = $all_product_elements[$i];
                error_log("Processing product $i");
                
                // Add debugging info for this product
                $debug_extraction[$i] = [];
                
                // Extract title using multiple approaches and track which one succeeds
                $title = '';
                $title_extraction_method = '';
                
                // Method 1: h2 with aria-label
                $h2WithAriaLabel = $xpath->query('.//h2[@aria-label]', $element)->item(0);
                if ($h2WithAriaLabel) {
                    $aria_label = $h2WithAriaLabel->getAttribute('aria-label');
                    error_log("Product $i - Found h2 with aria-label: $aria_label");
                    $debug_extraction[$i]['h2_aria_label'] = $aria_label;
                    
                    $spanInsideH2 = $xpath->query('.//span', $h2WithAriaLabel)->item(0);
                    if ($spanInsideH2) {
                        $title = trim(preg_replace('/\s+/', ' ', $spanInsideH2->textContent));
                        $title_extraction_method = 'h2_aria_label_span';
                        error_log("Product $i - Found title in span inside h2: $title");
                        $debug_extraction[$i]['h2_span_content'] = $title;
                    } else {
                        // Fallback to h2's text content if span not found
                        $title = trim(preg_replace('/\s+/', ' ', $h2WithAriaLabel->textContent));
                        $title_extraction_method = 'h2_aria_label_text';
                        error_log("Product $i - Using h2 text content as title: $title");
                        $debug_extraction[$i]['h2_text_content'] = $title;
                    }
                }
                
                // Method 2: Standard a-text-normal selector (fallback)
                if (empty($title)) {
                    $titleNode = $xpath->query('.//span[contains(@class, "a-text-normal")]', $element)->item(0);
                    if ($titleNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
                        $title_extraction_method = 'a_text_normal';
                        $debug_extraction[$i]['a_text_normal'] = $title;
                    }
                }
                
                // Method 3: h2 span fallback
                if (empty($title)) {
                    $titleNode = $xpath->query('.//h2//span', $element)->item(0);
                    if ($titleNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
                        $title_extraction_method = 'h2_span';
                        $debug_extraction[$i]['h2_span'] = $title;
                    }
                }
                
                // Method 4: h2 fallback
                if (empty($title)) {
                    $titleNode = $xpath->query('.//h2', $element)->item(0);
                    if ($titleNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
                        $title_extraction_method = 'h2_text';
                        $debug_extraction[$i]['h2_text'] = $title;
                    }
                }
                
                // Method 5: Any a-link with a-text-normal span inside
                if (empty($title)) {
                    $linkNodes = $xpath->query('.//a[contains(@class, "a-link-normal")]', $element);
                    if ($linkNodes && $linkNodes->length > 0) {
                        // Try to find the one most likely to contain the title (first one or one with most text)
                        $bestTitle = '';
                        foreach ($linkNodes as $idx => $node) {
                            $spanNode = $xpath->query('.//span[contains(@class, "a-text-normal")]', $node)->item(0);
                            if ($spanNode) {
                                $nodeTitle = trim(preg_replace('/\s+/', ' ', $spanNode->textContent));
                                $debug_extraction[$i]['a_link_span_' . $idx] = $nodeTitle;
                                
                                // If this is the first one or longer than what we have, use it
                                if (empty($bestTitle) || strlen($nodeTitle) > strlen($bestTitle)) {
                                    $bestTitle = $nodeTitle;
                                }
                            }
                        }
                        
                        if (!empty($bestTitle)) {
                            $title = $bestTitle;
                            $title_extraction_method = 'a_link_span';
                            $debug_extraction[$i]['a_link_span_best'] = $bestTitle;
                        }
                    }
                }
                
                // Method 6: Any a-size-base-plus (commonly used for product names)
                if (empty($title)) {
                    $titleNodes = $xpath->query('.//span[contains(@class, "a-size-base-plus")]', $element);
                    if ($titleNodes && $titleNodes->length > 0) {
                        // Get the longest text (most likely to be the full title)
                        $bestTitle = '';
                        foreach ($titleNodes as $idx => $node) {
                            $nodeTitle = trim(preg_replace('/\s+/', ' ', $node->textContent));
                            $debug_extraction[$i]['a_size_base_plus_' . $idx] = $nodeTitle;
                            
                            // If this is the first one or longer than what we have, use it
                            if (empty($bestTitle) || strlen($nodeTitle) > strlen($bestTitle)) {
                                $bestTitle = $nodeTitle;
                            }
                        }
                        
                        if (!empty($bestTitle)) {
                            $title = $bestTitle;
                            $title_extraction_method = 'a_size_base_plus';
                            $debug_extraction[$i]['a_size_base_plus_best'] = $bestTitle;
                        }
                    }
                }
                
                // Method 7: a-color-base (sometimes contains title)
                if (empty($title)) {
                    $titleNodes = $xpath->query('.//span[contains(@class, "a-color-base")]', $element);
                    if ($titleNodes && $titleNodes->length > 0) {
                        // Get the longest text (most likely to be the full title)
                        $bestTitle = '';
                        foreach ($titleNodes as $idx => $node) {
                            $nodeTitle = trim(preg_replace('/\s+/', ' ', $node->textContent));
                            // Skip very short texts or ones that look like prices
                            if (strlen($nodeTitle) > 5 && !preg_match('/^\$?\d+/', $nodeTitle)) {
                                $debug_extraction[$i]['a_color_base_' . $idx] = $nodeTitle;
                                
                                // If this is the first one or longer than what we have, use it
                                if (empty($bestTitle) || strlen($nodeTitle) > strlen($bestTitle)) {
                                    $bestTitle = $nodeTitle;
                                }
                            }
                        }
                        
                        if (!empty($bestTitle)) {
                            $title = $bestTitle;
                            $title_extraction_method = 'a_color_base';
                            $debug_extraction[$i]['a_color_base_best'] = $bestTitle;
                        }
                    }
                }
                
                // Extract link
                $link = '';
                $linkNode = $xpath->query('.//a[contains(@class, "a-link-normal")]', $element)->item(0) ??
                           $xpath->query('.//h2//a', $element)->item(0);
                
                if ($linkNode) {
                    $link = $linkNode->getAttribute('href');
                    
                    // Make link absolute
                    if (strpos($link, 'http') !== 0) {
                        $link = 'https://www.amazon.com' . $link;
                    }
                    
                    // Add affiliate tag
                    if (strpos($link, 'tag=') === false) {
                        $link .= (strpos($link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                    }
                    
                    $debug_extraction[$i]['link'] = $link;
                }
                
                // Extract price
                $price = '';
                $price_value = 0;
                $priceNode = $xpath->query('.//span[@class="a-price"]/span[@class="a-offscreen"]', $element)->item(0) ??
                             $xpath->query('.//span[contains(@class, "a-price")]/span[contains(@class, "a-offscreen")]', $element)->item(0);
                
                if ($priceNode) {
                    $price = trim($priceNode->textContent);
                    $price_value = (float) preg_replace('/[^0-9.]/', '', $price);
                    $debug_extraction[$i]['price'] = $price;
                }
                
                // Extract image
                $image = '';
                $imageNode = $xpath->query('.//img[contains(@class, "s-image")]', $element)->item(0);
                
                if ($imageNode) {
                    $image = $imageNode->getAttribute('src');
                    $debug_extraction[$i]['image'] = $image;
                }
                
                // Extract ASIN
                $asin = '';
                if ($linkNode && preg_match('/\/dp\/([A-Z0-9]{10})/', $link, $matches)) {
                    $asin = $matches[1];
                    $debug_extraction[$i]['asin'] = $asin;
                }
                
                // Extract rating
                $rating = '';
                $rating_count = '';
                $ratingNode = $xpath->query('.//span[contains(@class, "a-icon-alt")]', $element)->item(0);
                
                // Rating count extraction to match Amazon's current HTML structure
                $ratingCountNode = $xpath->query('.//a[contains(@class, "a-link-normal")]//span[contains(@class, "a-size-base") and contains(@class, "s-underline-text")]', $element)->item(0);
                
                if ($ratingNode) {
                    $rating = trim($ratingNode->textContent);
                    $debug_extraction[$i]['rating'] = $rating;
                }
                
                if ($ratingCountNode) {
                    $rating_count = trim($ratingCountNode->textContent);
                    $debug_extraction[$i]['rating_count'] = $rating_count;
                }
                
                // Extract brand name - only look for brand text that appears before the title
                $brand = '';
                $brand_extraction_method = 'none';
                
                // First, find the title element to establish position reference
                $titleElement = null;
                if ($h2WithAriaLabel) {
                    $titleElement = $h2WithAriaLabel;
                } else {
                    // Try to find the title element using the same methods as title extraction
                    $titleElement = $xpath->query('.//span[contains(@class, "a-text-normal")]', $element)->item(0) ??
                                   $xpath->query('.//h2//span', $element)->item(0) ??
                                   $xpath->query('.//h2', $element)->item(0);
                }
                
                if ($titleElement) {
                    // Method 1: Look for brand elements that come before the title element in DOM order
                    $allSpans = $xpath->query('.//span[contains(@class, "a-size-base-plus") and contains(@class, "a-color-base")]', $element);
                    foreach ($allSpans as $spanNode) {
                        // Check if this span comes before the title element
                        if ($spanNode->compareDocumentPosition($titleElement) & DOMNode::DOCUMENT_POSITION_FOLLOWING) {
                            $brand_text = trim($spanNode->textContent);
                            // Filter out non-brand text (prices, descriptions, etc.)
                            if (!empty($brand_text) && !preg_match('/^\$?\d+/', $brand_text) && strlen($brand_text) < 50) {
                                $brand = $brand_text;
                                $brand_extraction_method = 'before_title_a_size_base_plus';
                                break;
                            }
                        }
                    }
                    
                    // Method 2: Look for brand in elements that precede the h2/title container
                    if (empty($brand)) {
                        $precedingSpans = $xpath->query('preceding-sibling::*//*[contains(@class, "a-color-secondary") and contains(@class, "a-size-base")]', $titleElement);
                        foreach ($precedingSpans as $spanNode) {
                            $brand_text = trim($spanNode->textContent);
                            // Look for text that looks like a brand (not price, not description)
                            if (!empty($brand_text) && 
                                !preg_match('/^\$?\d+/', $brand_text) && 
                                !preg_match('/delivery|shipping|free|prime/i', $brand_text) &&
                                !preg_match('/\d+\s*(oz|ml|lb|kg|inch|cm)/i', $brand_text) &&
                                strlen($brand_text) > 2 && strlen($brand_text) < 50) {
                                $brand = $brand_text;
                                $brand_extraction_method = 'preceding_sibling_brand';
                                break;
                            }
                        }
                    }
                    
                    // Method 3: Look for brand in parent elements that come before title
                    if (empty($brand)) {
                        $parentElement = $titleElement->parentNode;
                        if ($parentElement) {
                            $precedingElements = $xpath->query('preceding-sibling::*', $parentElement);
                            foreach ($precedingElements as $precedingElement) {
                                $brandSpans = $xpath->query('.//span[contains(@class, "a-color-secondary") or contains(@class, "a-size-base")]', $precedingElement);
                                foreach ($brandSpans as $spanNode) {
                                    $brand_text = trim($spanNode->textContent);
                                    if (!empty($brand_text) && 
                                        !preg_match('/^\$?\d+/', $brand_text) && 
                                        !preg_match('/delivery|shipping|free|prime/i', $brand_text) &&
                                        !preg_match('/\d+\s*(oz|ml|lb|kg|inch|cm)/i', $brand_text) &&
                                        strlen($brand_text) > 2 && strlen($brand_text) < 50) {
                                        $brand = $brand_text;
                                        $brand_extraction_method = 'preceding_parent_brand';
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                }
                
                $debug_extraction[$i]['brand'] = $brand;
                $debug_extraction[$i]['brand_method'] = $brand_extraction_method;
                
                // Extract unit price (e.g., $379.02/100 ml)
                $unit_price = '';
                $unit = '';
                
                // Look for the unit price container that contains parentheses - be more specific
                $unitPriceContainers = $xpath->query('.//span[contains(@class, "a-size-base") and contains(@class, "a-color-secondary")]', $element);
                $unitPriceContainer = null;
                
                // Find the container that actually contains parentheses with unit price
                foreach ($unitPriceContainers as $container) {
                    $container_text = trim($container->textContent);
                    if (strpos($container_text, '(') !== false && strpos($container_text, ')') !== false && strpos($container_text, '/') !== false) {
                        $unitPriceContainer = $container;
                        break;
                    }
                }
                
                if ($unitPriceContainer) {
                    $container_text = trim($unitPriceContainer->textContent);
                    $debug_extraction[$i]['unit_container_text'] = $container_text;
                    
                    // Check if it contains parentheses (indicating unit price)
                    if (strpos($container_text, '(') !== false && strpos($container_text, ')') !== false) {
                        $debug_extraction[$i]['unit_has_parentheses'] = true;
                        
                        // Extract the price from the offscreen span for accuracy
                        $unitPriceNode = $xpath->query('.//span[contains(@class, "a-price a-text-price")]/span[@class="a-offscreen"]', $element)->item(0);
                        if ($unitPriceNode) {
                            $unit_price_val = trim($unitPriceNode->textContent);
                            $debug_extraction[$i]['unit_price_val'] = $unit_price_val;
                            
                            // Extract the unit from the container text (e.g., "/100 ml" from "($3.49$3.49/100 ml)")
                            if (preg_match('/\/([^)]+)\)/', $container_text, $unitMatch)) {
                                $unit = trim($unitMatch[1]);
                                $unit_price = $unit_price_val . '/' . $unit;
                                $debug_extraction[$i]['unit_extracted'] = $unit;
                                $debug_extraction[$i]['unit_match_pattern'] = '/\/([^)]+)\)/';
                                
                                // Reasonableness check: detect obviously wrong unit prices
                                $unit_price_numeric = (float) preg_replace('/[^0-9.]/', '', $unit_price_val);
                                if ($unit_price_numeric > 0 && $price_value > 0) {
                                    $ratio = $unit_price_numeric / $price_value;
                                    
                                    // If unit price is more than 50x the product price, it's likely wrong
                                    if ($ratio > 50) {
                                        error_log("Unit price reasonableness check failed (test): Product price $price, Unit price $unit_price (ratio: " . number_format($ratio, 2) . "). Marking as invalid.");
                                        $unit_price = ''; // Mark as invalid
                                        $unit = '';
                                        $debug_extraction[$i]['reasonableness_check'] = 'failed_high_ratio';
                                    }
                                    // If unit price is less than 1/1000th of product price, also suspicious
                                    elseif ($ratio < 0.001) {
                                        error_log("Unit price reasonableness check failed (test): Product price $price, Unit price $unit_price (ratio: " . number_format($ratio, 6) . "). Marking as invalid.");
                                        $unit_price = ''; // Mark as invalid
                                        $unit = '';
                                        $debug_extraction[$i]['reasonableness_check'] = 'failed_low_ratio';
                                    } else {
                                        $debug_extraction[$i]['reasonableness_check'] = 'passed';
                                    }
                                }
                            } else {
                                $unit_price = $unit_price_val;
                                $debug_extraction[$i]['unit_extraction_result'] = 'no_unit_pattern_match';
                            }
                        } else {
                            $debug_extraction[$i]['unit_price_node_found'] = false;
                            
                            // Fallback: try to extract from the container text directly
                            if (preg_match('/\(([^)]+)\)/', $container_text, $match)) {
                                $extracted_content = trim($match[1]);
                                $debug_extraction[$i]['fallback_extracted_content'] = $extracted_content;
                                
                                // Try to clean up duplicate price values (e.g., "$3.49$3.49/100 ml" -> "$3.49/100 ml")
                                if (preg_match('/(\$[\d.]+).*?\/(.+)$/', $extracted_content, $cleanMatch)) {
                                    $unit_price = $cleanMatch[1] . '/' . $cleanMatch[2];
                                    $unit = $cleanMatch[2];
                                    $debug_extraction[$i]['fallback_unit_extracted'] = $unit;
                                    
                                    // Apply the same reasonableness check
                                    $unit_price_numeric = (float) preg_replace('/[^0-9.]/', '', $cleanMatch[1]);
                                    if ($unit_price_numeric > 0 && $price_value > 0) {
                                        $ratio = $unit_price_numeric / $price_value;
                                        if ($ratio > 50 || $ratio < 0.001) {
                                            error_log("Unit price reasonableness check failed (test fallback): Product price $price, Unit price $unit_price (ratio: " . number_format($ratio, 6) . "). Marking as invalid.");
                                            $unit_price = '';
                                            $unit = '';
                                            $debug_extraction[$i]['fallback_reasonableness_check'] = 'failed';
                                        } else {
                                            $debug_extraction[$i]['fallback_reasonableness_check'] = 'passed';
                                        }
                                    }
                                } else {
                                    $unit_price = $extracted_content;
                                    $debug_extraction[$i]['fallback_result'] = 'no_clean_pattern_match';
                                }
                            } else {
                                $debug_extraction[$i]['fallback_parentheses_match'] = false;
                            }
                        }
                    } else {
                        $debug_extraction[$i]['unit_has_parentheses'] = false;
                    }
                } else {
                    $debug_extraction[$i]['unit_container_found'] = false;
                }
                
                // Fallback to the old method if the new method doesn't work
                if (empty($unit_price)) {
                    $debug_extraction[$i]['using_fallback_method'] = true;
                    $unitPriceNode = $xpath->query('.//span[contains(@class, "a-price a-text-price")]/span[@class="a-offscreen"]', $element)->item(0);
                    $unitTextNode = $xpath->query('.//span[contains(@class, "a-size-base a-color-secondary")]', $element)->item(0);
                    if ($unitPriceNode && $unitTextNode) {
                        $unit_price_val = trim($unitPriceNode->textContent);
                        $unit_text = $unitTextNode->textContent;
                        $debug_extraction[$i]['fallback_unit_text'] = $unit_text;
                        
                        // Try to extract unit (e.g., /100 ml) from the text node
                        if (preg_match('/\/([\d\w\s.]+)/', $unit_text, $unitMatch)) {
                            $unit = trim($unitMatch[1]);
                            $unit_price = $unit_price_val . '/' . $unit;
                            $debug_extraction[$i]['fallback_unit_extracted'] = $unit;
                        } else {
                            $unit_price = $unit_price_val;
                            $debug_extraction[$i]['fallback_unit_extraction'] = 'no_pattern_match';
                        }
                    } else {
                        $debug_extraction[$i]['fallback_nodes_found'] = false;
                    }
                } else {
                    $debug_extraction[$i]['using_fallback_method'] = false;
                }
                
                // Set default unit if none found
                if (empty($unit) && !empty($unit_price)) {
                    $unit = 'No unit';
                    $debug_extraction[$i]['unit_set_to_default'] = true;
                }
                
                // Clear unit price data if no actual unit of measure exists
                if (!empty($unit_price) && (empty($unit) || $unit === 'No unit' || !preg_match('/(?:ml|g|gram|grams|oz|ounce|ounces|lb|pound|pounds|kg|kilogram|kilograms|unit|count|piece|pieces|pack|packs|each|item|items|fl\s*oz)\b/i', $unit))) {
                    $unit_price = '';
                    $unit = '';
                    $debug_extraction[$i]['unit_cleared_no_valid_measure'] = true;
                }
                
                $debug_extraction[$i]['final_unit_price'] = $unit_price;
                $debug_extraction[$i]['final_unit'] = $unit;
                
                // Extract delivery time (enhanced to capture multiple delivery options)
                $delivery_time = '';
                $delivery_extraction_method = 'none';
                
                // First try the delivery-block structure for multiple delivery options
                $deliveryBlock = $xpath->query('.//div[contains(@data-cy, "delivery-block")]', $element)->item(0);
                if ($deliveryBlock) {
                    $delivery_lines = [];
                    
                    // Extract primary delivery message
                    $primaryDelivery = $xpath->query('.//div[contains(@class, "udm-primary-delivery-message")]', $deliveryBlock)->item(0);
                    if ($primaryDelivery) {
                        $primary_text = trim($primaryDelivery->textContent);
                        if (!empty($primary_text)) {
                            $delivery_lines[] = $primary_text;
                        }
                    }
                    
                    // Extract secondary delivery message (e.g., "Or fastest delivery")
                    $secondaryDelivery = $xpath->query('.//div[contains(@class, "udm-secondary-delivery-message")]', $deliveryBlock)->item(0);
                    if ($secondaryDelivery) {
                        $secondary_text = trim($secondaryDelivery->textContent);
                        if (!empty($secondary_text)) {
                            $delivery_lines[] = $secondary_text;
                        }
                    }
                    
                    if (!empty($delivery_lines)) {
                        $delivery_time = implode("\n", $delivery_lines);
                        $delivery_extraction_method = 'data_cy_delivery_block_multi';
                    }
                }
                
                // Fallback to delivery-recipe if delivery-block not found
                if (empty($delivery_time)) {
                    $deliveryBlock = $xpath->query('.//div[contains(@data-cy, "delivery-recipe")]', $element)->item(0);
                    if ($deliveryBlock) {
                        $delivery_lines = [];
                        
                        // Look for spans with aria-label attributes that contain delivery information
                        $deliverySpans = $xpath->query('.//span[@aria-label]', $deliveryBlock);
                        foreach ($deliverySpans as $span) {
                            $aria_label = $span->getAttribute('aria-label');
                            if (!empty($aria_label) && (
                                stripos($aria_label, 'delivery') !== false || 
                                stripos($aria_label, 'FREE') !== false ||
                                preg_match('/\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)\b/', $aria_label)
                            )) {
                                $delivery_lines[] = trim($aria_label);
                            }
                        }
                        
                        // If we found delivery lines from aria-labels, use them
                        if (!empty($delivery_lines)) {
                            $delivery_time = implode("\n", $delivery_lines);
                            $delivery_extraction_method = 'data_cy_aria_labels';
                        } else {
                            // Fallback to the old method if aria-labels don't work
                            $delivery_texts = [];
                            foreach ($deliveryBlock->getElementsByTagName('span') as $span) {
                                $text = trim($span->textContent);
                                if ($text !== '' && strlen($text) > 2) { // Avoid single characters like "F", "R", "E"
                                    $delivery_texts[] = $text;
                                }
                            }
                            $delivery_time_full = trim(implode(' ', $delivery_texts));
                            // Remove duplicate text (sometimes repeated in nested spans)
                            $delivery_time_full = preg_replace('/(.*?)\1+/', '$1', $delivery_time_full);
                            
                            // Enhanced regex to capture FREE delivery with dates
                            if (preg_match('/^(FREE\s+delivery\s+.+?\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+[A-Z][a-z]{2,8}\s+\d{1,2})\b/i', $delivery_time_full, $match)) {
                                $delivery_time = trim($match[1]);
                                $delivery_extraction_method = 'data_cy_with_free_and_date';
                            } 
                            // Match FREE delivery without specific date
                            elseif (preg_match('/^(FREE\s+delivery[^.]*)/i', $delivery_time_full, $match)) {
                                $delivery_time = trim($match[1]);
                                $delivery_extraction_method = 'data_cy_with_free_no_date';
                            }
                            // Match delivery with date but no FREE
                            elseif (preg_match('/^(.+?\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+[A-Z][a-z]{2,8}\s+\d{1,2})\b/', $delivery_time_full, $match)) {
                                $delivery_time = trim($match[1]);
                                $delivery_extraction_method = 'data_cy_with_date_no_free';
                            } 
                            // Fallback to full text if no pattern matches
                            else {
                                $delivery_time = $delivery_time_full;
                                $delivery_extraction_method = 'data_cy_full_text';
                            }
                            
                            $debug_extraction[$i]['delivery_time_full'] = $delivery_time_full;
                        }
                    }
                }
                
                $debug_extraction[$i]['delivery_time'] = $delivery_time;
                $debug_extraction[$i]['delivery_method'] = $delivery_extraction_method;
                
                // Add to sample products array
                if (!empty($title)) {
                    error_log("Product $i - Final title: $title (method: $title_extraction_method)");
                    $sample_products[] = [
                        'title' => $title,
                        'title_extraction_method' => $title_extraction_method,
                        'price' => $price,
                        'price_per_unit' => $unit_price,
                        'unit' => $unit,
                        'delivery_time' => $delivery_time,
                        'delivery_extraction_method' => $delivery_extraction_method ?? 'none',
                        'image' => $image,
                        'link' => $link,
                        'asin' => $asin,
                        'rating' => $rating,
                        'rating_count' => $rating_count,
                        'brand' => $brand,
                        'brand_extraction_method' => $brand_extraction_method ?? 'none'
                    ];
                } else {
                    error_log("Product $i - No title found after trying all methods");
                }
            }
            
            // Add debug information
            $response['debug_extraction'] = $debug_extraction;
            $response['debug_msg'] = 'Extracted ' . count($sample_products) . ' real products from HTML';
            error_log("Extraction complete. Found " . count($sample_products) . " products with titles");
        } else {
            error_log("No products found with selector: $selected_products_selector");
        }
    }
    
    // Update sample_products in response
    $response['xpath_results']['sample_products'] = $sample_products;
    
    error_log("Sending response with " . count($sample_products) . " products");
    wp_send_json_success($response);
}

/**
 * Register scripts and styles for parsing test
 */
function ps_enqueue_parsing_test_scripts() {
    wp_enqueue_script(
        'ps-parsing-test', 
        PS_PLUGIN_URL . 'assets/js/parsing-test.js', 
        ['jquery'], 
        PS_VERSION . '.' . time(), // Add timestamp to force reload
        true
    );
    
    wp_localize_script(
        'ps-parsing-test',
        'psParsing',
        [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ps_parsing_test_nonce')
        ]
    );
}
add_action('wp_enqueue_scripts', 'ps_enqueue_parsing_test_scripts');
add_action('admin_enqueue_scripts', 'ps_enqueue_parsing_test_scripts');

/**
 * Shortcode for the parsing test UI
 */
function ps_parsing_test_shortcode() {
    // Get a list of HTML files in the logs directory
    $logs_dir = PS_PLUGIN_DIR . 'logs/';
    $html_files = [];
    
    if (is_dir($logs_dir)) {
        $files = scandir($logs_dir);
        foreach ($files as $file) {
            if (strpos($file, '.html') !== false) {
                $html_files[] = $file;
            }
        }
    }
    
    ob_start();
    ?>
    <div class="ps-parsing-test-container">
        <h3>Amazon HTML Parsing Test Tool</h3>
        
        <div class="ps-test-options">
            <div class="ps-test-source-selector">
                <label>HTML Source:</label>
                <select id="ps-source-type">
                    <option value="file">HTML File</option>
                    <option value="url">Amazon URL</option>
                    <option value="text">Custom HTML</option>
                </select>
            </div>
            
            <div id="ps-file-source" class="ps-source-option">
                <label>Select HTML File:</label>
                <select id="ps-file-path">
                    <option value="">Select a file...</option>
                    <?php foreach ($html_files as $file): ?>
                    <option value="<?php echo esc_attr($file); ?>"><?php echo esc_html($file); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div id="ps-url-source" class="ps-source-option" style="display:none;">
                <label>Amazon URL:</label>
                <input type="text" id="ps-url" placeholder="https://www.amazon.com/s?k=..." />
            </div>
            
            <div id="ps-text-source" class="ps-source-option" style="display:none;">
                <label>Custom HTML:</label>
                <textarea id="ps-html-content" rows="8" placeholder="Paste HTML content here..."></textarea>
            </div>
            
            <div class="ps-test-country">
                <label>Amazon Region:</label>
                <select id="ps-country">
                    <option value="us">United States</option>
                    <option value="ca">Canada</option>
                </select>
            </div>
            
            <div class="ps-test-actions">
                <button id="ps-run-test" class="button">Run Parsing Test</button>
            </div>
        </div>
        
        <div id="ps-parsing-results" style="display:none;">
            <h4>Test Results</h4>
            
            <div class="ps-results-summary">
                <div class="ps-result-item">
                    <span class="ps-result-label">Amazon Blocking Detection:</span>
                    <span id="ps-blocking-result" class="ps-result-value"></span>
                </div>
                <div class="ps-result-item">
                    <span class="ps-result-label">XPath Selectors:</span>
                    <div id="ps-xpath-selectors" class="ps-result-value"></div>
                </div>
                <div class="ps-result-item">
                    <span class="ps-result-label">Alternative XPath Selectors:</span>
                    <div id="ps-alt-xpath-selectors" class="ps-result-value"></div>
                </div>
                <div class="ps-result-item">
                    <span class="ps-result-label">XML Parsing Issues:</span>
                    <span id="ps-xml-errors" class="ps-result-value"></span>
                </div>
            </div>
            
            <div id="ps-sample-products" style="display:none;">
                <h4>Sample Products</h4>
                <ul id="ps-sample-product-list"></ul>
            </div>
            
            <div class="ps-html-sample">
                <h4>HTML Sample</h4>
                <pre id="ps-html-preview"></pre>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('primates_parsing_test', 'ps_parsing_test_shortcode'); 