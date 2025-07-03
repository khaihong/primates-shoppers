<?php
/**
 * Amazon Scraper integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search Amazon products using web scraping
 *
 * PROXY COST OPTIMIZATION STRATEGY:
 * - Primary use: Proxy is used for development/testing when on configured network
 * - Fallback protection: If external IPs get blocked (503, 429, 403), automatically retry once with proxy
 * - This minimizes proxy costs while ensuring reliability for production users
 *
 * @param string $query The main search query
 * @param string $exclude_keywords Keywords to exclude from results
 * @param string $sort_by Sorting method (price, price_per_unit)
 * @param string $country Country code ('us' or 'ca')
 * @param float $min_rating Minimum rating filter (3.5, 4.0, 4.5)
 * @param int $page Page number for pagination (default: 1)
 * @return array Search results
 */
function ps_search_amazon_products($query, $exclude_keywords = '', $sort_by = 'price', $country = 'us', $min_rating = 4.0, $page = 1) {
    // If query is empty, return empty array
    if (empty($query)) {
        return array();
    }
    
    // ps_log_error("Initiating live search for: '{$query}' in country: {$country}, page: {$page}");
    
    // Construct the Amazon search URL with pagination
    $search_url = ps_construct_amazon_search_url($query, $country, $page);
    // ps_log_error("Constructed search URL: {$search_url}");
    
    // Get the search results HTML
    $html_content = ps_fetch_amazon_search_results($search_url, $country);
    
    // Check if we got an error response
    if (is_array($html_content) && isset($html_content['error']) && $html_content['error'] === true) {
        $http_code = $html_content['http_code'];
        ps_log_error("Failed to fetch Amazon search results for query: '{$query}' page {$page} - HTTP {$http_code}");
        
        // Check if it's a blocking error and we weren't using proxy - retry with proxy as fallback
        if (in_array($http_code, [503, 429, 403, 502, 504])) {
            // Check if we can use proxy fallback (not already using proxy)
            $on_current_network = ps_is_on_current_network();
            if (!$on_current_network && defined('PS_DECODO_PROXY_HOST') && defined('PS_DECODO_PROXY_PORT')) {
                ps_log_error("FALLBACK: External IP got blocked (HTTP {$http_code}), retrying with proxy for cost-effective unblocking");
                
                // Retry with forced proxy
                $html_content = ps_fetch_amazon_search_results($search_url, $country, true);
                
                // If the retry succeeded, continue with normal processing
                if (!is_array($html_content) || !isset($html_content['error'])) {
                    ps_log_error("FALLBACK SUCCESS: Proxy retry worked, continuing with results");
                    // Don't return here - let it continue to normal processing
                } else {
                    ps_log_error("FALLBACK FAILED: Proxy retry also failed - HTTP " . ($html_content['http_code'] ?? 'unknown'));
                }
            }
        }
        
        // If we're still in error state after potential retry, handle the error
        if (is_array($html_content) && isset($html_content['error']) && $html_content['error'] === true) {
            $http_code = $html_content['http_code'];
            
            // Get the associate tag for the correct affiliate link
            $associate_tag = ps_get_associate_tag($country);
            
            // Check if it's a blocking error (503, 429, etc.)
            if (in_array($http_code, [503, 429, 403, 502, 504])) {
            // Create Amazon search URL for the user to continue searching manually
            $amazon_base = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
            $amazon_search_url = $amazon_base . '/s?k=' . urlencode($query);
            
            // Add affiliate tag to the continue URL
            if (!empty($associate_tag)) {
                $amazon_search_url .= '&tag=' . $associate_tag;
            }
            
            return array(
                'success' => false,
                'items' => array(),
                'count' => 0,
                'message' => 'Amazon is blocking requests. Please try again later.<br><br>Or continue search on <a href="' . $amazon_search_url . '" target="_blank" rel="noopener">' . $amazon_search_url . '</a>',
                'amazon_search_url' => $amazon_search_url,
                'search_query' => $query,
                'country' => $country,
                'http_code' => $http_code
            );
            } else {
                // Create Amazon search URL for the user to continue searching manually
                $amazon_base = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
                $amazon_search_url = $amazon_base . '/s?k=' . urlencode($query);
                
                // Add affiliate tag to the continue URL
                if (!empty($associate_tag)) {
                    $amazon_search_url .= '&tag=' . $associate_tag;
                }
                
                return array(
                    'success' => false,
                    'items' => array(),
                    'count' => 0,
                    'message' => 'Failed to connect to Amazon. Please try again later.<br><br>Or continue search on <a href="' . $amazon_search_url . '" target="_blank" rel="noopener">' . $amazon_search_url . '</a>',
                    'amazon_search_url' => $amazon_search_url,
                    'search_query' => $query,
                    'country' => $country,
                    'http_code' => $http_code
                );
            }
        }
    }
    
    if (empty($html_content)) {
        // ps_log_error("Failed to fetch Amazon search results for query: '{$query}' page {$page} - No response received");
        
        // Get the associate tag for the correct affiliate link
        $associate_tag = ps_get_associate_tag($country);
        
        // Create Amazon search URL for the user to continue searching manually
        $amazon_base = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
        $amazon_search_url = $amazon_base . '/s?k=' . urlencode($query);
        
        // Add affiliate tag to the continue URL
        if (!empty($associate_tag)) {
            $amazon_search_url .= '&tag=' . $associate_tag;
        }
        
        return array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => 'No response received from Amazon. Please try again later.<br><br>Or continue search on <a href="' . $amazon_search_url . '" target="_blank" rel="noopener">' . $amazon_search_url . '</a>',
            'amazon_search_url' => $amazon_search_url,
            'search_query' => $query,
            'country' => $country
        );
    }
    
    // Check if Amazon is blocking the request
    if (ps_is_amazon_blocking($html_content)) {
        ps_log_error("Amazon is blocking search for query: '{$query}' page {$page} - Blocking page detected");
        
        // Check if we can use proxy fallback (not already using proxy)
        $on_current_network = ps_is_on_current_network();
        if (!$on_current_network && defined('PS_DECODO_PROXY_HOST') && defined('PS_DECODO_PROXY_PORT')) {
            ps_log_error("FALLBACK: External IP got blocking page, retrying with proxy for cost-effective unblocking");
            
            // Retry with forced proxy
            $html_content = ps_fetch_amazon_search_results($search_url, $country, true);
            
            // If the retry succeeded and is not blocking, continue with normal processing
            if (!empty($html_content) && !ps_is_amazon_blocking($html_content)) {
                ps_log_error("FALLBACK SUCCESS: Proxy retry bypassed blocking page, continuing with results");
                // Don't return here - let it continue to normal processing
            } else {
                ps_log_error("FALLBACK FAILED: Proxy retry still returned blocking page or error");
            }
        }
        
        // If we're still being blocked after potential retry, return error
        if (ps_is_amazon_blocking($html_content)) {
            // Get the associate tag for the correct affiliate link
            $associate_tag = ps_get_associate_tag($country);
            
            // Create Amazon search URL for the user to continue searching manually
            $amazon_base = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
            $amazon_search_url = $amazon_base . '/s?k=' . urlencode($query);
            
            // Add affiliate tag to the continue URL
            if (!empty($associate_tag)) {
                $amazon_search_url .= '&tag=' . $associate_tag;
            }
            
            return array(
                'success' => false,
                'items' => array(),
                'count' => 0,
                'message' => 'Amazon is blocking requests. Please try again later.<br><br>Or continue search on <a href="' . $amazon_search_url . '" target="_blank" rel="noopener">' . $amazon_search_url . '</a>',
                'amazon_search_url' => $amazon_search_url,
                'search_query' => $query,
                'country' => $country
            );
        }
    }
    
    // Check if it's a valid search page
    if (!ps_is_valid_search_page($html_content)) {
        // ps_log_error("Invalid Amazon search results format: " . substr($html_content, 0, 100));
        
        // Get the associate tag for the correct affiliate link
        $associate_tag = ps_get_associate_tag($country);
        
        // Create Amazon search URL for the user to continue searching manually
        $amazon_base = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
        $amazon_search_url = $amazon_base . '/s?k=' . urlencode($query);
        
        // Add affiliate tag to the continue URL
        if (!empty($associate_tag)) {
            $amazon_search_url .= '&tag=' . $associate_tag;
        }
        
        $invalid_response = array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => 'Invalid response from Amazon. Please try again later.<br><br>Or continue search on <a href="' . $amazon_search_url . '" target="_blank" rel="noopener">' . $amazon_search_url . '</a>',
            'amazon_search_url' => $amazon_search_url,
            'search_query' => $query,
            'country' => $country
        );
        
        // ps_log_error("INVALID_RESPONSE_RETURN: " . json_encode($invalid_response));
        
        return $invalid_response;
    }
    
    // Get the associate tag
    $associate_tag = ps_get_associate_tag($country);
    // ps_log_error("Using associate tag for {$country}: '{$associate_tag}'");
    
    // Parse the search results HTML
    $products = ps_parse_amazon_results($html_content, $associate_tag, $min_rating, $country);
    
    return $products;
}

/**
 * Try alternative parsing methods for Amazon results
 * This helps handle changes in Amazon's HTML structure
 */
function ps_try_alternative_parsing($html, $affiliate_id, $min_rating = 4.0, $country = 'us') {
    // ps_log_error("Attempting alternative parsing methods");
    
    $products = array();
    
    try {
        // Now go straight to XPath parsing - JSON parsing was removed
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Array of alternative selector sets to try
        $selector_sets = array(
            // Primary selector (only one we're using now)
            array(
                'product' => '//div[@role="listitem"]',
                'title' => './/span[contains(@class, "a-text-normal")]',
                'link' => './/a[contains(@class, "a-link-normal")]/@href',
                'price' => './/span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]',
                'image' => './/img[contains(@class, "s-image")]/@src'
            ),
            // Sponsored products selector
            array(
                'product' => '//div[@data-asin and contains(@class, "s-result-item") and not(@role="listitem")]',
                'title' => './/span[contains(@class, "a-text-normal")]',
                'link' => './/a[contains(@class, "a-link-normal")]/@href',
                'price' => './/span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]',
                'image' => './/img[contains(@class, "s-image")]/@src'
            )
        );
        
        // Try each selector set
        foreach ($selector_sets as $index => $selectors) {
            // ps_log_error("Trying alternative selector set #" . ($index + 1));
            
            $product_nodes = $xpath->query($selectors['product']);
            
            if ($product_nodes && $product_nodes->length > 0) {
                // ps_log_error("Found " . $product_nodes->length . " potential product nodes with selector set #" . ($index + 1));
                
                foreach ($product_nodes as $node) {
                    $title_nodes = $xpath->query($selectors['title'], $node);
                    $link_nodes = $xpath->query($selectors['link'], $node);
                    $price_nodes = $xpath->query($selectors['price'], $node);
                    $image_nodes = $xpath->query($selectors['image'], $node);
                    
                    if ($title_nodes->length > 0 && $link_nodes->length > 0) {
                        $title = trim($title_nodes->item(0)->textContent);
                        $link = trim($link_nodes->item(0)->nodeValue);
                        
                        // Skip if title or link is empty
                        if (empty($title)) {
                            continue;
                        }
                        
                        // Clean title - remove "Sponsored Ad –" prefix if present
                        $title = preg_replace('/^Sponsored Ad\s*[–-]\s*/i', '', $title);
                        
                        // Process link - ensure it's absolute & add affiliate tag
                        if (strpos($link, 'http') !== 0) {
                            $base_amazon_url = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
                            $link = $base_amazon_url . $link;
                        }
                        
                        // Add affiliate tag if not already present
                        if (!preg_match('/[?&]tag=/', $link) && !empty($affiliate_id)) {
                            $original_link = $link;
                            $link .= (strpos($link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                        }
                        
                        $price = '';
                        $price_value = 0;
                        
                        if ($price_nodes->length > 0) {
                            $price = trim($price_nodes->item(0)->nodeValue);
                            
                            // Use common utility for price parsing
                            $price_value = ps_parse_price($price);
                        }
                        
                        $image = '';
                        if ($image_nodes->length > 0) {
                            $image = trim($image_nodes->item(0)->nodeValue);
                        }
                        
                        // Extract unit price using common utility
                        $unit_price_data = ps_extract_amazon_unit_price($xpath, $node, $price_value, 'alt');
                        $unit_price = $unit_price_data['unit_price'];
                        $unit = $unit_price_data['unit'];
                        $unit_price_numeric = $unit_price_data['unit_price_numeric'];

                        // Extract delivery time (enhanced to capture multiple delivery options)
                        $delivery_time = '';
                        
                        // First try the delivery-block structure for multiple delivery options
                        $deliveryBlock = $xpath->query('.//div[contains(@data-cy, "delivery-block")]', $node)->item(0);
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
                            }
                        }
                        
                        // Fallback to delivery-recipe if delivery-block not found
                        if (empty($delivery_time)) {
                            $deliveryBlock = $xpath->query('.//div[contains(@data-cy, "delivery-recipe")]', $node)->item(0);
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
                                    } 
                                    // Match FREE delivery without specific date
                                    elseif (preg_match('/^(FREE\s+delivery[^.]*)/i', $delivery_time_full, $match)) {
                                        $delivery_time = trim($match[1]);
                                    }
                                    // Match delivery with date but no FREE
                                    elseif (preg_match('/^(.+?\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+[A-Z][a-z]{2,8}\s+\d{1,2})\b/', $delivery_time_full, $match)) {
                                        $delivery_time = trim($match[1]);
                                    } 
                                    // Fallback to full text if no pattern matches
                                    else {
                                        $delivery_time = $delivery_time_full;
                                    }
                                }
                            }
                        }

                        // Extract brand - only look for brand text that appears before the title
                        $brand = '';
                        $brand_extraction_method = 'none';
                        
                        // First, find the title element to establish position reference
                        $titleElement = null;
                        if ($h2WithAriaLabel) {
                            $titleElement = $h2WithAriaLabel;
                        } else {
                            // Try to find the title element using the same methods as title extraction
                            $titleElement = $xpath->query('.//span[contains(@class, "a-text-normal")]', $node)->item(0) ??
                                           $xpath->query('.//h2//span', $node)->item(0) ??
                                           $xpath->query('.//h2', $node)->item(0);
                        }
                        
                        if ($titleElement) {
                            // Method 1: Look for brand elements that come before the title element in DOM order
                            $allSpans = $xpath->query('.//span[contains(@class, "a-size-base-plus") and contains(@class, "a-color-base")]', $node);
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
                        
                        // Clear unit price data if no actual unit of measure exists
                        if (!empty($unit_price) && (empty($unit) || !preg_match('/(?:ml|g|gram|grams|oz|ounce|ounces|lb|pound|pounds|kg|kilogram|kilograms|unit|count|piece|pieces|pack|packs|each|item|items|fl\s*oz)\b/i', $unit))) {
                            $unit_price = '';
                            $unit = '';
                            $current_product_debug['unit_cleared_no_valid_measure'] = true;
                        }
                        
                        // Calculate unit price value for sorting using common utility
                        $unit_price_value = $price_value; // Default to regular price
                        if (!empty($unit_price)) {
                            $unit_price_numeric = ps_extract_numeric_price($unit_price);
                            if ($unit_price_numeric > 0) {
                                $unit_price_value = $unit_price_numeric;
                            }
                        }
                        
                        // Create product structure
                        $product = array(
                            'title' => $title,
                            'link' => $link,
                            'price' => $price,
                            'price_value' => $price_value,
                            'image' => $image,
                            'price_per_unit' => $unit_price,
                            'price_per_unit_value' => $unit_price_value,
                            'unit' => $unit,
                            'delivery_time' => $delivery_time,
                            'brand' => $brand,
                            'description' => substr($title, 0, 100) . '...',
                            'parsing_method' => 'xpath_alt'
                        );
                        
                        $products[] = $product;
                    }
                }
                
                if (!empty($products)) {
                    // ps_log_error("Alternative parsing method successfully extracted " . count($products) . " products with selector set #" . ($index + 1));
                    break; // Stop trying other selectors if we found products
                }
            }
        }
    } catch (Exception $e) {
        // ps_log_error("Alternative parsing failed with error: " . $e->getMessage());
    }
    
    return $products;
}

/**
 * Check if Amazon is blocking the request
 *
 * @param string $html The HTML content
 * @return bool True if Amazon is blocking, false otherwise
 */
function ps_is_amazon_blocking($html) {
    // Check for common indicators of blocking
    $blocking_indicators = array(
        'robot check',
        // 'captcha', // Temporarily commented out for testing with valid search results page
        'verify you\\\'re a human',
        'automated access',
        'unusual activity',
        'sorry, we just need to make sure you\\\'re not a robot',
        'to discuss automated access to amazon data please contact'
    );
    
    $html_lower = strtolower($html);
    
    foreach ($blocking_indicators as $indicator) {
        if (strpos($html_lower, $indicator) !== false) {
            // ps_log_error("ps_is_amazon_blocking: Detected blocking indicator: '{$indicator}'");
            return true;
        }
    }
    
    return false;
}

/**
 * Check if the response is a valid Amazon search page
 *
 * @param string $html The HTML content
 * @return bool True if it's a valid search page, false otherwise
 */
function ps_is_valid_search_page($html) {
    // Check for common elements that should be present in a search page
    $search_indicators = array(
        'role="listitem"',
        'data-component-type="s-search-results"',
        'class="s-result-item"',
        'class="s-search-results"',
        'id="search"'
    );
    
    foreach ($search_indicators as $indicator) {
        if (strpos($html, $indicator) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Parse Amazon search results HTML and extract product information
 *
 * @param string $html The Amazon HTML content
 * @param string $affiliate_id The Amazon affiliate ID
 * @param float $min_rating Minimum rating filter
 * @param string $country Country code ('us' or 'ca') for proper URL construction
 * @return array The parsed products
 */
function ps_parse_amazon_results($html, $affiliate_id, $min_rating = 4.0, $country = 'us') {
    $products = array();
    $raw_items_for_cache = array(); // For storing all successfully parsed items before display filtering
    $raw_items_count_for_cache = 0;
    
    // Track seen products to prevent duplicates between parsing methods
    $seen_links = array();
    $seen_asins = array();

    // ps_log_error("Starting to parse Amazon results.");

    // --- Using XPath parsing ---
    try {
        // Suppress XML errors
        $previous_error_state = libxml_use_internal_errors(true);
        
        // Try to improve HTML by fixing encoding issues
        $html = ps_sanitize_html_for_parsing($html);
        
        // Load HTML into DOMDocument
        $dom = new DOMDocument();
        @$dom->loadHTML($html); // Use @ to suppress PHP warnings during loading
        
        // Check for errors
        $errors = libxml_get_errors();
        if (!empty($errors)) {
            $error_count = count($errors);
            // ps_log_error("XPath: DOMDocument encountered {$error_count} XML parsing errors (showing first 3)");
            
            // Log first 3 errors only to avoid flooding the log
            for ($i = 0; $i < min(3, $error_count); $i++) {
                // ps_log_error("XPath XML Error " . ($i + 1) . ": " . trim($errors[$i]->message));
            }
            // Clear the errors
            libxml_clear_errors();
        }
        
        // Restore previous error state
        libxml_use_internal_errors($previous_error_state);
        
        $xpath = new DOMXPath($dom);
        
        // Use the div[@role="listitem"] selector
        $productElements = $xpath->query('//div[@role="listitem"]');
        
        // Also capture sponsored products which may appear in different structures
        $sponsoredElements = $xpath->query('//div[@data-asin and contains(@class, "s-result-item") and not(@role="listitem")]');
        
        // Merge regular and sponsored product elements
        $allProductElements = array();
        if ($productElements && $productElements->length > 0) {
            foreach ($productElements as $element) {
                $allProductElements[] = $element;
            }
        }
        if ($sponsoredElements && $sponsoredElements->length > 0) {
            foreach ($sponsoredElements as $element) {
                $allProductElements[] = $element;
            }
        }
        
        $totalProductCount = count($allProductElements);
        // ps_log_error("XPath: Found " . $productElements->length . " regular products and " . $sponsoredElements->length . " sponsored products (total: " . $totalProductCount . ")");
        
        if ($totalProductCount > 0) {
            $debug_extraction_data = []; // For detailed logging of extraction attempts

            foreach ($allProductElements as $idx => $element) {
                $current_product_debug = []; // Debug info for the current product
            
                // Initialize product data
                $title = '';
                $link = '';
                $price_str = '';
                $price_value = 0;
                $image = '';
                $asin = '';
                $brand = '';
                $rating_text = '';
                $rating_number = 0;
                $rating_count_str = '';
                $rating_link = '';
                $delivery_time = '';
                $title_extraction_method = 'none';

                // --- Extract Title (using methods from parsing-test.php) ---
                // Method 1: h2 with aria-label
                $h2WithAriaLabel = $xpath->query('.//h2[@aria-label]', $element)->item(0);
                if ($h2WithAriaLabel) {
                    $title = trim(preg_replace('/\s+/', ' ', $h2WithAriaLabel->getAttribute('aria-label')));
                    $title_extraction_method = 'h2_aria_label';
                    $current_product_debug['title_h2_aria_label'] = $title;
                }
                
                // Method 2: span inside h2
                if (empty($title)) {
                    $spanInH2 = $xpath->query('.//h2//span', $element)->item(0);
                    if ($spanInH2) {
                        $title = trim(preg_replace('/\s+/', ' ', $spanInH2->textContent));
                        $title_extraction_method = 'h2_span';
                        $current_product_debug['title_h2_span'] = $title;
                    }
                }
                
                // Method 3: span with a-text-normal inside any anchor
                if (empty($title)) {
                    $spanNode = $xpath->query('.//span[contains(@class, "a-text-normal")]', $element)->item(0);
                    if ($spanNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $spanNode->textContent));
                        $title_extraction_method = 'span_a_text_normal';
                        $current_product_debug['title_span_a_text_normal'] = $title;
                    }
                }
                
                // Method 4: Generic h2 text content
                if (empty($title)) {
                    $titleNode = $xpath->query('.//h2', $element)->item(0);
                    if ($titleNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
                        $title_extraction_method = 'h2_text';
                        $current_product_debug['title_h2_text'] = $title;
                    }
                }
                
                // Method 5: Any a-link with a-text-normal span inside
                if (empty($title)) {
                    $linkNodes = $xpath->query('.//a[contains(@class, "a-link-normal")]', $element);
                    if ($linkNodes && $linkNodes->length > 0) {
                        $bestTitle = '';
                        foreach ($linkNodes as $node) {
                            $spanNode = $xpath->query('.//span[contains(@class, "a-text-normal")]', $node)->item(0);
                            if ($spanNode) {
                                $nodeTitle = trim(preg_replace('/\s+/', ' ', $spanNode->textContent));
                                if (empty($bestTitle) || strlen($nodeTitle) > strlen($bestTitle)) {
                                    $bestTitle = $nodeTitle;
                                }
                            }
                        }
                        if (!empty($bestTitle)) {
                            $title = $bestTitle;
                            $title_extraction_method = 'a_link_normal_span';
                            $current_product_debug['title_a_link_normal_span'] = $title;
                        }
                    }
                }
                
                // Method 6: Any a-size-base-plus
                if (empty($title)) {
                    $titleNodes = $xpath->query('.//span[contains(@class, "a-size-base-plus")]', $element);
                    if ($titleNodes && $titleNodes->length > 0) {
                        $bestTitle = '';
                        foreach ($titleNodes as $node) {
                            $nodeTitle = trim(preg_replace('/\s+/', ' ', $node->textContent));
                            if (strlen($nodeTitle) > 5 && !preg_match('/^\$?\d+/', $nodeTitle)) { // Skip short/price-like
                                if (empty($bestTitle) || strlen($nodeTitle) > strlen($bestTitle)) {
                                    $bestTitle = $nodeTitle;
                                }
                            }
                        }
                        if (!empty($bestTitle)) {
                            $title = $bestTitle;
                            $title_extraction_method = 'a_size_base_plus';
                            $current_product_debug['title_a_size_base_plus'] = $title;
                        }
                    }
                }
                
                // Method 7: Any a-color-base span (fallback)
                if (empty($title)) {
                    $titleNodes = $xpath->query('.//span[contains(@class, "a-color-base")]', $element);
                    if ($titleNodes && $titleNodes->length > 0) {
                        $bestTitle = '';
                        foreach ($titleNodes as $node) {
                            $nodeTitle = trim(preg_replace('/\s+/', ' ', $node->textContent));
                            if (strlen($nodeTitle) > 5 && !preg_match('/^\$?\d+/', $nodeTitle)) { // Skip short/price-like
                                if (empty($bestTitle) || strlen($nodeTitle) > strlen($bestTitle)) {
                                    $bestTitle = $nodeTitle;
                                }
                            }
                        }
                        if (!empty($bestTitle)) {
                            $title = $bestTitle;
                            $title_extraction_method = 'a_color_base';
                            $current_product_debug['title_a_color_base'] = $title;
                        }
                    }
                }
                $current_product_debug['title_final'] = $title;
                $current_product_debug['title_method'] = $title_extraction_method;

                // --- Extract Link ---
                $linkNode = $xpath->query('.//a[contains(@class, "a-link-normal") and .//img[contains(@class, "s-image")]]', $element)->item(0) ?? // Link containing the main image
                            $xpath->query('.//h2//a[contains(@class, "a-link-normal")]', $element)->item(0) ?? // Link within H2
                            $xpath->query('.//a[contains(@class, "a-link-normal") and contains(@class, "s-underline-text")]', $element)->item(0) ?? // Common text link
                            $xpath->query('.//a[contains(@class, "a-link-normal")]', $element)->item(0); // Generic fallback

                if ($linkNode) {
                    $link = $linkNode->getAttribute('href');
                    if (strpos($link, 'http') !== 0) {
                        $base_amazon_url = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com'; // Use country parameter for correct URL
                        $link = rtrim($base_amazon_url, '/') . $link;
                    }
                    if (!preg_match('/[?&]tag=/', $link) && !empty($affiliate_id)) {
                        $original_link = $link;
                        $link .= (strpos($link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                    }
                }
                $current_product_debug['link'] = $link;
                
                // Skip if title or link is empty or title contains learn more/let us know
                if (empty($title) || empty($link) || stripos($title, 'learn more') !== false || stripos($title, 'let us know') !== false ) {
                    // ps_log_error("Skipping product " . ($idx + 1) . ": Empty title/link or unwanted content. Title: '{$title}', Link: '{$link}'");
                    $debug_extraction_data[$idx] = $current_product_debug; // Log debug even for skipped
                    continue;
                }

                // Clean title - remove "Sponsored Ad –" prefix if present
                $title = preg_replace('/^Sponsored Ad\s*[–-]\s*/i', '', $title);
                $current_product_debug['title_cleaned'] = $title;

                // --- Extract Price ---
                    $priceNode = $xpath->query('.//span[@class="a-price"]/span[@class="a-offscreen"]', $element)->item(0) ??
                             $xpath->query('.//span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]', $element)->item(0);
                    if ($priceNode) {
                    $price_str = trim($priceNode->textContent);
                    // Use common utility for price parsing
                    $price_value = ps_parse_price($price_str);
                }
                 $current_product_debug['price_str'] = $price_str;
                 $current_product_debug['price_value'] = $price_value;

                // --- Extract Image ---
                $imageNode = $xpath->query('.//img[contains(@class, "s-image")]', $element)->item(0);
                    if ($imageNode) {
                        $image = $imageNode->getAttribute('src');
                    // Ensure image src is not empty and is a valid image format, prefer larger images
                    if (empty($image) || !preg_match('/\.(jpeg|jpg|gif|png)(?:\?.*)?$/i', $image)) {
                         $image = $imageNode->getAttribute('data-src'); // Try data-src
                    }
                     if (empty($image) || !preg_match('/\.(jpeg|jpg|gif|png)(?:\?.*)?$/i', $image)) {
                        $image = ''; // Reset if still not valid
                    }
                }
                $current_product_debug['image'] = $image;

                // --- Extract ASIN ---
                if (!empty($link) && preg_match('/\/dp\/([A-Z0-9]{10})/', $link, $matches)) {
                        $asin = $matches[1];
                } else {
                    $asin_data_attribute = $element->getAttribute('data-asin');
                    if(!empty($asin_data_attribute)) {
                        $asin = $asin_data_attribute;
                    }
                }
                $current_product_debug['asin'] = $asin;

                // --- Extract Brand - only look for brand text that appears before the title ---
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
                
                $current_product_debug['brand'] = $brand;
                $current_product_debug['brand_method'] = $brand_extraction_method;

                // --- Extract unit price using common utility ---
                $unit_price_data = ps_extract_amazon_unit_price($xpath, $element, $price_value, 'main');
                $unit_price = $unit_price_data['unit_price'];
                $unit = $unit_price_data['unit'];
                $unit_price_numeric = $unit_price_data['unit_price_numeric'];
                
                // Fallback to the old method if the new method doesn't work
                if (empty($unit_price)) {
                    $unitPriceNode = $xpath->query('.//span[contains(@class, "a-price a-text-price")]/span[@class="a-offscreen"]', $element)->item(0);
                    $unitTextNode = $xpath->query('.//span[contains(@class, "a-size-base a-color-secondary")]', $element)->item(0);
                    if ($unitPriceNode && $unitTextNode) {
                        $unit_price_val = trim($unitPriceNode->textContent);
                        // Try to extract unit (e.g., /100 ml) from the text node
                        if (preg_match('/\/([\d\w\s.]+)/', $unitTextNode->textContent, $unitMatch)) {
                            $unit = trim($unitMatch[1]);
                            $unit_price = $unit_price_val . '/' . $unit;
                        } else {
                            $unit_price = $unit_price_val;
                        }
                    }
                }
                $current_product_debug['unit_price'] = $unit_price;
                
                // Clear unit price data if no actual unit of measure exists
                if (!empty($unit_price) && (empty($unit) || !preg_match('/(?:ml|g|gram|grams|oz|ounce|ounces|lb|pound|pounds|kg|kilogram|kilograms|unit|count|piece|pieces|pack|packs|each|item|items|fl\s*oz)\b/i', $unit))) {
                    $unit_price = '';
                    $unit = '';
                    $current_product_debug['unit_cleared_no_valid_measure'] = true;
                }

                // --- Extract delivery time (enhanced to capture multiple delivery options) ---
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
                            } 
                            // Match FREE delivery without specific date
                            elseif (preg_match('/^(FREE\s+delivery[^.]*)/i', $delivery_time_full, $match)) {
                                $delivery_time = trim($match[1]);
                            }
                            // Match delivery with date but no FREE
                            elseif (preg_match('/^(.+?\b(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+[A-Z][a-z]{2,8}\s+\d{1,2})\b/', $delivery_time_full, $match)) {
                                $delivery_time = trim($match[1]);
                            } 
                            // Fallback to full text if no pattern matches
                            else {
                                $delivery_time = $delivery_time_full;
                            }
                        }
                    }
                }
                
                $current_product_debug['delivery_time'] = $delivery_time;
                $current_product_debug['delivery_method'] = $delivery_extraction_method;

                // --- Extract Rating ---
                $ratingNode = $xpath->query('.//span[contains(@class, "a-icon-alt")]', $element)->item(0);
                    if ($ratingNode) {
                        $rating_text = trim($ratingNode->textContent);
                    if (preg_match('/([0-9.]+)\s*out\s*of\s*5\s*stars/i', $rating_text, $matches_rating)) {
                        $rating_number = floatval($matches_rating[1]);
                    } elseif (preg_match('/([0-9.]+)/ ', $rating_text, $matches_rating)) { // Simpler match if "out of 5 stars" is missing
                         $rating_number = floatval($matches_rating[1]);
                    }
                }
                $current_product_debug['rating_text'] = $rating_text;
                $current_product_debug['rating_number'] = $rating_number;

                // --- Extract Rating Count ---
                $ratingCountNode = $xpath->query('.//a[contains(@class, "a-link-normal")]//span[contains(@class, "a-size-base") and contains(@class, "s-underline-text")]', $element)->item(0);
                    if ($ratingCountNode) {
                    $rating_count_str = trim($ratingCountNode->textContent);
                    $rating_count_str = preg_replace('/[^0-9,]/', '', $rating_count_str); // Keep numbers and commas, remove everything else
                    }
                $current_product_debug['rating_count_str'] = $rating_count_str;
                    
                // --- Rating Link ---
                    if ($rating_number > 0) {
                    $ratingLinkNode = $xpath->query('.//a[contains(@href, "customerReviews")]', $element)->item(0);
                    if ($ratingLinkNode) {
                        $rating_link = $ratingLinkNode->getAttribute('href');
                            if (strpos($rating_link, 'http') !== 0) {
                             $base_amazon_url = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
                            $rating_link = rtrim($base_amazon_url, '/') . $rating_link;
                        }
                         if (strpos($rating_link, 'tag=') === false && !empty($affiliate_id)) {
                                $rating_link .= (strpos($rating_link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                            }
                    } else if (!empty($link)) { // Fallback to product link + #customerReviews
                        $rating_link = $link . (strpos($link, '#customerReviews') === false ? '#customerReviews' : '');
                        }
                    }
                $current_product_debug['rating_link'] = $rating_link;
                    
                // Delivery time already extracted above using data-cy="delivery-recipe" method
                
                $debug_extraction_data[$idx] = $current_product_debug; // Store debug info

                // Only add product if essential data (title, link, price, image) is present
                if (!empty($title) && !empty($link) && $price_value > 0 && !empty($image)) {
                    // Create normalized link for duplicate detection (remove query parameters except essential ones)
                    $normalized_link = preg_replace('/[?&](?!tag=)[^=]*=[^&]*/', '', $link);
                    
                    // Check for duplicates using both link and ASIN
                    $is_duplicate = false;
                    if (!empty($asin) && in_array($asin, $seen_asins)) {
                        $is_duplicate = true;
                    } elseif (in_array($normalized_link, $seen_links)) {
                        $is_duplicate = true;
                    }
                    
                    if (!$is_duplicate) {
                        // Track this product to prevent future duplicates
                        $seen_links[] = $normalized_link;
                        if (!empty($asin)) {
                            $seen_asins[] = $asin;
                        }
                        
                        $product_data = array(
                            'title' => $title,
                            'link' => $link,
                            'price' => $price_str,
                            'price_value' => $price_value,
                            'image' => $image,
                            'price_per_unit' => $unit_price, // Now populated from extraction
                            'price_per_unit_value' => $price_value, // Will be updated if unit price is available
                            'unit' => $unit, // Now populated from extraction
                            'description' => substr($title, 0, 150) . '...',
                            'parsing_method' => 'xpath_role_listitem',
                            'asin' => $asin,
                            'brand' => $brand,
                            'title_extraction_method' => $title_extraction_method // For debugging
                        );
                        
                        if ($rating_number > 0) {
                            $product_data['rating_number'] = number_format($rating_number, 1);
                            $product_data['rating'] = str_repeat('★', round($rating_number)) . str_repeat('☆', 5 - round($rating_number));
                            if (!empty($rating_count_str)) {
                                $product_data['rating_count'] = $rating_count_str;
                            }
                            if (!empty($rating_link)) {
                                $product_data['rating_link'] = $rating_link;
                            }
                        }
                        if (!empty($delivery_time)) {
                            $product_data['delivery_time'] = $delivery_time;
                        }
                        
                        // Update price_per_unit_value if we have a unit price using common utility
                        if (!empty($unit_price)) {
                            // Extract numeric value from unit price (e.g., "$3.99/100ml" -> 3.99)
                            $unit_price_numeric = ps_extract_numeric_price($unit_price);
                            if ($unit_price_numeric > 0) {
                                $product_data['price_per_unit_value'] = $unit_price_numeric;
                            }
                        }
                        
                        // Apply minimum rating filter for display products
                        if ($rating_number >= $min_rating || $rating_number == 0) {
                            // Include products with no rating (rating_number == 0) or those meeting minimum rating
                            $products[] = $product_data;
                        }
                        
                        // Always add to raw cache regardless of rating filter
                        $raw_items_for_cache[] = $product_data;
                    }
                } else {
                    // ps_log_error("Product " . ($idx + 1) . " missing essential data. Title: '$title', Link: '$link', Price: $price_value, Image: '$image'");
                }
            }
            
            // Log detailed extraction attempts if debugging is enabled (e.g. via a constant or setting)
            if (defined('PS_DEBUG_PARSING') && PS_DEBUG_PARSING) {
                 foreach($debug_extraction_data as $prod_idx => $debug_data) {
                     // ps_log_error("Debug Extraction for Product #" . ($prod_idx +1) . ": " . json_encode($debug_data));
                }
            }
            
            if (!empty($products)) {
                // ps_log_error("Successfully extracted " . count($products) . " products using XPath parsing (div[@role=\"listitem\"]).");
            } else {
                 // ps_log_error("XPath (div[@role=\"listitem\"]): Found " . $productElements->length . " elements, but extracted 0 valid products after field extraction.");
            }
        } else {
            // ps_log_error("XPath: No product elements found with div[@role=\"listitem\"] selector.");
        }
        
        // --- Add Carousel Parsing for Sponsored Products ---
        // Look for carousel items that might be sponsored products
        $carouselElements = $xpath->query('//div[contains(@class, "puis-card-container")]');
        
        if ($carouselElements && $carouselElements->length > 0) {
            // ps_log_error("XPath: Found " . $carouselElements->length . " puis-card-container product elements");
            
            foreach ($carouselElements as $idx => $element) {
                $current_product_debug = []; // Debug info for the current product
                
                // Initialize product data
                $title = '';
                $link = '';
                $price_str = '';
                $price_value = 0;
                $image = '';
                $asin = '';
                $brand = '';
                $rating_text = '';
                $rating_number = 0;
                $rating_count_str = '';
                $rating_link = '';
                $delivery_time = '';
                $title_extraction_method = 'none';

                // --- Extract Title (using same methods as main parsing) ---
                // Method 1: h2 with aria-label
                $h2WithAriaLabel = $xpath->query('.//h2[@aria-label]', $element)->item(0);
                if ($h2WithAriaLabel) {
                    $title = trim(preg_replace('/\s+/', ' ', $h2WithAriaLabel->getAttribute('aria-label')));
                    $title_extraction_method = 'h2_aria_label';
                    $current_product_debug['title_h2_aria_label'] = $title;
                }
                
                // Method 2: span inside h2
                if (empty($title)) {
                    $spanInH2 = $xpath->query('.//h2//span', $element)->item(0);
                    if ($spanInH2) {
                        $title = trim(preg_replace('/\s+/', ' ', $spanInH2->textContent));
                        $title_extraction_method = 'h2_span';
                        $current_product_debug['title_h2_span'] = $title;
                    }
                }
                
                // Method 3: span with a-text-normal
                if (empty($title)) {
                    $spanNode = $xpath->query('.//span[contains(@class, "a-text-normal")]', $element)->item(0);
                    if ($spanNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $spanNode->textContent));
                        $title_extraction_method = 'span_a_text_normal';
                        $current_product_debug['title_span_a_text_normal'] = $title;
                    }
                }
                
                // Method 4: Generic h2 text content
                if (empty($title)) {
                    $titleNode = $xpath->query('.//h2', $element)->item(0);
                    if ($titleNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
                        $title_extraction_method = 'h2_text';
                        $current_product_debug['title_h2_text'] = $title;
                    }
                }
                
                $current_product_debug['title_final'] = $title;
                $current_product_debug['title_method'] = $title_extraction_method;

                // --- Extract Link ---
                $linkNode = $xpath->query('.//a[contains(@class, "a-link-normal") and .//img[contains(@class, "s-image")]]', $element)->item(0) ?? 
                            $xpath->query('.//h2//a[contains(@class, "a-link-normal")]', $element)->item(0) ?? 
                            $xpath->query('.//a[contains(@class, "a-link-normal")]', $element)->item(0);

                if ($linkNode) {
                    $link = $linkNode->getAttribute('href');
                    if (strpos($link, 'http') !== 0) {
                        $base_amazon_url = ($country === 'ca') ? 'https://www.amazon.ca' : 'https://www.amazon.com';
                        $link = rtrim($base_amazon_url, '/') . $link;
                    }
                    if (!preg_match('/[?&]tag=/', $link) && !empty($affiliate_id)) {
                        $link .= (strpos($link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                    }
                }
                $current_product_debug['link'] = $link;
                
                // Skip if title or link is empty
                if (empty($title) || empty($link)) {
                    continue;
                }

                // Clean title - remove "Sponsored Ad –" prefix if present
                $title = preg_replace('/^Sponsored Ad\s*[–-]\s*/i', '', $title);
                $current_product_debug['title_cleaned'] = $title;

                // --- Extract Price ---
                $priceNode = $xpath->query('.//span[@class="a-price"]/span[@class="a-offscreen"]', $element)->item(0) ??
                             $xpath->query('.//span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]', $element)->item(0);
                if ($priceNode) {
                    $price_str = trim($priceNode->textContent);
                    // Use common utility for price parsing
                    $price_value = ps_parse_price($price_str);
                }
                $current_product_debug['price_str'] = $price_str;
                $current_product_debug['price_value'] = $price_value;

                // --- Extract Image ---
                $imageNode = $xpath->query('.//img[contains(@class, "s-image")]', $element)->item(0);
                if ($imageNode) {
                    $image = $imageNode->getAttribute('src');
                    if (empty($image) || !preg_match('/\.(jpeg|jpg|gif|png)(?:\?.*)?$/i', $image)) {
                        $image = $imageNode->getAttribute('data-src');
                    }
                    if (empty($image) || !preg_match('/\.(jpeg|jpg|gif|png)(?:\?.*)?$/i', $image)) {
                        $image = '';
                    }
                }
                $current_product_debug['image'] = $image;

                // --- Extract ASIN ---
                if (!empty($link) && preg_match('/\/dp\/([A-Z0-9]{10})/', $link, $matches)) {
                    $asin = $matches[1];
                } else {
                    $asin_data_attribute = $element->getAttribute('data-asin');
                    if(!empty($asin_data_attribute)) {
                        $asin = $asin_data_attribute;
                    }
                }
                $current_product_debug['asin'] = $asin;

                // Create product if we have essential data
                if (!empty($title) && !empty($link) && $price_value > 0 && !empty($image)) {
                    // Create normalized link for duplicate detection (remove query parameters except essential ones)
                    $normalized_link = preg_replace('/[?&](?!tag=)[^=]*=[^&]*/', '', $link);
                    
                    // Check for duplicates using both link and ASIN
                    $is_duplicate = false;
                    if (!empty($asin) && in_array($asin, $seen_asins)) {
                        $is_duplicate = true;
                    } elseif (in_array($normalized_link, $seen_links)) {
                        $is_duplicate = true;
                    }
                    
                    if (!$is_duplicate) {
                        // Track this product to prevent future duplicates
                        $seen_links[] = $normalized_link;
                        if (!empty($asin)) {
                            $seen_asins[] = $asin;
                        }
                        
                        $product_data = array(
                            'title' => $title,
                            'link' => $link,
                            'price' => $price_str,
                            'price_value' => $price_value,
                            'image' => $image,
                            'price_per_unit' => '',
                            'price_per_unit_value' => $price_value,
                            'unit' => '',
                            'description' => substr($title, 0, 150) . '...',
                            'parsing_method' => 'xpath_puis_card',
                            'asin' => $asin,
                            'brand' => $brand,
                            'title_extraction_method' => $title_extraction_method
                        );
                        
                        // Add to both display products and raw cache
                        $products[] = $product_data;
                        $raw_items_for_cache[] = $product_data;
                        
                        // ps_log_error("Puis-card product added: " . substr($title, 0, 50) . "... (Price: $price_str)");
                    }
                } else {
                    // ps_log_error("Puis-card product " . ($idx + 1) . " missing essential data. Title: '$title', Link: '$link', Price: $price_value, Image: '$image'");
                }
            }
            
            if (count($carouselElements) > 0) {
                // ps_log_error("Successfully processed " . count($carouselElements) . " puis-card elements.");
            }
        } else {
            // ps_log_error("XPath: No puis-card-container product elements found.");
        }
        
    } catch (Exception $e) {
        // ps_log_error("XPath parsing failed with error: " . $e->getMessage());
    }

    $raw_items_count_for_cache = count($raw_items_for_cache);

    // Extract pagination URLs for pages 2 and 3
    $pagination_urls = ps_extract_pagination_urls($html, $country);
    
    // Log duplicate prevention statistics
    $total_unique_products = count($raw_items_for_cache);
    $total_seen_links = count($seen_links);
    $total_seen_asins = count($seen_asins);
    // ps_log_error("Duplicate prevention summary: {$total_unique_products} unique products added, {$total_seen_links} unique links tracked, {$total_seen_asins} unique ASINs tracked");

    // Return a structured array containing both display products and raw products for caching
    return array(
        'success' => !empty($products), // Overall success if any products were displayable
        'items'   => $products,          // Products for display (potentially filtered later)
        'count'   => count($products),    // Count of displayable products
        'raw_items_for_cache' => $raw_items_for_cache, // All items successfully parsed, for base caching
        'raw_items_count_for_cache' => $raw_items_count_for_cache, // Count of raw items
        'pagination_urls' => $pagination_urls, // URLs for pages 2 and 3
        'message' => empty($products) ? 'No products found or extracted successfully.' : ''
    );
}

/**
 * Extract pagination URLs for pages 2 and 3 from Amazon search results
 *
 * @param string $html The HTML content
 * @param string $country Country code ('us' or 'ca')
 * @return array Array with page URLs
 */
function ps_extract_pagination_urls($html, $country = 'us') {
    $pagination_urls = array();
    $base_url = $country === 'ca' ? 'https://www.amazon.ca' : 'https://www.amazon.com';
    
    // First check if pagination section exists at all
    if (strpos($html, 's-pagination-container') === false) {
        // ps_log_error("No pagination container found in HTML");
        return $pagination_urls;
    }
    
    // ps_log_error("Found pagination container, extracting URLs...");
    
    // Use regex to find all pagination links directly from HTML
    // This pattern works based on our test results
    $patterns = array(
        // Primary pattern for Amazon's current pagination structure - TESTED AND WORKING
        '/href="([^"]*page=([23])[^"]*)"[^>]*aria-label="Go to page [23]"[^>]*class="[^"]*s-pagination-button[^"]*"/i',
        // Alternative pattern in case order is different
        '/aria-label="Go to page ([23])"[^>]*href="([^"]*page=\\1[^"]*)"/i',
        // Simple fallback - any href with page=2 or page=3 in pagination context
        '/s-pagination[^>]*>.*?href="([^"]*page=([23])[^"]*)"/is',
        // Very broad pattern as last resort
        '/href="([^"]*[?&]page=([23])[^"]*)"/i'
    );
    
    foreach ($patterns as $index => $pattern) {
        // ps_log_error("Trying pagination pattern " . ($index + 1));
        
        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
            // ps_log_error("Pattern " . ($index + 1) . " found " . count($matches) . " matches");
            
            foreach ($matches as $match) {
                // Handle different capture group orders
                if (isset($match[2]) && is_numeric($match[2])) {
                    // Pattern with page number as second capture group
                    $url = $match[1];
                    $page_num = $match[2];
                } else if (isset($match[1]) && is_numeric($match[1]) && isset($match[2])) {
                    // Pattern with page number as first capture group, URL as second
                    $url = $match[2];
                    $page_num = $match[1];
                } else {
                    // Fallback - extract page number from URL
                    $url = $match[1];
                    if (preg_match('/page=([23])/', $url, $page_match)) {
                        $page_num = $page_match[1];
                    } else {
                        continue;
                    }
                }
                
                // Only take pages 2 and 3
                if ($page_num == '2' || $page_num == '3') {
                    // Clean up the URL and make it absolute
                    $clean_url = html_entity_decode($url, ENT_QUOTES | ENT_HTML401, 'UTF-8');
                    if (strpos($clean_url, 'http') !== 0) {
                        $clean_url = $base_url . $clean_url;
                    }
                    
                    // FIX: Ensure the URL uses the correct country domain
                    // Amazon sometimes returns .com URLs even when on .ca site
                    if ($country === 'ca' && strpos($clean_url, 'amazon.com') !== false) {
                        $clean_url = str_replace('amazon.com', 'amazon.ca', $clean_url);
                        // ps_log_error("Fixed pagination URL for page " . $page_num . ": corrected .com to .ca domain");
                    } elseif ($country === 'us' && strpos($clean_url, 'amazon.ca') !== false) {
                        $clean_url = str_replace('amazon.ca', 'amazon.com', $clean_url);
                        // ps_log_error("Fixed pagination URL for page " . $page_num . ": corrected .ca to .com domain");
                    }
                    
                    // Remove everything after the page parameter to clean up the URL
                    if (preg_match('/^(.*[?&]page=' . $page_num . ')(&.*)?$/', $clean_url, $url_match)) {
                        $clean_url = $url_match[1]; // Keep only up to and including the page parameter
                        // ps_log_error("Cleaned pagination URL for page " . $page_num . ": removed extra parameters");
                    }
                    
                    // Store with the key format expected by JavaScript and load more function
                    $pagination_urls['page_' . $page_num] = $clean_url;
                    // ps_log_error("Added pagination URL for page " . $page_num . ": " . $clean_url);
                }
            }
            
            // If we found URLs with this pattern, stop trying other patterns
            if (!empty($pagination_urls)) {
                break;
            }
        } else {
            // ps_log_error("Pattern " . ($index + 1) . " found no matches");
        }
    }
    
    // ps_log_error("Successfully extracted pagination URLs: " . json_encode(array_keys($pagination_urls)));
    
    // Also log the actual URLs for debugging
    foreach ($pagination_urls as $key => $url) {
        // ps_log_error("Pagination URL stored: {$key} => " . substr($url, 0, 100) . (strlen($url) > 100 ? '...' : ''));
    }
    
    return $pagination_urls;
}

/**
 * Sanitize HTML for better XML parsing
 * 
 * @param string $html The HTML content to sanitize
 * @return string Sanitized HTML
 */
function ps_sanitize_html_for_parsing($html) {
    // Fix common encoding issues
    $html = preg_replace('/<!\[CDATA\[.*?\]\]>/s', '', $html); // Remove CDATA sections
    $html = preg_replace('/<!--.*?-->/s', '', $html); // Remove comments
    $html = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;|nbsp;)/', '&amp;', $html); // Fix unescaped ampersands
    
    // Add proper DOCTYPE and html/body tags if missing
    if (strpos($html, '<!DOCTYPE') === false) {
        $html = '<!DOCTYPE html>' . $html;
    }
    
    if (strpos($html, '<html') === false) {
        $html = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
    }
    
    return $html;
}

/**
 * Log errors to the error log
 * 
 * @param string $message The message to log
 */
function ps_log_error($message) {
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
    
    $error_log_file = $logs_dir . '/error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
    
    file_put_contents($error_log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Save a sample of the response for debugging
 *
 * @param string $html The HTML content
 */
function ps_save_response_sample($html) {
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $sample_file = $logs_dir . "/amazon_response_{$timestamp}.html";
    
    // Save only first 1MB to avoid huge files
    $content_to_save = strlen($html) > 1048576 ? substr($html, 0, 1048576) : $html;
    
    file_put_contents($sample_file, $content_to_save);
    
    // Log the save
    ps_log_error("Amazon response sample saved: {$sample_file} (Size: " . number_format(strlen($html)) . " bytes)");
}

/**
 * Get the appropriate associate tag for the given country
 */
function ps_get_associate_tag($country = 'us') {
    $settings = get_option('ps_settings');
    
    // Use country-specific tag if available
    if ($country === 'us' && isset($settings['amazon_associate_tag_us'])) {
        return $settings['amazon_associate_tag_us'];
    } elseif ($country === 'ca' && isset($settings['amazon_associate_tag_ca'])) {
        return $settings['amazon_associate_tag_ca'];
    }
    
    // Fallback to default tags based on country
    if ($country === 'ca') {
        return PS_AFFILIATE_ID; // CA tag
    } else {
        return 'primatesshopp-20'; // US tag
    }
}

/**
 * Construct Amazon search URL
 */
function ps_construct_amazon_search_url($query, $country = 'us', $page = 1) {
    $base_url = $country === 'ca' ? 'https://www.amazon.ca' : 'https://www.amazon.com';
    $url = $base_url . '/s?k=' . urlencode($query);
    
    // Only add page parameter if it's not the first page
    if ($page > 1) {
        $url .= '&page=' . $page;
    }
    
    return $url;
}

/**
 * Fetch Amazon search results
 *
 * @param string $url The Amazon search URL
 * @param string $country Country code ('us' or 'ca')
 * @return string|false The HTML content or false on failure
 */
function ps_fetch_amazon_search_results($url, $country = 'us', $force_proxy = false) {
    ps_log_error("Fetching search results from URL: {$url}");
    ps_log_error("DEBUG: Checking proxy constants - HOST defined: " . (defined('PS_DECODO_PROXY_HOST') ? 'YES' : 'NO') . ", PORT defined: " . (defined('PS_DECODO_PROXY_PORT') ? 'YES' : 'NO'));
    
    // Check if we're on the current network to determine proxy usage
    $on_current_network = ps_is_on_current_network();
    $should_use_proxy = $on_current_network || $force_proxy;
    
    if ($force_proxy) {
        ps_log_error("PROXY FALLBACK: Forcing proxy usage due to previous blocking");
    }
    
    ps_log_error("Network detection result: On current network = " . ($on_current_network ? 'YES' : 'NO') . ", Will use proxy = " . ($should_use_proxy ? 'YES' : 'NO'));
    ps_log_error("PROXY DECISION: " . ($should_use_proxy ? 'USING PROXY' : 'DIRECT REQUEST') . " for URL: {$url}");
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Configure proxy settings (Decodo proxy service) - only if needed
    if ($should_use_proxy && defined('PS_DECODO_PROXY_HOST') && defined('PS_DECODO_PROXY_PORT')) {
        $proxy_host = PS_DECODO_PROXY_HOST;
        $proxy_port = PS_DECODO_PROXY_PORT;
        
        ps_log_error("PROXY EXECUTION: Using Decodo proxy: {$proxy_host}:{$proxy_port}");
        
        curl_setopt($ch, CURLOPT_PROXY, $proxy_host);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxy_port);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        
        // Add proxy authentication if credentials are defined
        if (defined('PS_DECODO_USER_BASE') && defined('PS_DECODO_PASSWORD')) {
            $proxy_username = PS_DECODO_USER_BASE . '-country-' . $country; // Add country suffix
            $proxy_password = PS_DECODO_PASSWORD;
            
            ps_log_error("DEBUG PROXY AUTH: PS_DECODO_USER_BASE = '" . PS_DECODO_USER_BASE . "'");
            ps_log_error("DEBUG PROXY AUTH: Country = '{$country}'");
            ps_log_error("DEBUG PROXY AUTH: Constructed proxy username = '{$proxy_username}'");
            ps_log_error("Using proxy authentication with user: {$proxy_username}");
            
            // Validate that we have the correct format
            if (strpos($proxy_username, '-') === false) {
                ps_log_error("WARNING: Proxy username does not contain country suffix! Format should be 'user-sptlq8hpk0-{$country}'");
            } else {
                ps_log_error("CONFIRMED: Proxy username has correct country-specific format");
            }
            
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$proxy_username}:{$proxy_password}");
        } else {
            ps_log_error("Warning: Proxy authentication credentials not defined");
            ps_log_error("DEBUG: PS_DECODO_USER_BASE defined: " . (defined('PS_DECODO_USER_BASE') ? 'YES' : 'NO'));
            ps_log_error("DEBUG: PS_DECODO_PASSWORD defined: " . (defined('PS_DECODO_PASSWORD') ? 'YES' : 'NO'));
        }
    } elseif ($should_use_proxy) {
        ps_log_error("PROXY EXECUTION: Warning - Proxy constants not defined but proxy is needed - making direct request to Amazon");
    } else {
        ps_log_error("PROXY EXECUTION: Making direct request to Amazon (no proxy)");
    }
    
    // Set user agent to mimic a browser
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36');
    
    // Enable automatic decompression of gzip/deflate responses
    curl_setopt($ch, CURLOPT_ENCODING, ''); // Empty string means "accept all encodings"
    
    // Add headers to make the request look more like a browser
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Accept-Encoding: gzip, deflate, br',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'Cache-Control: max-age=0'
    ]);
    
    // Execute the request
    $html_content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for errors
    if ($html_content === false) {
        $error = curl_error($ch);
        ps_log_error("cURL Error: " . $error);
        curl_close($ch);
        return false;
    }
    
    // Close cURL handle
    curl_close($ch);
    
    // Check for successful response
    if ($http_code !== 200) {
        ps_log_error("HTTP Error: " . $http_code);
        // Return error information instead of just false
        return array(
            'error' => true,
            'http_code' => $http_code,
            'message' => 'HTTP Error: ' . $http_code
        );
    }
    
    // Save the original response for debugging (before optimization)
    ps_save_response_sample($html_content);
    
    // Check if bandwidth optimization is enabled
    $settings = get_option('ps_settings');
    $bandwidth_optimization = isset($settings['bandwidth_optimization']) ? $settings['bandwidth_optimization'] : 1; // Default enabled
    
    if ($bandwidth_optimization) {
        // Apply bandwidth optimization - extract only product-related HTML
        $optimized_html = ps_extract_product_html($html_content);
        return $optimized_html;
    } else {
        ps_log_error("Bandwidth optimization disabled - returning full HTML");
        return $html_content;
    }
}

/**
 * Extract only product-related HTML content to save bandwidth
 * This function removes unnecessary HTML like scripts, styles, navigation, etc.
 * and keeps only the essential product listing content.
 *
 * @param string $html The full HTML content
 * @return string Stripped HTML containing only product-related content
 */
function ps_extract_product_html($html) {
    // ps_log_error("Starting bandwidth optimization - extracting product-only HTML");
    
    $original_size = strlen($html);
    
    try {
        // Create DOMDocument to parse HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Create a new minimal document
        $newDom = new DOMDocument();
        $newDom->formatOutput = false;
        
        // Create basic HTML structure
        $htmlElement = $newDom->createElement('html');
        $bodyElement = $newDom->createElement('body');
        $htmlElement->appendChild($bodyElement);
        $newDom->appendChild($htmlElement);
        
        // Extract the main search results container
        $searchResults = $xpath->query('//div[@data-component-type="s-search-result"]');
        if ($searchResults->length > 0) {
            // ps_log_error("Found " . $searchResults->length . " search result containers");
            
            // Create a container for all products
            $containerDiv = $newDom->createElement('div');
            $containerDiv->setAttribute('id', 'search-results-container');
            
            foreach ($searchResults as $result) {
                // Import the product node to the new document
                $importedNode = $newDom->importNode($result, true);
                $containerDiv->appendChild($importedNode);
            }
            
            $bodyElement->appendChild($containerDiv);
        } else {
            // Fallback: try to find products using role="listitem"
            $listItems = $xpath->query('//div[@role="listitem"]');
            if ($listItems->length > 0) {
                // ps_log_error("Found " . $listItems->length . " listitem containers (fallback method)");
                
                $containerDiv = $newDom->createElement('div');
                $containerDiv->setAttribute('id', 'search-results-container');
                
                foreach ($listItems as $item) {
                    $importedNode = $newDom->importNode($item, true);
                    $containerDiv->appendChild($importedNode);
                }
                
                $bodyElement->appendChild($containerDiv);
            } else {
                // Last resort: try to find any product-like containers
                $productContainers = $xpath->query('//div[contains(@class, "s-result-item") or contains(@class, "sg-col-inner")]');
                if ($productContainers->length > 0) {
                    // ps_log_error("Found " . $productContainers->length . " product containers (last resort method)");
                    
                    $containerDiv = $newDom->createElement('div');
                    $containerDiv->setAttribute('id', 'search-results-container');
                    
                    foreach ($productContainers as $container) {
                        $importedNode = $newDom->importNode($container, true);
                        $containerDiv->appendChild($importedNode);
                    }
                    
                    $bodyElement->appendChild($containerDiv);
                } else {
                    // ps_log_error("No product containers found - returning original HTML");
                    return $html; // Return original if we can't find products
                }
            }
        }
        
        // IMPORTANT: Also preserve the pagination container for load more functionality
        $paginationContainers = $xpath->query('//div[contains(@class, "s-pagination-container")]');
        if ($paginationContainers->length > 0) {
            // ps_log_error("Found " . $paginationContainers->length . " pagination containers - preserving for load more functionality");
            
            foreach ($paginationContainers as $pagination) {
                $importedPagination = $newDom->importNode($pagination, true);
                $bodyElement->appendChild($importedPagination);
            }
        } else {
            // ps_log_error("No pagination containers found - load more functionality may not work");
        }
        
        // Get the optimized HTML
        $optimizedHtml = $newDom->saveHTML();
        
        // Additional cleanup to remove any remaining unwanted elements
        $optimizedHtml = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $optimizedHtml);
        $optimizedHtml = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $optimizedHtml);
        $optimizedHtml = preg_replace('/<link[^>]*>/mi', '', $optimizedHtml);
        $optimizedHtml = preg_replace('/<meta[^>]*>/mi', '', $optimizedHtml);
        $optimizedHtml = preg_replace('/<!--.*?-->/s', '', $optimizedHtml);
        
        // Remove excessive whitespace
        $optimizedHtml = preg_replace('/\s+/', ' ', $optimizedHtml);
        $optimizedHtml = trim($optimizedHtml);
        
        $optimized_size = strlen($optimizedHtml);
        $savings_percent = round((($original_size - $optimized_size) / $original_size) * 100, 1);
        
        // ps_log_error("Bandwidth optimization complete - Original: " . number_format($original_size) . " bytes, Optimized: " . number_format($optimized_size) . " bytes, Savings: {$savings_percent}%");
        
        return $optimizedHtml;
        
    } catch (Exception $e) {
        // ps_log_error("Error during HTML optimization: " . $e->getMessage() . " - returning original HTML");
        return $html; // Return original HTML if optimization fails
    }
}

/**
 * Check if the current request is from the configured "current network"
 * This can be used to determine whether to use proxy or direct connection
 *
 * @return bool True if on current network, false otherwise
 */
function ps_is_on_current_network() {
    // Get current network settings
    $settings = get_option('ps_settings');
    $use_network_detection = isset($settings['use_network_detection']) ? $settings['use_network_detection'] : 0;
    
    // If network detection is disabled, always use proxy (existing behavior)
    if (!$use_network_detection) {
        ps_log_error("Network detection disabled - will NOT use proxy");
        return false;
    }
    
    // Get the current server's IP address
    $current_ip = ps_get_server_ip();
    ps_log_error("Network detection: Current server IP: {$current_ip}");
    
    // Check various network indicators
    $network_indicators = array();
    
    // 1. Check if we have a configured network IP range
    $current_network_range = isset($settings['current_network_range']) ? trim($settings['current_network_range']) : '';
    if (!empty($current_network_range)) {
        $is_in_range = ps_ip_in_range($current_ip, $current_network_range);
        $network_indicators['ip_range'] = $is_in_range;
        ps_log_error("Network detection: IP range check ({$current_network_range}): " . ($is_in_range ? 'YES' : 'NO'));
    }
    
    // 2. Check if the main server IP is private/local (only as fallback indicator)
    $is_main_ip_private = ps_is_local_or_private_ip($current_ip);
    if ($is_main_ip_private) {
        $network_indicators['private_ip'] = true;
        ps_log_error("Network detection: Main IP ({$current_ip}): PRIVATE/LOCAL");
    } else {
        ps_log_error("Network detection: Main IP ({$current_ip}): PUBLIC");
    }
    
    // 3. Check for specific hostname patterns
    $current_network_hostnames = isset($settings['current_network_hostnames']) ? trim($settings['current_network_hostnames']) : '';
    if (!empty($current_network_hostnames)) {
        $hostnames = array_map('trim', explode(',', $current_network_hostnames));
        $current_hostname = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
        
        $hostname_match = false;
        foreach ($hostnames as $pattern) {
            if (!empty($pattern) && (strpos($current_hostname, $pattern) !== false || fnmatch($pattern, $current_hostname))) {
                $hostname_match = true;
                break;
            }
        }
        $network_indicators['hostname'] = $hostname_match;
        ps_log_error("Network detection: Hostname check ({$current_hostname} in [{$current_network_hostnames}]): " . ($hostname_match ? 'MATCH' : 'NO MATCH'));
    }
    
    // Determine if we're on current network based on indicators
    $on_current_network = false;
    
    // If any indicator suggests we're on the current network, consider it true
    foreach ($network_indicators as $indicator => $result) {
        if ($result === true) {
            $on_current_network = true;
            ps_log_error("Network detection: Identified as current network based on: {$indicator}");
            break;
        }
    }
    
    ps_log_error("Network detection: Final result - On current network: " . ($on_current_network ? 'YES' : 'NO') . " (will " . ($on_current_network ? 'use proxy' : 'NOT use proxy') . ")");
    
    return $on_current_network;
}

/**
 * Get the server's IP address
 *
 * @return string The server's IP address
 */
function ps_get_server_ip() {
    // Try various methods to get the server's actual IP
    $ip_sources = array(
        'SERVER_ADDR',
        'LOCAL_ADDR', 
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CF_CONNECTING_IP',
        'REMOTE_ADDR'
    );
    
    foreach ($ip_sources as $source) {
        if (isset($_SERVER[$source]) && !empty($_SERVER[$source])) {
            $ip = trim($_SERVER[$source]);
            // Handle comma-separated IPs (from proxies)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    // Fallback: try to get external IP via API call
    $external_ip = ps_get_external_ip();
    if ($external_ip) {
        return $external_ip;
    }
    
    // Last resort
    return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
}

/**
 * Get external IP address via API call
 *
 * @return string|false External IP address or false on failure
 */
function ps_get_external_ip() {
    $services = array(
        'https://api.ipify.org',
        'https://ipecho.net/plain',
        'https://api.my-ip.io/ip'
    );
    
    foreach ($services as $service) {
        $response = wp_remote_get($service, array('timeout' => 5));
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $ip = trim(wp_remote_retrieve_body($response));
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return false;
}

/**
 * Check if an IP address is in a given range
 *
 * @param string $ip The IP address to check
 * @param string $range The IP range (e.g., "192.168.1.0/24" or "192.168.1.1-192.168.1.255")
 * @return bool True if IP is in range, false otherwise
 */
function ps_ip_in_range($ip, $range) {
    if (empty($ip) || empty($range)) {
        return false;
    }
    
    // Handle CIDR notation (e.g., 192.168.1.0/24)
    if (strpos($range, '/') !== false) {
        return ps_ip_in_cidr($ip, $range);
    }
    
    // Handle range notation (e.g., 192.168.1.1-192.168.1.255)
    if (strpos($range, '-') !== false) {
        list($start, $end) = explode('-', $range, 2);
        return ps_ip_in_range_start_end($ip, trim($start), trim($end));
    }
    
    // Handle single IP
    return $ip === trim($range);
}

/**
 * Check if an IP address is in a CIDR range
 *
 * @param string $ip The IP address to check
 * @param string $cidr The CIDR range (e.g., "192.168.1.0/24")
 * @return bool True if IP is in range, false otherwise
 */
function ps_ip_in_cidr($ip, $cidr) {
    list($network, $mask) = explode('/', $cidr, 2);
    
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($network, FILTER_VALIDATE_IP)) {
        return false;
    }
    
    $ip_long = ip2long($ip);
    $network_long = ip2long($network);
    $mask = (0xffffffff << (32 - (int)$mask)) & 0xffffffff;
    
    return ($ip_long & $mask) === ($network_long & $mask);
}

/**
 * Check if an IP address is between two IP addresses
 *
 * @param string $ip The IP address to check
 * @param string $start The start IP address
 * @param string $end The end IP address
 * @return bool True if IP is in range, false otherwise
 */
function ps_ip_in_range_start_end($ip, $start, $end) {
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($start, FILTER_VALIDATE_IP) || !filter_var($end, FILTER_VALIDATE_IP)) {
        return false;
    }
    
    $ip_long = ip2long($ip);
    $start_long = ip2long($start);
    $end_long = ip2long($end);
    
    return $ip_long >= $start_long && $ip_long <= $end_long;
}

/**
 * Check if an IP address is localhost or in private IP ranges
 *
 * @param string $ip The IP address to check
 * @return bool True if IP is local/private, false otherwise
 */
function ps_is_local_or_private_ip($ip) {
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    
    // Check if it's localhost
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return true;
    }
    
    // Check if it's in private IP ranges
    return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}
