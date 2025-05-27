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
 * @param string $query The main search query
 * @param string $exclude_keywords Keywords to exclude from results
 * @param string $sort_by Sorting method (price, price_per_unit)
 * @param string $country Country code ('us' or 'ca')
 * @param float $min_rating Minimum rating filter (3.5, 4.0, 4.5)
 * @return array Search results
 */
function ps_search_amazon_products($query, $exclude_keywords = '', $sort_by = 'price', $country = 'us', $min_rating = 4.0) {
    // If query is empty, return empty array
    if (empty($query)) {
        return array();
    }
    
    ps_log_error("Initiating live search for: '{$query}' in country: {$country}");
    
    // Construct the Amazon search URL
    $search_url = ps_construct_amazon_search_url($query, $country);
    ps_log_error("Constructed search URL: {$search_url}");
    
    // Get the search results HTML
    $html_content = ps_fetch_amazon_search_results($search_url, $country);
    
    if (empty($html_content)) {
        ps_log_error("Failed to fetch Amazon search results for query: '{$query}' - No response received");
        return array();
    }
    
    // Check if Amazon is blocking the request
    if (ps_is_amazon_blocking($html_content)) {
        ps_log_error("Amazon is blocking search for query: '{$query}' - Blocking page detected");
        return array();
    }
    
    // Check if it's a valid search page
    if (!ps_is_valid_search_page($html_content)) {
        ps_log_error("Invalid Amazon search results format: " . substr($html_content, 0, 100));
        return array();
    }
    
    // Get the associate tag
    $associate_tag = ps_get_associate_tag($country);
    
    // Parse the search results HTML
    $products = ps_parse_amazon_results($html_content, $associate_tag, $min_rating);
    
    return $products;
}

/**
 * Try alternative parsing methods for Amazon results
 * This helps handle changes in Amazon's HTML structure
 */
function ps_try_alternative_parsing($html, $affiliate_id, $min_rating = 4.0) {
    ps_log_error("Attempting alternative parsing methods");
    
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
            )
        );
        
        // Try each selector set
        foreach ($selector_sets as $index => $selectors) {
            ps_log_error("Trying alternative selector set #" . ($index + 1));
            
            $product_nodes = $xpath->query($selectors['product']);
            
            if ($product_nodes && $product_nodes->length > 0) {
                ps_log_error("Found " . $product_nodes->length . " potential product nodes with selector set #" . ($index + 1));
                
                foreach ($product_nodes as $node) {
                    $title_nodes = $xpath->query($selectors['title'], $node);
                    $link_nodes = $xpath->query($selectors['link'], $node);
                    $price_nodes = $xpath->query($selectors['price'], $node);
                    $image_nodes = $xpath->query($selectors['image'], $node);
                    
                    if ($title_nodes->length > 0 && $link_nodes->length > 0) {
                        $title = trim($title_nodes->item(0)->textContent);
                        $link = trim($link_nodes->item(0)->nodeValue);
                        
                        // Skip sponsored products or other non-product items
                        if (empty($title) || stripos($title, "sponsored") !== false) {
                            continue;
                        }
                        
                        // Process link - ensure it's absolute & add affiliate tag
                        if (strpos($link, 'http') !== 0) {
                            $link = 'https://www.amazon.com' . $link;
                        }
                        
                        // Add affiliate tag if not already present
                        if (strpos($link, 'tag=') === false) {
                            $link .= (strpos($link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                        }
                        
                        $price = '';
                        $price_value = 0;
                        
                        if ($price_nodes->length > 0) {
                            $price = trim($price_nodes->item(0)->nodeValue);
                            $price_value = (float) preg_replace('/[^0-9.]/', '', $price);
                        }
                        
                        $image = '';
                        if ($image_nodes->length > 0) {
                            $image = trim($image_nodes->item(0)->nodeValue);
                        }
                        
                        // Extract unit price (e.g., $379.02/100 ml)
                        $unit_price = '';
                        $unit = '';
                        $unitPriceNode = $xpath->query('.//span[contains(@class, "a-price a-text-price")]/span[@class="a-offscreen"]', $node)->item(0);
                        $unitTextNode = $xpath->query('.//span[contains(@class, "a-size-base a-color-secondary")]', $node)->item(0);
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
                        
                        // Calculate unit price value for sorting
                        $unit_price_value = $price_value; // Default to regular price
                        if (!empty($unit_price)) {
                            $unit_price_numeric = (float) preg_replace('/[^0-9.]/', '', $unit_price);
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
                    ps_log_error("Alternative parsing method successfully extracted " . count($products) . " products with selector set #" . ($index + 1));
                    break; // Stop trying other selectors if we found products
                }
            }
        }
    } catch (Exception $e) {
        ps_log_error("Alternative parsing failed with error: " . $e->getMessage());
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
            ps_log_error("ps_is_amazon_blocking: Detected blocking indicator: '{$indicator}'");
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
 * Parse Amazon search results HTML
 *
 * @param string $html The HTML content
 * @param string $affiliate_id The Amazon affiliate ID
 * @return array The parsed products
 */
function ps_parse_amazon_results($html, $affiliate_id, $min_rating = 4.0) {
    $products = array();
    $raw_items_for_cache = array(); // For storing all successfully parsed items before display filtering
    $raw_items_count_for_cache = 0;

    ps_log_error("Starting to parse Amazon results.");

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
            ps_log_error("XPath: DOMDocument encountered {$error_count} XML parsing errors (showing first 3)");
            
            // Log first 3 errors only to avoid flooding the log
            for ($i = 0; $i < min(3, $error_count); $i++) {
                ps_log_error("XPath XML Error " . ($i + 1) . ": " . trim($errors[$i]->message));
            }
            // Clear the errors
            libxml_clear_errors();
        }
        
        // Restore previous error state
        libxml_use_internal_errors($previous_error_state);
        
        $xpath = new DOMXPath($dom);
        
        // Use the div[@role="listitem"] selector
        $productElements = $xpath->query('//div[@role="listitem"]');
        
        if ($productElements && $productElements->length > 0) {
            ps_log_error("XPath: Found " . $productElements->length . " product elements using div[@role=\"listitem\"] selector");
            $debug_extraction_data = []; // For detailed logging of extraction attempts

            foreach ($productElements as $idx => $element) {
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
                    $aria_label = $h2WithAriaLabel->getAttribute('aria-label');
                    $current_product_debug['title_h2_aria_label'] = $aria_label;
                    $spanInsideH2 = $xpath->query('.//span', $h2WithAriaLabel)->item(0);
                    if ($spanInsideH2) {
                        $title = trim(preg_replace('/\s+/', ' ', $spanInsideH2->textContent));
                        $title_extraction_method = 'h2_aria_label_span';
                        $current_product_debug['title_h2_span_content'] = $title;
                    } else {
                        $title = trim(preg_replace('/\s+/', ' ', $h2WithAriaLabel->textContent));
                        $title_extraction_method = 'h2_aria_label_text';
                        $current_product_debug['title_h2_text_content'] = $title;
                    }
                }
                
                // Method 2: Standard a-text-normal selector
                if (empty($title)) {
                    $titleNode = $xpath->query('.//span[contains(@class, "a-text-normal")]', $element)->item(0);
                    if ($titleNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
                        $title_extraction_method = 'a_text_normal';
                        $current_product_debug['title_a_text_normal'] = $title;
                    }
                }
                
                // Method 3: h2 span fallback
                if (empty($title)) {
                    $titleNode = $xpath->query('.//h2//span', $element)->item(0);
                    if ($titleNode) {
                        $title = trim(preg_replace('/\s+/', ' ', $titleNode->textContent));
                        $title_extraction_method = 'h2_span';
                        $current_product_debug['title_h2_span'] = $title;
                    }
                }
                
                // Method 4: h2 fallback
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
                            if (empty($bestTitle) || strlen($nodeTitle) > strlen($bestTitle)) {
                                $bestTitle = $nodeTitle;
                            }
                        }
                        if (!empty($bestTitle)) {
                            $title = $bestTitle;
                            $title_extraction_method = 'a_size_base_plus';
                            $current_product_debug['title_a_size_base_plus'] = $title;
                        }
                    }
                }
                
                // Method 7: a-color-base
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
                        $base_amazon_url = (strpos($affiliate_id, '-20') !== false) ? 'https://www.amazon.com' : 'https://www.amazon.ca'; // basic country detection
                        $link = rtrim($base_amazon_url, '/') . $link;
                    }
                    if (strpos($link, 'tag=') === false && !empty($affiliate_id)) {
                        $link .= (strpos($link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                    }
                }
                $current_product_debug['link'] = $link;
                
                // Skip if title or link is empty or title contains sponsored text
                if (empty($title) || empty($link) || stripos($title, 'sponsored') !== false || stripos($title, 'learn more') !== false || stripos($title, 'let us know') !== false ) {
                    ps_log_error("Skipping product " . ($idx + 1) . ": Empty title/link or sponsored. Title: '{$title}', Link: '{$link}'");
                    $debug_extraction_data[$idx] = $current_product_debug; // Log debug even for skipped
                    continue;
                }

                // --- Extract Price ---
                    $priceNode = $xpath->query('.//span[@class="a-price"]/span[@class="a-offscreen"]', $element)->item(0) ??
                             $xpath->query('.//span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]', $element)->item(0);
                    if ($priceNode) {
                    $price_str = trim($priceNode->textContent);
                    $price_value = (float) preg_replace('/[^0-9.,]/', '', str_replace(',', '.', $price_str)); // Handle comma as decimal
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

                // --- Extract unit price (e.g., $379.02/100 ml) ---
                $unit_price = '';
                $unit = '';
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
                    $current_product_debug['unit_price'] = $unit_price;
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
                $ratingCountNode = $xpath->query('.//span[contains(@aria-label, "ratings")]/a/span', $element)->item(0) ??
                                   $xpath->query('.//a[contains(@href, "#customerReviews")]//span[contains(@class, "a-size-base")]', $element)->item(0) ??
                                   $xpath->query('.//span[@class="a-size-base s-underline-text"]', $element)->item(0); 
                    if ($ratingCountNode) {
                    $rating_count_str = trim(preg_replace('/[^0-9]/', '', $ratingCountNode->textContent));
                    }
                $current_product_debug['rating_count_str'] = $rating_count_str;
                    
                // --- Rating Link ---
                    if ($rating_number > 0) {
                    $ratingLinkNode = $xpath->query('.//a[contains(@href, "customerReviews")]', $element)->item(0);
                    if ($ratingLinkNode) {
                        $rating_link = $ratingLinkNode->getAttribute('href');
                            if (strpos($rating_link, 'http') !== 0) {
                             $base_amazon_url = (strpos($affiliate_id, '-20') !== false) ? 'https://www.amazon.com' : 'https://www.amazon.ca';
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
                    
                    // Update price_per_unit_value if we have a unit price
                    if (!empty($unit_price)) {
                        // Extract numeric value from unit price (e.g., "$3.99/100ml" -> 3.99)
                        $unit_price_numeric = (float) preg_replace('/[^0-9.]/', '', $unit_price);
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
                } else {
                    ps_log_error("Product " . ($idx + 1) . " missing essential data. Title: '$title', Link: '$link', Price: $price_value, Image: '$image'");
                }
            }
            
            // Log detailed extraction attempts if debugging is enabled (e.g. via a constant or setting)
            if (defined('PS_DEBUG_PARSING') && PS_DEBUG_PARSING) {
                 foreach($debug_extraction_data as $prod_idx => $debug_data) {
                     ps_log_error("Debug Extraction for Product #" . ($prod_idx +1) . ": " . json_encode($debug_data));
                }
            }
            
            if (!empty($products)) {
                ps_log_error("Successfully extracted " . count($products) . " products using XPath parsing (div[@role=\"listitem\"]).");
            } else {
                 ps_log_error("XPath (div[@role=\"listitem\"]): Found " . $productElements->length . " elements, but extracted 0 valid products after field extraction.");
            }
        } else {
            ps_log_error("XPath: No product elements found with div[@role=\"listitem\"] selector.");
        }
        
    } catch (Exception $e) {
        ps_log_error("XPath parsing failed with error: " . $e->getMessage());
    }

    $raw_items_count_for_cache = count($raw_items_for_cache);

    // Return a structured array containing both display products and raw products for caching
    return array(
        'success' => !empty($products), // Overall success if any products were displayable
        'items'   => $products,          // Products for display (potentially filtered later)
        'count'   => count($products),    // Count of displayable products
        'raw_items_for_cache' => $raw_items_for_cache, // All items successfully parsed, for base caching
        'raw_items_count_for_cache' => $raw_items_count_for_cache, // Count of raw items
        'message' => empty($products) ? 'No products found or extracted successfully.' : ''
    );
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
    // Get the current date and time
    $date = date('Y-m-d H:i:s');
    
    // Format the log message
    $log_message = "[{$date}] {$message}" . PHP_EOL;
    
    // Log directory
    $log_dir = PS_PLUGIN_DIR . 'logs';
    
    // Create log directory if it doesn't exist
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Log file path
    $log_file = $log_dir . '/error_log.txt';
    
    // Write to log file
    file_put_contents($log_file, $log_message, FILE_APPEND);
}

/**
 * Save a sample of the response for debugging
 *
 * @param string $html The HTML content
 */
function ps_save_response_sample($html) {
    // Save the full HTML response
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
    
    // Save the full response
    $file = $logs_dir . '/amazon_response_' . date('Y-m-d_H-i-s') . '.html';
    file_put_contents($file, $html);
    ps_log_error("Saved full HTML response to " . basename($file));
    
    // Keep only the 5 most recent samples
    $files = glob($logs_dir . '/amazon_response_*.html');
    if (count($files) > 5) {
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $files_to_delete = array_slice($files, 0, count($files) - 5);
        foreach ($files_to_delete as $file) {
            unlink($file);
        }
    }
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
function ps_construct_amazon_search_url($query, $country = 'us') {
    $base_url = $country === 'ca' ? 'https://www.amazon.ca' : 'https://www.amazon.com';
    return $base_url . '/s?k=' . urlencode($query);
}

/**
 * Fetch Amazon search results
 *
 * @param string $url The Amazon search URL
 * @param string $country Country code ('us' or 'ca')
 * @return string|false The HTML content or false on failure
 */
function ps_fetch_amazon_search_results($url, $country = 'us') {
    ps_log_error("Fetching search results from URL: {$url}");
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Set user agent to mimic a browser
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36');
    
    // Add headers to make the request look more like a browser
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
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
        return false;
    }
    
    // Save the response for debugging
    ps_save_response_sample($html_content);
    
    return $html_content;
}