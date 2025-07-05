<?php
/**
 * eBay Scraper integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search eBay products using web scraping
 *
 * @param string $query The main search query
 * @param string $exclude_keywords Keywords to exclude from results
 * @param string $sort_by Sorting method (price, price_per_unit)
 * @param string $country Country code ('us' or 'ca')
 * @param float $min_rating Minimum rating filter (3.5, 4.0, 4.5)
 * @param int $page Page number for pagination (default: 1)
 * @return array Search results
 */
function ps_search_ebay_products($query, $exclude_keywords = '', $sort_by = 'price', $country = 'us', $min_rating = 4.0, $page = 1) {
    // If query is empty, return empty array
    if (empty($query)) {
        return array();
    }
    
    // ps_log_error("Initiating eBay search for: '{$query}' in country: {$country}, page: {$page}");
    
    // Construct the eBay search URL with pagination
    $search_url = ps_construct_ebay_search_url($query, $country, $page);
    // ps_log_error("Constructed eBay search URL: {$search_url}");
    
    // Get the search results HTML
    $html_content = ps_fetch_ebay_search_results($search_url, $country);
    
    // Check if we got an error response
    if (is_array($html_content) && isset($html_content['error']) && $html_content['error'] === true) {
        $http_code = $html_content['http_code'];
        // ps_log_error("Failed to fetch eBay search results for query: '{$query}' page {$page} - HTTP {$http_code}");
        
        // Check if it's a blocking error (503, 429, etc.)
        if (in_array($http_code, [503, 429, 403, 502, 504])) {
            // Create eBay search URL for the user to continue searching manually
            $ebay_base = ($country === 'ca') ? 'https://www.ebay.ca' : 'https://www.ebay.com';
            $ebay_search_url = $ebay_base . '/sch/i.html?_nkw=' . urlencode($query);
            
            return array(
                'success' => false,
                'items' => array(),
                'count' => 0,
                'message' => 'eBay is blocking requests. Please try again later.<br><br>Or continue search on <a href="' . $ebay_search_url . '" target="_blank" rel="noopener">' . $ebay_search_url . '</a>',
                'ebay_search_url' => $ebay_search_url,
                'search_query' => $query,
                'country' => $country,
                'http_code' => $http_code
            );
        } else {
            // Create eBay search URL for the user to continue searching manually
            $ebay_base = ($country === 'ca') ? 'https://www.ebay.ca' : 'https://www.ebay.com';
            $ebay_search_url = $ebay_base . '/sch/i.html?_nkw=' . urlencode($query);
            
            return array(
                'success' => false,
                'items' => array(),
                'count' => 0,
                'message' => 'Failed to connect to eBay. Please try again later.<br><br>Or continue search on <a href="' . $ebay_search_url . '" target="_blank" rel="noopener">' . $ebay_search_url . '</a>',
                'ebay_search_url' => $ebay_search_url,
                'search_query' => $query,
                'country' => $country,
                'http_code' => $http_code
            );
        }
    }
    
    if (empty($html_content)) {
        // ps_log_error("Failed to fetch eBay search results for query: '{$query}' page {$page} - No response received");
        
        // Create eBay search URL for the user to continue searching manually
        $ebay_base = ($country === 'ca') ? 'https://www.ebay.ca' : 'https://www.ebay.com';
        $ebay_search_url = $ebay_base . '/sch/i.html?_nkw=' . urlencode($query);
        
        return array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => 'No response received from eBay. Please try again later.<br><br>Or continue search on <a href="' . $ebay_search_url . '" target="_blank" rel="noopener">' . $ebay_search_url . '</a>',
            'ebay_search_url' => $ebay_search_url,
            'search_query' => $query,
            'country' => $country
        );
    }
    
    // Check if eBay is blocking the request
    if (ps_is_ebay_blocking($html_content)) {
        // ps_log_error("eBay is blocking search for query: '{$query}' page {$page} - Blocking page detected");
        
        // Create eBay search URL for the user to continue searching manually
        $ebay_base = ($country === 'ca') ? 'https://www.ebay.ca' : 'https://www.ebay.com';
        $ebay_search_url = $ebay_base . '/sch/i.html?_nkw=' . urlencode($query);
        
        return array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => 'eBay is blocking requests. Please try again later.<br><br>Or continue search on <a href="' . $ebay_search_url . '" target="_blank" rel="noopener">' . $ebay_search_url . '</a>',
            'ebay_search_url' => $ebay_search_url,
            'search_query' => $query,
            'country' => $country
        );
    }
    
    // Check if it's a valid search page
    if (!ps_is_valid_ebay_search_page($html_content)) {
        // ps_log_error("Invalid eBay search results format: " . substr($html_content, 0, 100));
        
        // Create eBay search URL for the user to continue searching manually
        $ebay_base = ($country === 'ca') ? 'https://www.ebay.ca' : 'https://www.ebay.com';
        $ebay_search_url = $ebay_base . '/sch/i.html?_nkw=' . urlencode($query);
        
        $invalid_response = array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => 'Invalid response from eBay. Please try again later.<br><br>Or continue search on <a href="' . $ebay_search_url . '" target="_blank" rel="noopener">' . $ebay_search_url . '</a>',
            'ebay_search_url' => $ebay_search_url,
            'search_query' => $query,
            'country' => $country
        );
        
        // ps_log_error("INVALID_EBAY_RESPONSE_RETURN: " . json_encode($invalid_response));
        
        return $invalid_response;
    }
    
    // ps_log_error("Parsing eBay search results for {$country}");
    
    // Parse the search results HTML
    $products = ps_parse_ebay_results($html_content, $min_rating, $country);
    
    return $products;
}

/**
 * Check if eBay is blocking the request
 */
function ps_is_ebay_blocking($html) {
    if (empty($html)) {
        return false;
    }
    
    // eBay blocking indicators
    $blocking_indicators = array(
        'blocked for policy violating',
        'temporarily unavailable',
        'security measure',
        'unusual traffic',
        'verify you are human',
        'captcha',
        'access denied',
        'your request appears to be from an automated process',
        'blocked by ebay',
        'ip address blocked'
    );
    
    $html_lower = strtolower($html);
    
    foreach ($blocking_indicators as $indicator) {
        if (strpos($html_lower, $indicator) !== false) {
            // ps_log_error("eBay blocking detected: '{$indicator}'");
            return true;
        }
    }
    
    return false;
}

/**
 * Check if the page contains valid eBay search results
 */
function ps_is_valid_ebay_search_page($html) {
    if (empty($html)) {
        return false;
    }
    
    // Look for eBay search result indicators
    $valid_indicators = array(
        'class="s-item',
        'data-view="mi:',
        'id="srp-river-results"',
        '"srp-results"',
        'class="srp-results',
        'data-testid="item-card"'
    );
    
    foreach ($valid_indicators as $indicator) {
        if (strpos($html, $indicator) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Parse eBay search results HTML
 */
function ps_parse_ebay_results($html, $min_rating = 4.0, $country = 'us') {
    // ps_log_error("Starting eBay results parsing");
    
    $products = array();
    
    try {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // eBay search result item selectors - use more specific selectors to avoid nested duplicates
        $item_selectors = array(
            '//div[contains(@class, "s-item") and not(ancestor::div[contains(@class, "s-item")])]',
            '//div[@data-view="mi:1686|iid:1" and not(ancestor::div[@data-view="mi:1686|iid:1"])]',
            '//div[contains(@class, "srp-item") and not(ancestor::div[contains(@class, "srp-item")])]'
        );
        
        $items = array();
        foreach ($item_selectors as $selector) {
            $found_items = $xpath->query($selector);
            if ($found_items->length > 0) {
                // ps_log_error("Found " . $found_items->length . " items using selector: " . $selector);
                $items = $found_items;
                
                // Debug: Log first few item classes to understand structure
                for ($i = 0; $i < min(3, $found_items->length); $i++) {
                    $item_element = $found_items->item($i);
                    if ($item_element instanceof DOMElement) {
                        $item_class = $item_element->getAttribute('class');
                        $item_id = $item_element->getAttribute('id');
                        // eBay debug removed
                    }
                }
                break;
            }
        }
        
        if ($items->length === 0) {
            // ps_log_error("No eBay items found with any selector");
            return array(
                'success' => true,
                'items' => array(),
                'count' => 0,
                'raw_items_for_cache' => array(),
                'raw_items_count_for_cache' => 0
            );
        }
        
        // ps_log_error("Processing " . $items->length . " eBay items");
        
        $raw_items_for_cache = array();
        
        foreach ($items as $index => $item) {
            try {
                // eBay debug removed
                $product_data = ps_extract_ebay_product_data($item, $xpath, $country);
                
                if ($product_data && !empty($product_data['title'])) {
                    // eBay debug removed
                    
                    // Apply minimum rating filter for eBay sellers
                    if (isset($product_data['rating_numeric']) && is_numeric($product_data['rating_numeric'])) {
                        $product_rating = floatval($product_data['rating_numeric']);
                        if ($product_rating < $min_rating) {
                            // ps_log_error("Skipping eBay product '" . substr($product_data['title'], 0, 50) . "...' - seller rating {$product_rating} below minimum {$min_rating}");
                            continue; // Skip this product
                        }
                    }
                    
                    // Check for duplicates based on title and link before adding
                    $is_duplicate = false;
                    foreach ($raw_items_for_cache as $existing_item) {
                        // Check for duplicate title
                        if (isset($existing_item['title']) && $existing_item['title'] === $product_data['title']) {
                            $is_duplicate = true;
                            // eBay debug removed
                            break;
                        }
                        // Check for duplicate link (same eBay item)
                        if (isset($existing_item['link']) && isset($product_data['link']) && 
                            !empty($existing_item['link']) && !empty($product_data['link']) &&
                            $existing_item['link'] === $product_data['link']) {
                            $is_duplicate = true;
                            // eBay debug removed
                            break;
                        }
                    }
                    
                    if (!$is_duplicate) {
                        $raw_items_for_cache[] = $product_data;
                        // eBay debug removed
                    }
                }
                
                // No limit on items processed - get all available results
                
            } catch (Exception $e) {
                // ps_log_error("Error processing eBay item " . ($index + 1) . ": " . $e->getMessage());
                continue;
            }
        }
        
        // ps_log_error("Successfully parsed " . count($raw_items_for_cache) . " eBay products");
        
        return array(
            'success' => true,
            'items' => $raw_items_for_cache, // For display
            'count' => count($raw_items_for_cache),
            'raw_items_for_cache' => $raw_items_for_cache, // For caching
            'raw_items_count_for_cache' => count($raw_items_for_cache)
        );
        
    } catch (Exception $e) {
        // ps_log_error("Exception in eBay parsing: " . $e->getMessage());
        return array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => 'Error parsing eBay results: ' . $e->getMessage()
        );
    }
}

/**
 * Extract product data from a single eBay item element
 */
function ps_extract_ebay_product_data($item, $xpath, $country = 'us') {
    $product = array();
    
    try {
        // Get title
        $title_nodes = $xpath->query('.//h3//a | .//h3//span | .//div[contains(@class, "s-item__title")]//span', $item);
        $title = '';
        if ($title_nodes->length > 0) {
            $title = trim($title_nodes->item(0)->textContent);
            // Remove "New listing" and other prefixes
            $title = preg_replace('/^(New listing:?\s*|SPONSORED\s*)/i', '', $title);
        }
        
        if (empty($title) || $title === 'Shop on eBay') {
            return null; // Skip invalid items
        }
        
        $product['title'] = $title;
        $product['platform'] = 'ebay';
        
        // Get link
        $link_nodes = $xpath->query('.//h3//a | .//a[contains(@class, "s-item__link")]', $item);
        if ($link_nodes->length > 0) {
            $link = $link_nodes->item(0)->getAttribute('href');
            // Clean up eBay link
            if (strpos($link, 'ebay.') !== false) {
                $product['link'] = $link;
            }
        }
        
        // Get image - improved selectors based on actual eBay HTML structure
        $img_selectors = array(
            './/div[contains(@class, "s-item__image-wrapper")]//img',
            './/img[contains(@class, "s-item__image")]',
            './/div[contains(@class, "image-treatment")]//img',
            './/img[@alt and @src]'
        );
        
        $img_src = '';
        foreach ($img_selectors as $img_selector) {
            $img_nodes = $xpath->query($img_selector, $item);
            if ($img_nodes->length > 0) {
                $img_element = $img_nodes->item(0);
                if ($img_element instanceof DOMElement) {
                    $img_src = $img_element->getAttribute('src');
                    if (empty($img_src)) {
                        $img_src = $img_element->getAttribute('data-src');
                    }
                    if (!empty($img_src) && strpos($img_src, 'ebayimg.com') !== false) {
                        $product['image'] = $img_src;
                        // eBay debug removed
                        break;
                    }
                }
            }
        }
        
        // Get price
        $price_nodes = $xpath->query('.//span[contains(@class, "s-item__price")] | .//span[contains(@class, "notranslate")]', $item);
        if ($price_nodes->length > 0) {
            $price_text = trim($price_nodes->item(0)->textContent);
            $product['price'] = $price_text;
            
            // Extract numeric price for sorting
            if (preg_match('/[\d,]+\.?\d*/', $price_text, $matches)) {
                $numeric_price = floatval(str_replace(',', '', $matches[0]));
                $product['price_numeric'] = $numeric_price;
            }
        }
        
        // Get condition
        $condition_nodes = $xpath->query('.//span[contains(@class, "SECONDARY_INFO")] | .//span[contains(text(), "New")] | .//span[contains(text(), "Used")]', $item);
        if ($condition_nodes->length > 0) {
            $condition = trim($condition_nodes->item(0)->textContent);
            if (!empty($condition)) {
                $product['condition'] = $condition;
            }
        }
        
        // Enhanced shipping cost extraction
        $shipping_cost = 0;
        $shipping_text = '';
        $shipping_nodes = $xpath->query('.//span[contains(@class, "s-item__shipping")] | .//span[contains(text(), "shipping")] | .//span[contains(text(), "postage")] | .//div[contains(@class, "s-item__shipping")]', $item);
        
        if ($shipping_nodes->length > 0) {
            $shipping_text = trim($shipping_nodes->item(0)->textContent);
            $product['shipping'] = $shipping_text;
            
            // Extract numeric shipping cost
            if (preg_match('/[\d,]+\.?\d*/', $shipping_text, $shipping_matches)) {
                $shipping_cost = floatval(str_replace(',', '', $shipping_matches[0]));
            }
            
            // Handle "Free shipping" or similar
            if (preg_match('/free\s*shipping/i', $shipping_text)) {
                $shipping_cost = 0;
                $product['shipping'] = 'Free shipping';
            }
        } else {
            // Check for free shipping in other locations
            $free_shipping_nodes = $xpath->query('.//span[contains(text(), "Free shipping")] | .//span[contains(text(), "FREE SHIPPING")]', $item);
            if ($free_shipping_nodes->length > 0) {
                $shipping_cost = 0;
                $product['shipping'] = 'Free shipping';
            }
        }
        
        // Calculate total price (base price + shipping) for accurate sorting
        $base_price = isset($product['price_numeric']) ? $product['price_numeric'] : 0;
        $total_price = $base_price + $shipping_cost;
        $product['price_total'] = $total_price;
        $product['shipping_cost'] = $shipping_cost;
        
        // Use total price for sorting if shipping cost is available
        if ($shipping_cost > 0) {
            $product['price_numeric'] = $total_price;
            $product['price_value'] = $total_price; // Amazon compatibility
                    // Update display price to show total with breakdown format: [total] ([price] + [ship] shipping)
        if (!empty($product['price']) && $shipping_cost > 0) {
            $currency_symbol = '$'; // Default
            if (preg_match('/^([^0-9.,]+)/', $product['price'], $symbol_match)) {
                $currency_symbol = $symbol_match[1];
            }
            // Clean up Canadian currency symbol from "C $" to just "$" for both main price and shipping breakdown
            $cleaned_currency_symbol = ($currency_symbol === 'C $') ? '$' : $currency_symbol;
            $shipping_currency_symbol = ($currency_symbol === 'C $') ? '$' : $currency_symbol;
            
            // Clean up the original price for the breakdown by removing "C $" prefix
            $original_price_cleaned = $product['price'];
            if (strpos($original_price_cleaned, 'C $') === 0) {
                $original_price_cleaned = '$' . substr($original_price_cleaned, 3);
            }
            
            $product['price'] = $cleaned_currency_symbol . number_format($total_price, 2) . ' <span class="ps-ebay-price-breakdown">(' . $original_price_cleaned . ' + ' . $shipping_currency_symbol . number_format($shipping_cost, 2) . ' shipping)</span>';
        }
        } else {
            // Ensure we still have price_value for compatibility even without shipping
            $product['price_value'] = $base_price;
            
            // Clean up Canadian currency symbol from main price even when no shipping
            if (!empty($product['price']) && strpos($product['price'], 'C $') === 0) {
                $product['price'] = '$' . substr($product['price'], 3);
            }
        }
        
        // Comprehensive seller info extraction with rating conversion
        $seller_name = '';
        $seller_feedback_count = '';
        $seller_rating_percentage = null;
        $seller_rating_numeric = null;
        
        // First, search the entire item for any text containing a percentage
        $percentage_nodes = $xpath->query('.//*[contains(text(), "%")]', $item);
        foreach ($percentage_nodes as $node) {
            $text_content = trim($node->textContent);
            
            // Try to extract percentage from this text
            if (preg_match('/(\d+(?:\.\d+)?)%/', $text_content, $percentage_matches)) {
                $found_percentage = floatval($percentage_matches[1]);
                
                // Only accept percentages that look like seller ratings (85-100%)
                if ($found_percentage >= 85 && $found_percentage <= 100) {
                    $seller_rating_percentage = $found_percentage;
                                            // eBay debug removed
                    
                    // Try to extract seller name and feedback count from the same text
                    // Format: "sellername (123,456) 98.5%"
                    if (preg_match('/([a-zA-Z0-9_-]+)\s*\(([0-9,]+)\)\s*\d+(?:\.\d+)?%/', $text_content, $matches)) {
                        $seller_name = $matches[1];
                        $seller_feedback_count = $matches[2];
                                                    // eBay debug removed
                        break;
                    }
                    
                    // Format: "From sellername (12,345) 98.5% positive"
                    if (preg_match('/(?:From\s+)?([a-zA-Z0-9_-]+)\s*\(([0-9,]+)\)\s*\d+(?:\.\d+)?%/', $text_content, $matches)) {
                        $seller_name = $matches[1];
                        $seller_feedback_count = $matches[2];
                                                    // eBay debug removed
                        break;
                    }
                    
                    // Try to find seller name anywhere in the item if we have percentage
                    if (empty($seller_name)) {
                        $seller_search_selectors = array(
                            './/a[contains(@href, "/usr/")]',
                            './/a[contains(@href, "/str/")]',
                            './/span[contains(@class, "seller")]',
                            './/div[contains(@class, "seller")]'
                        );
                        
                        foreach ($seller_search_selectors as $seller_selector) {
                            $seller_link_nodes = $xpath->query($seller_selector, $item);
                            if ($seller_link_nodes->length > 0) {
                                $seller_link_text = trim($seller_link_nodes->item(0)->textContent);
                                $seller_href = $seller_link_nodes->item(0)->getAttribute('href');
                                
                                // Extract seller name from link or text
                                if (!empty($seller_href) && preg_match('/\/usr\/([^\/\?]+)/', $seller_href, $href_matches)) {
                                    $seller_name = urldecode($href_matches[1]);
                                    break;
                                } elseif (!empty($seller_link_text) && preg_match('/([a-zA-Z0-9_-]+)/', $seller_link_text, $text_matches)) {
                                    $seller_name = $text_matches[1];
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Try to find feedback count in surrounding text if not found yet
                    if (empty($seller_feedback_count)) {
                        $feedback_nodes = $xpath->query('.//*[contains(text(), "(") and contains(text(), ")")]', $item);
                        foreach ($feedback_nodes as $feedback_node) {
                            $feedback_text = trim($feedback_node->textContent);
                            if (preg_match('/\(([0-9,]+)\)/', $feedback_text, $count_matches)) {
                                $feedback_number = str_replace(',', '', $count_matches[1]);
                                // Only accept reasonable feedback counts (100+)
                                if (is_numeric($feedback_number) && intval($feedback_number) >= 100) {
                                    $seller_feedback_count = $count_matches[1];
                                    break;
                                }
                            }
                        }
                    }
                    
                    break; // We found a valid percentage, stop looking
                }
            }
        }
        
        // If we didn't find the full seller info in one place, try to piece it together
        if (empty($seller_rating_percentage)) {
            // Look for percentage in various locations
            $rating_selectors = array(
                './/span[contains(text(), "%")] | .//div[contains(text(), "%")]',
                './/span[contains(@class, "seller")] | .//div[contains(@class, "seller")]',
                './/span[contains(@class, "feedback")] | .//div[contains(@class, "feedback")]',
                './/text()[contains(., "%")]'
            );
            
            foreach ($rating_selectors as $selector) {
                $rating_nodes = $xpath->query($selector, $item);
                if ($rating_nodes->length > 0) {
                    for ($i = 0; $i < $rating_nodes->length; $i++) {
                        $rating_text = trim($rating_nodes->item($i)->textContent);
                        
                        // Extract percentage and possibly feedback count
                        if (preg_match('/(\d+(?:\.\d+)?)%/', $rating_text, $matches)) {
                            $seller_rating_percentage = floatval($matches[1]);
                            
                            // Try to extract feedback count from same text
                            if (empty($seller_feedback_count) && preg_match('/\(([0-9,]+)\)/', $rating_text, $count_matches)) {
                                $seller_feedback_count = $count_matches[1];
                            }
                            break 2;
                        }
                    }
                }
            }
        }
        
        // Build comprehensive seller display and convert rating to stars
        if ($seller_rating_percentage !== null) {
            $product['seller_rating_percentage'] = $seller_rating_percentage;
            
            // Convert percentage to stars: 85% = 3.5, 90% = 4.0, 95% = 4.5
            if ($seller_rating_percentage >= 95.0) {
                $seller_rating_numeric = 4.5;
            } elseif ($seller_rating_percentage >= 90.0) {
                $seller_rating_numeric = 4.0;
            } elseif ($seller_rating_percentage >= 85.0) {
                $seller_rating_numeric = 3.5;
            } else {
                $seller_rating_numeric = 3.0; // Below 85%
            }
            
            $product['rating_numeric'] = $seller_rating_numeric;
            $product['rating_number'] = $seller_rating_numeric; // For JavaScript filtering compatibility
            $product['is_ebay_seller_rating'] = true; // Flag to indicate this is an eBay seller rating
            $product['seller_rating_stars'] = $seller_rating_numeric;
            
            // Create eBay-style seller display: "sellername (feedback_count) percentage%"
            $seller_display = '';
            if (!empty($seller_name)) {
                $seller_display = $seller_name;
                if (!empty($seller_feedback_count)) {
                    $seller_display .= ' (' . $seller_feedback_count . ')';
                }
                $seller_display .= ' ' . $seller_rating_percentage . '%';
            } else {
                // Fallback: Create a generic seller display with percentage
                $seller_display = 'eBay Seller ' . $seller_rating_percentage . '%';
            }
            
            // For eBay, only set the rating field to avoid duplication in template
            $product['rating'] = $seller_display;
            // Set rating_link to the product link for eBay products
            $product['rating_link'] = isset($product['link']) ? $product['link'] : '#';
            
            // Debug logging for seller rating (found rating case)
            // ps_log_error("eBay Seller Rating Debug - Found rating: seller_name='{$seller_name}', feedback_count='{$seller_feedback_count}', percentage={$seller_rating_percentage}%, stars={$seller_rating_numeric}, display='{$seller_display}'");
            // ps_log_error("eBay Seller Rating Debug - Product fields set: rating='{$product['rating']}', rating_link='{$product['rating_link']}', seller=" . (isset($product['seller']) ? "'{$product['seller']}'" : 'NOT_SET'));
            // ps_log_error("eBay FINAL RATING SET: '{$product['rating']}' for product: '{$product['title']}'");
        } else {
            // Default rating if none found - set to 4.0 (90%+ equivalent) to pass 4.0+ filter
            $seller_rating_numeric = 4.0;
            $product['rating_numeric'] = $seller_rating_numeric;
            $product['rating_number'] = $seller_rating_numeric; // For JavaScript filtering compatibility
            $product['is_ebay_seller_rating'] = true; // Flag to indicate this is an eBay seller rating
            $product['seller_rating_stars'] = $seller_rating_numeric;
            
            // Default seller display
            if (!empty($seller_name)) {
                $seller_display = $seller_name;
                if (!empty($seller_feedback_count)) {
                    $seller_display .= ' (' . $seller_feedback_count . ')';
                }
                $seller_display .= ' 90%'; // Default to 90% (4.0 stars)
            } else {
                $seller_display = 'Seller: 90%';
            }
            
            // For eBay, only set the rating field to avoid duplication in template
            $product['rating'] = $seller_display;
            // Set rating_link to the product link for eBay products
            $product['rating_link'] = isset($product['link']) ? $product['link'] : '#';
            
            // Debug logging for seller rating (default case)
            // ps_log_error("eBay Seller Rating Debug - Default rating: seller_name='{$seller_name}', feedback_count='{$seller_feedback_count}', stars={$seller_rating_numeric}, display='{$seller_display}'");
            // ps_log_error("eBay Seller Rating Debug - Product fields set (default): rating='{$product['rating']}', rating_link='{$product['rating_link']}', seller=" . (isset($product['seller']) ? "'{$product['seller']}'" : 'NOT_SET'));
            // ps_log_error("eBay FINAL RATING SET (default): '{$product['rating']}' for product: '{$product['title']}'");
        }
        
        // Add unit price fields for JavaScript processing (initially empty - will be calculated from title)
        $product['price_per_unit'] = '';
        $product['price_per_unit_value'] = 0; // Start with 0 - will be set by JavaScript when unit price is calculated
        $product['unit'] = '';
        
        // Default image if none found - use a data URI instead of external placeholder
        if (empty($product['image'])) {
            // Use a simple gray placeholder as data URI to avoid external requests
            $product['image'] = 'data:image/svg+xml;base64,' . base64_encode('<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="200" height="200" fill="#f0f0f0"/><text x="100" y="100" text-anchor="middle" dy=".3em" font-family="Arial" font-size="14" fill="#999">No Image</text></svg>');
        }
        
        // Default link if none found
        if (empty($product['link'])) {
            $ebay_base = ($country === 'ca') ? 'https://www.ebay.ca' : 'https://www.ebay.com';
            $product['link'] = $ebay_base;
        }
        
        // Final debug logging before returning product
        // ps_log_error("eBay PRODUCT FINAL BEFORE RETURN: title='{$product['title']}', rating='{$product['rating']}', is_ebay_seller_rating=" . (isset($product['is_ebay_seller_rating']) ? 'true' : 'false'));
        
        return $product;
        
    } catch (Exception $e) {
        // ps_log_error("Error extracting eBay product data: " . $e->getMessage());
        return null;
    }
}

/**
 * Construct eBay search URL
 */
function ps_construct_ebay_search_url($query, $country = 'us', $page = 1) {
    $base_url = ($country === 'ca') ? 'https://www.ebay.ca' : 'https://www.ebay.com';
    
    $params = array(
        '_nkw' => urlencode($query),
        '_sacat' => '0', // All categories
        '_sop' => '15', // Sort by price + shipping (lowest first)
        '_pgn' => $page
    );
    
    $url = $base_url . '/sch/i.html?' . http_build_query($params);
    
    return $url;
}

/**
 * Fetch eBay search results using same proxy/networking as Amazon
 */
function ps_fetch_ebay_search_results($url, $country = 'us') {
    // Use similar networking approach as Amazon
    $user_agents = array(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0'
    );
    
    $random_user_agent = $user_agents[array_rand($user_agents)];
    
    $headers = array(
        'User-Agent: ' . $random_user_agent,
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Accept-Encoding: gzip, deflate, br',
        'DNT: 1',
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
    );
    
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_ENCODING => 'gzip,deflate',
        CURLOPT_COOKIEJAR => '',
        CURLOPT_COOKIEFILE => ''
    ));
    
    // Use proxy if available (similar to Amazon implementation)
    if (ps_is_on_current_network()) {
        $proxy_config = ps_get_proxy_config();
        if ($proxy_config) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_config['host'] . ':' . $proxy_config['port']);
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_config['username'] . ':' . $proxy_config['password']);
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        }
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        // ps_log_error("eBay cURL error: " . $error);
        return array('error' => true, 'http_code' => 0, 'message' => $error);
    }
    
    if ($http_code !== 200) {
        // ps_log_error("eBay HTTP error: " . $http_code);
        return array('error' => true, 'http_code' => $http_code);
    }
    
    return $response;
}

/**
 * Get proxy configuration (reuse from Amazon implementation)
 */
function ps_get_proxy_config() {
    if (defined('PS_DECODO_PROXY_HOST') && defined('PS_DECODO_PROXY_PORT') && 
        defined('PS_DECODO_USER_BASE') && defined('PS_DECODO_PASSWORD')) {
        
        return array(
            'host' => PS_DECODO_PROXY_HOST,
            'port' => PS_DECODO_PROXY_PORT,
            'username' => PS_DECODO_USER_BASE,
            'password' => PS_DECODO_PASSWORD
        );
    }
    
    return null;
} 
