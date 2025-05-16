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
 * @return array Search results
 */
function ps_search_amazon_products($query, $exclude_keywords = '', $sort_by = 'price') {
    // Get settings
    $settings = get_option('ps_settings');
    $associate_tag = isset($settings['amazon_associate_tag']) ? $settings['amazon_associate_tag'] : PS_AFFILIATE_ID;
    
    // Use the latest saved Amazon response file
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    $response_files = glob($logs_dir . '/amazon_response_*.html');
    
    if (empty($response_files)) {
        // No saved response files found
        ps_log_error("No Amazon response files found in the logs directory. Please run a manual test first to generate response files.");
        return array(
            'success' => false,
            'message' => 'No saved Amazon response files available. Admin needs to generate test data first.',
            'items' => array(),
            'count' => 0,
            'error_type' => 'no_response_files'
        );
    }
    
    // Sort files by modification time (newest first)
    usort($response_files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $latest_file = $response_files[0];

    // Log which file is being processed
    ps_log_error("Attempting to process Amazon response file: " . basename($latest_file));

    $file_age = time() - filemtime($latest_file);
    $file_age_minutes = round($file_age / 60);
    
    ps_log_error("Using saved Amazon response file: " . basename($latest_file) . " (age: " . $file_age_minutes . " minutes)");
    $response = file_get_contents($latest_file);
    
    if ($response === false) {
        ps_log_error("Failed to read response file: " . basename($latest_file));
        return array(
            'success' => false,
            'message' => 'Error reading saved Amazon response file.',
            'items' => array(),
            'count' => 0,
            'error_type' => 'file_read_error'
        );
    }
    
    ps_log_error("Successfully loaded saved response file with " . strlen($response) . " bytes");
    
    // Check if Amazon is blocking the request, but don't act immediately
    $is_response_flagged_as_blocked = ps_is_amazon_blocking($response);
    if ($is_response_flagged_as_blocked) {
        ps_log_error("Warning: Response content triggered blocking indicators. Will proceed with parsing attempt.");
    }
    
    // Enhanced parsing with better error handling
    $products = ps_parse_amazon_results($response, $associate_tag);
    
    // If no products found, but the page appears to be a valid search page,
    // try alternative parsing methods or selectors
    if (empty($products) && ps_is_valid_search_page($response)) {
        ps_log_error("Primary parsing method yielded no products, trying alternative XPath selectors");
        
        // Try alternative selectors (just an example of how we might handle this)
        $alternative_products = ps_try_alternative_parsing($response, $associate_tag);
        
        if (!empty($alternative_products)) {
            ps_log_error("Alternative parsing method found " . count($alternative_products) . " products");
            $products = $alternative_products;
        } else {
            ps_log_error("Both primary and alternative parsing methods failed to extract products");
        }
    }
    
    // If still no products found and it's not a valid search page structure
    if (empty($products) && !ps_is_valid_search_page($response)) {
        ps_log_error("Failed to parse Amazon results. HTML structure may have changed or is not a valid search page.");
        
        // Save a fresh copy of the problematic response for debugging
        $debug_filename = $logs_dir . '/parsing_error_' . date('Y-m-d_H-i-s') . '.html';
        file_put_contents($debug_filename, $response);
        ps_log_error("Saved problematic response to " . basename($debug_filename) . " for debugging");
        
        return array(
            'success' => false,
            'message' => 'Unable to parse search results. The Amazon page structure may have changed.',
            'items' => array(),
            'count' => 0,
            'error_type' => 'parsing_error',
            'debug_file' => basename($debug_filename)
        );
    }
    
    // If we have products but fewer than expected, log as a warning
    if (count($products) < 5 && strlen($response) > 50000) {
        ps_log_error("Warning: Only " . count($products) . " products extracted from a large response (" . strlen($response) . " bytes). Parsing may be incomplete.");
    }
    
    // Ensure all search words are in the results
    $search_words = array_filter(explode(' ', strtolower($query)), function($word) {
        return !empty($word); // Keep words of any length, only filter out empty strings
    });
    
    if (!empty($search_words) && !empty($products)) {
        $pre_filter_count = count($products);
        ps_log_error("Filtering with search words: " . implode(', ', $search_words) . " (wildcards enabled)");
        
        $filtered_products = array_filter($products, function($item) use ($search_words) {
            $title_lower = strtolower($item['title']);
            
            // Require ALL search words to be in the TITLE
            foreach ($search_words as $word) {
                // Check if the word contains a wildcard (*)
                if (strpos($word, '*') !== false) {
                    // Word has *, it's a wildcard search
                    $prefix = str_replace('*', '', $word); // Remove all * to get prefix
                    
                    // Skip if prefix is too short
                    if (strlen($prefix) < 2) {
                        continue;
                    }
                    
                    // Method 1: Check if any word in the title starts with the prefix
                    $found_match = false;
                    
                    // Split the title into words
                    $title_words = preg_split('/[\s\-\'",.:;!?()[\]{}|\\\/]+/', $title_lower);
                    foreach ($title_words as $title_word) {
                        if (strpos($title_word, $prefix) === 0) { // Word starts with prefix
                            $found_match = true;
                            break;
                        }
                    }
                    
                    // Method 2: Also check if title contains this prefix as a substring
                    // This helps with compound words, plurals, etc.
                    if (!$found_match) {
                        // Check for word boundary before the prefix
                        if (preg_match('/\b' . preg_quote($prefix, '/') . '/', $title_lower)) {
                            $found_match = true;
                        }
                    }
                    
                    // If no match found for this wildcard term, filter out the product
                    if (!$found_match) {
                        return false;
                    }
                } else {
                    // No wildcard, perform exact substring match
                    if (stripos($title_lower, $word) === false) {
                        return false; // If any search word is missing from title, filter out
                    }
                }
            }
            return true;
        });
        
        $post_filter_count = count($filtered_products);
        // Always apply filtering, even if it removes all products
        if ($post_filter_count > 0) {
            ps_log_error("Filtered {$pre_filter_count} products down to {$post_filter_count} products matching search terms (with wildcards support)");
        } else {
            ps_log_error("No products matched all search terms in title (with wildcards support). Returning empty results.");
        }
        $products = $filtered_products;
    }
    
    // Filter by exclude keywords
    if (!empty($exclude_keywords) && !empty($products)) {
        // Split by spaces instead of commas
        $exclude_terms = array_filter(preg_split('/\s+/', $exclude_keywords), function($term) {
            return !empty(trim($term));
        });
        $pre_count = count($products);
        
        $products = array_filter($products, function($item) use ($exclude_terms) {
            $title_lower = strtolower($item['title']);
            
            foreach ($exclude_terms as $term) {
                // Only check title for excluded terms, not description
                if (stripos($title_lower, trim($term)) !== false) {
                    return false;
                }
            }
            return true;
        });
        
        $post_count = count($products);
        if ($post_count < $pre_count) {
            ps_log_error("Excluded " . ($pre_count - $post_count) . " products containing excluded terms");
        }
    }
    
    // Add default price values if missing for sorting purposes
    foreach ($products as $key => $product) {
        if (!isset($product['price_value']) || empty($product['price_value'])) {
            // Default to a high price (999.99) so items without price appear last when sorting by price
            $products[$key]['price_value'] = 999.99;
            
            // If there's a text price but no numeric value, try to extract it
            if (isset($product['price']) && !empty($product['price'])) {
                $price_text = $product['price'];
                $price_numeric = preg_replace('/[^0-9.]/', '', $price_text);
                if (!empty($price_numeric)) {
                    $products[$key]['price_value'] = floatval($price_numeric);
                }
            }
        }
        
        // Only set default price_per_unit value if a unit was actually found
        if (!isset($product['unit']) || empty($product['unit']) || $product['unit'] === 'N/A' || $product['unit'] === 'unit') {
            // No real unit value found, remove any price per unit data to avoid showing it
            $products[$key]['price_per_unit_value'] = 0;
            $products[$key]['price_per_unit'] = '';
            $products[$key]['unit'] = '';
        } elseif (!isset($product['price_per_unit_value']) || empty($product['price_per_unit_value'])) {
            // Has a real unit but missing the price per unit value
            $products[$key]['price_per_unit_value'] = $products[$key]['price_value'];
            $products[$key]['price_per_unit'] = isset($product['price']) ? $product['price'] : '';
        }
    }
    
    // Sort results
    if ($sort_by === 'price' && !empty($products)) {
        usort($products, function($a, $b) {
            return $a['price_value'] - $b['price_value'];
        });
        ps_log_error("Sorted products by price");
    } elseif ($sort_by === 'price_per_unit' && !empty($products)) {
        usort($products, function($a, $b) {
            return $a['price_per_unit_value'] - $b['price_per_unit_value'];
        });
        ps_log_error("Sorted products by price per unit");
    }
    
    // Count the results
    $result_count = count($products);
    ps_log_error("Final result: {$result_count} products for query '{$query}' with sort '{$sort_by}'");
    
    // If no products found after all processing, return an informative error
    if ($result_count === 0) {
        if ($is_response_flagged_as_blocked) {
            ps_log_error("No products found AND response was flagged as potentially blocked. Returning 'amazon_blocking' error.");
            return array(
                'success' => false,
                'message' => 'Amazon is currently not allowing automated searches, or the page structure is unparsable as a block page was detected.',
                'items' => array(),
                'count' => 0,
                'error_type' => 'amazon_blocking_after_parse_fail',
                'query' => $query,
                'exclude' => $exclude_keywords
            );
        } else {
            $file_info = basename($latest_file);
            ps_log_error("No products found, and response was NOT flagged as blocked. Returning 'no_matching_products' error.");
            return array(
                'success' => false,
                'message' => 'No products found matching your search criteria. Try different keywords or fewer exclusions. (Source file: ' . $file_info . ')',
                'items' => array(),
                'count' => 0,
                'error_type' => 'no_matching_products',
                'query' => $query,
                'exclude' => $exclude_keywords
            );
        }
    }
    
    return array(
        'success' => true,
        'items' => array_values($products),
        'count' => $result_count,
        'data_source' => 'saved_file:' . basename($latest_file),
        'file_age_minutes' => $file_age_minutes
    );
}

/**
 * Try alternative parsing methods for Amazon results
 * This helps handle changes in Amazon's HTML structure
 */
function ps_try_alternative_parsing($html, $affiliate_id) {
    ps_log_error("Attempting alternative parsing methods");
    
    $products = array();
    
    // Alternative 1: Try JSON first - sometimes Amazon includes product data in JSON format
    $json_products = ps_try_extract_products_from_json_scripts($html, $affiliate_id);
    if (!empty($json_products)) {
        ps_log_error("Successfully extracted " . count($json_products) . " products from JSON data");
        return $json_products;
    }
    
    // Alternative 2: Try different XPath selectors
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    
    // Array of alternative selector sets to try
    $selector_sets = array(
        // Alternative selector set 1
        array(
            'product' => '//div[contains(@class, "s-result-item")]',
            'title' => './/h2//a/span',
            'link' => './/h2//a/@href',
            'price' => './/span[@class="a-price"]/span[@class="a-offscreen"]',
            'image' => './/img[contains(@class, "s-image")]/@src'
        ),
        // Alternative selector set 2
        array(
            'product' => '//div[contains(@data-component-type, "s-search-result")]',
            'title' => './/h2//a/span',
            'link' => './/h2//a/@href',
            'price' => './/span[@class="a-price"]//span[@class="a-offscreen"]',
            'image' => './/img[contains(@class, "s-image")]/@src'
        ),
        // Alternative selector set 3 (newer Amazon layout)
        array(
            'product' => '//div[contains(@class, "sg-col-inner")]//div[contains(@class, "s-include-content-margin")]',
            'title' => './/span[contains(@class, "a-size-medium")]',
            'link' => './/a[contains(@class, "a-link-normal")]/@href',
            'price' => './/span[contains(@class, "a-offscreen")]',
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
                        $price = trim($price_nodes->item(0)->textContent);
                        // Extract numeric value for sorting
                        $price_value = preg_replace('/[^0-9.]/', '', $price);
                        $price_value = !empty($price_value) ? floatval($price_value) : 0;
                    }
                    
                    $image = '';
                    if ($image_nodes->length > 0) {
                        $img_node = $image_nodes->item(0);
                        
                        // First try to get image from srcset attribute (preferred)
                        $srcset_attr = trim($img_node->getAttribute('srcset'));
                        if (!empty($srcset_attr)) {
                            // Parse the srcset attribute to get the first URL (1x version)
                            $srcset_parts = explode(',', $srcset_attr);
                            if (!empty($srcset_parts[0])) {
                                // Extract URL from "url 1x" format
                                $first_srcset = trim($srcset_parts[0]);
                                $url_parts = explode(' ', $first_srcset);
                                if (!empty($url_parts[0])) {
                                    $image = trim($url_parts[0]);
                                }
                            }
                        }
                        
                        // Fallback to nodeValue (src attribute) if srcset parsing failed
                        if (empty($image)) {
                            $image = trim($img_node->nodeValue);
                        }
                    }
                    
                    $products[] = array(
                        'title' => $title,
                        'link' => $link,
                        'price' => $price,
                        'price_value' => $price_value,
                        'image' => $image,
                        'price_per_unit' => '',
                        'price_per_unit_value' => $price_value, // Default to regular price
                        'unit' => '',
                        'description' => '', // May not have description in search results
                        'parsing_method' => 'alternative_set_' . ($index + 1)
                    );
                }
            }
            
            // If we found some products, stop trying other selectors
            if (count($products) > 0) {
                ps_log_error("Successfully extracted " . count($products) . " products using alternative selector set #" . ($index + 1));
                break;
            }
        }
    }
    
    return $products;
}

/**
 * Fetch Amazon search results page
 *
 * @param string $url The Amazon search URL
 * @return string|false The HTML content or false on failure
 */
function ps_fetch_amazon_page($url) {
    // Log the attempt - Updated to indicate direct connection instead of proxy
    ps_log_error("Attempting to connect to: {$url} DIRECTLY (proxy disabled for testing)");

    // Decodo Proxy Configuration - Kept for reference but not used
    // $decodo_proxy_host = defined('PS_DECODO_PROXY_HOST') ? PS_DECODO_PROXY_HOST : 'gate.decodo.com';
    // $decodo_proxy_port = defined('PS_DECODO_PROXY_PORT') ? PS_DECODO_PROXY_PORT : 10001;
    // $decodo_user = defined('PS_DECODO_USER') ? PS_DECODO_USER : 'sptlq8hpk0';
    // $decodo_psw = defined('PS_DECODO_PASSWORD') ? PS_DECODO_PASSWORD : 'c=mKkGh1o3lCd3Shm1';

    // User-Agent Rotation
    $user_agents = array(
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:89.0) Gecko/20100101 Firefox/89.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1'
    );
    $user_agent = $user_agents[array_rand($user_agents)];

    // Referrer Rotation
    $referrers = array(
        'https://www.google.com/',
        'https://www.bing.com/',
        'https://duckduckgo.com/',
        'https://www.amazon.com/' // Referrer from Amazon itself (e.g., homepage)
    );
    $referer = $referrers[array_rand($referrers)];

    $max_retries = 2; // Reduced from 3 for debugging

    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        $current_retry_delay = rand(1, 2); // Randomized delay between 1 and 2 seconds
        ps_log_error("Fetching {$url} directly. Attempt {$attempt}/{$max_retries}. User-Agent: {$user_agent}. Referer: {$referer}");

        $ch = curl_init();
        $response_headers = array(); // Array to store response headers

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased from 15 to 30 for direct connection
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_COOKIEFILE, ""); 

        // Capture response headers
        curl_setopt($ch, CURLOPT_HEADERFUNCTION,
          function($curl, $header) use (&$response_headers)
          {
            $len = strlen($header);
            $header = trim($header);
            if (!empty($header)) { // Only add non-empty headers
                $response_headers[] = $header;
            }
            return $len;
          }
        );

        // Set Decodo Proxy - COMMENTED OUT FOR TESTING
        // curl_setopt($ch, CURLOPT_PROXY, "{$decodo_proxy_host}:{$decodo_proxy_port}");
        // curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$decodo_user}:{$decodo_psw}");

        // Set additional headers (request headers)
        $request_headers_arr = array(
            'Accept-Language: en-US,en;q=0.9',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            "Referer: {$referer}",
            'DNT: 1',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers_arr);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Disabled SSL verification for testing
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // Disabled SSL verification for testing
        curl_setopt($ch, CURLOPT_ENCODING, ""); // Accept any encoding

        $html_content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error_no = curl_errno($ch);
        $curl_error_msg = curl_error($ch);

        curl_close($ch);

        $headers_string = "";
        if (!empty($response_headers)) {
            $headers_string = "\nResponse Headers:\n" . implode("\n", $response_headers);
        }

        if ($curl_error_no) {
            ps_log_error("cURL Error ({$curl_error_no}) attempt {$attempt} for {$url} direct connection: {$curl_error_msg}{$headers_string}");
            if ($attempt < $max_retries) {
                ps_log_error("Waiting {$current_retry_delay} seconds before next attempt...");
                sleep($current_retry_delay);
                continue;
            }
            return false;
        }

        if ($http_code !== 200) {
            ps_log_error("HTTP Error {$http_code} attempt {$attempt} for {$url} direct connection.{$headers_string}\nResponse Body (first 500 chars): " . substr($html_content, 0, 500));
            if ($attempt < $max_retries) {
                ps_log_error("Waiting {$current_retry_delay} seconds before next attempt...");
                sleep($current_retry_delay);
                continue;
            }
            return false;
        }

        ps_log_error("Successfully fetched {$url} directly after {$attempt} attempt(s). HTTP Status: {$http_code}");
        ps_save_response_sample($html_content);
        return $html_content; // Success
    }

    ps_log_error("All {$max_retries} direct fetch attempts failed for URL: {$url}.");
    return false; // All attempts failed
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
function ps_parse_amazon_results($html, $affiliate_id) {
    $products = array();
    ps_log_error("Starting to parse Amazon results.");

    // --- Attempt 1: Try to extract product data from embedded JSON in <script> tags ---
    $products_from_json = ps_try_extract_products_from_json_scripts($html, $affiliate_id);
    if (!empty($products_from_json)) {
        ps_log_error("Successfully extracted " . count($products_from_json) . " products using JSON from script tags.");
        // If JSON provides enough data, we might return here or merge/prioritize.
        // For now, let's assume JSON is comprehensive if found.
        // We might need to add a check here to ensure the JSON products have all necessary fields
        // (title, link, image, price_value) before considering it a full success.
        $products = $products_from_json; 
    } else {
        ps_log_error("Could not extract products from JSON in script tags. Falling back to XPath parsing.");
    }

    // --- Attempt 2: Fallback to XPath parsing if JSON extraction failed or yielded no products ---
    // Only run XPath if JSON parsing didn't yield results.
    // Or, you could merge results or use XPath to fill in missing pieces from JSON.
    // For simplicity now, if JSON fails, XPath runs. If JSON succeeds, we use its results.
    if (empty($products)) {
        ps_log_error("Proceeding with XPath-based HTML parsing.");
        // Use DOMDocument to parse the HTML
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8"?>' . $html); // Added XML encoding declaration for better parsing
        $xpath = new DOMXPath($dom);
        
        // Find product elements - Prioritizing data-cel-widget
        $product_node_selectors = array(
            '//div[contains(@data-cel-widget, "search_result_")]', // Primary for PUIS-like structure
            '//div[contains(@class, "puis-card-container")]', // Alternative for PUIS structure
            '//div[contains(@class, "s-result-item") and @data-component-type="s-search-result"]', // Old primary
            '//div[contains(@class, "s-result-item")]' // Broader fallback
        );

        $product_nodes = null;
        foreach ($product_node_selectors as $selector) {
            $product_nodes = $xpath->query($selector);
            if ($product_nodes && $product_nodes->length > 0) {
                ps_log_error("XPath: Product nodes found using selector: {$selector} (" . $product_nodes->length . " nodes)");
                break;
            }
            ps_log_error("XPath: No product nodes with selector: {$selector}. Trying next.");
        }
        
        if (!$product_nodes || $product_nodes->length === 0) {
            ps_log_error("XPath: No product nodes found after trying all selectors. HTML structure may have significantly changed or page is not as expected.");
            // return $products; // products is already empty
        } else {
            foreach ($product_nodes as $index => $product_node) {
                if (count($products) >= 80) { // Limit total products from XPath if JSON failed
                    ps_log_error("XPath: Processed 80 product nodes. Stopping further parsing for this page.");
                    break;
                }
                
                $title = '';
                $brand = ''; // Added brand variable
                $link = '';
                $image_url = '';
                $price = '';
                $price_value = 0;
                $rating_value = 0; // Add rating value variable
                $rating_count = 0; // Add rating count variable
                $delivery_time = '';

                // --- Extract Brand (XPath) ---
                $brand_selectors = array(
                    './/div[contains(@data-cy, "title-recipe")]/div[contains(@class, "a-color-secondary")]//span[contains(@class, "a-color-base")]', // Specific to PUIS structure
                    './/div[contains(@class, "s-title-instructions-style")]/div[contains(@class, "a-color-secondary")]//span[contains(@class, "a-color-base")]' // Slightly broader
                );
                foreach($brand_selectors as $bs_idx => $bs) {
                    $nodes = $xpath->query($bs, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        $brand = trim($nodes->item(0)->textContent);
                        if (!empty($brand)) {
                            ps_log_error("XPath: Brand found ('{$brand}') using selector #{$bs_idx} for product #{$index}");
                            break;
                        }
                    }
                }
                if (empty($brand)) ps_log_error("XPath: Brand not found for product #{$index}");


                // --- Extract Product Title (XPath) ---
                // Refined selectors, prioritizing PUIS structure
                $title_selectors = array(
                    './/div[contains(@data-cy, "title-recipe")]//a[contains(@class, "a-link-normal")]/h2[contains(@class, "a-text-normal")]/span', // PUIS primary title
                    './/div[contains(@class, "s-title-instructions-style")]//a[contains(@class, "a-link-normal")]/h2[contains(@class, "a-text-normal")]/span', // PUIS broader
                    './/h2[contains(@class, "a-size-base-plus") and contains(@class, "a-text-normal")]/span', // General h2 span
                    // Original fallbacks
                    './/h2//a//span[not(contains(@class, "a-color-secondary")) and not(contains(@class, "s-image-text-helper"))]', 
                    './/h2//span[not(contains(@class, "a-color-secondary")) and not(contains(@class, "s-image-text-helper"))]',
                    './/span[contains(@class, "a-text-normal") and contains(@class, "a-size-medium")]',
                    './/a[contains(@class, "a-link-normal")]//span[contains(@class, "a-text-normal")]'
                );
                foreach($title_selectors as $ts_idx => $ts) {
                    $nodes = $xpath->query($ts, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        $title_content = trim($nodes->item(0)->textContent);
                        // Sometimes the brand is part of this title string, try to remove it if brand is already found
                        if (!empty($brand) && stripos($title_content, $brand) === 0) {
                            $title_content = trim(substr($title_content, strlen($brand)));
                        }
                        if (!empty($title_content)) {
                            $title = $title_content;
                            ps_log_error("XPath: Title found ('{$title}') using selector #{$ts_idx} for product #{$index}");
                            break;
                        }
                    }
                }
                if (empty($title)) ps_log_error("XPath: Title not found for product #{$index}");

                // --- Extract Product Link (XPath) ---
                // Refined selectors
                $link_selectors = array(
                    './/div[contains(@data-cy, "title-recipe")]//a[contains(@class, "a-link-normal") and ./h2]', // Link containing the title H2
                    './/div[contains(@class, "s-product-image-container")]//a[contains(@class, "a-link-normal")]', // Link around image
                    // Original fallbacks
                    './/h2//a[contains(@class, "a-link-normal")]',
                    './/a[contains(@class, "s-product-link")]',
                    './/a[contains(@class, "a-link-normal") and .//img]' // This was selector 3, can be a good fallback
                );
                foreach($link_selectors as $ls_idx => $ls) {
                    $nodes = $xpath->query($ls, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        $link = trim($nodes->item(0)->getAttribute('href'));
                        if (!empty($link)) {
                            ps_log_error("XPath: Link found using selector #{$ls_idx} for product #{$index}");
                            break;
                        }
                    }
                }
                if (empty($link)) ps_log_error("XPath: Link not found for product #{$index}");

                // --- Extract Price (XPath) ---
                // Refined selectors, prioritizing PUIS structure
                $price_selectors = array(
                    './/div[contains(@data-cy, "price-recipe")]//span[contains(@class, "a-price")]/span[contains(@class, "a-offscreen")]', // PUIS primary price
                    './/div[contains(@data-cy, "price-recipe")]//span[contains(@class, "a-price-whole")]', // PUIS whole price part
                    // Original fallbacks
                    './/span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]',
                    './/span[contains(@class, "a-price-whole")]', 
                    './/div[contains(@class, "a-row")]//span[contains(@class, "a-color-price")]'
                );
                foreach($price_selectors as $ps_idx => $ps) {
                    $nodes = $xpath->query($ps, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        $price_text = trim($nodes->item(0)->textContent);
                        if ($ps_idx === 1 && $price_text) {
                             $fraction_node = $xpath->query('.//span[contains(@class, "a-price-fraction")]', $nodes->item(0)->parentNode)->item(0);
                             if ($fraction_node) $price_text .= trim($fraction_node->textContent);
                        }
                        if (!empty($price_text)) {
                            $price_value = (float) preg_replace('/[^0-9.]/', '', $price_text);
                            if ($price_value > 0) {
                                $price = $price_text;
                                ps_log_error("XPath: Price found using selector #{$ps_idx} for product #{$index}. Value: {$price_value}");
                                break;
                            }
                        }
                    }
                }
                if (empty($price)) ps_log_error("XPath: Price not found for product #{$index}");
                
                // --- Extract Image URL (XPath) ---
                $image_url = ''; // Initialize
                
                // Use a highly specific selector for s-image class
                $s_image_selector = './/img[@class="s-image"]';
                $image_nodes = $xpath->query($s_image_selector, $product_node);
                
                // If not found with exact class, try contains
                if (!$image_nodes || $image_nodes->length === 0) {
                    $s_image_selector = './/img[contains(@class, "s-image")]';
                    $image_nodes = $xpath->query($s_image_selector, $product_node);
                }
                
                if ($image_nodes && $image_nodes->length > 0) {
                    $img_node = $image_nodes->item(0);
                    
                    // First try to get image from srcset attribute (preferred)
                    $srcset_attr = trim($img_node->getAttribute('srcset'));
                    if (!empty($srcset_attr)) {
                        // Parse the srcset attribute to get the first URL (1x version)
                        $srcset_parts = explode(',', $srcset_attr);
                        if (!empty($srcset_parts[0])) {
                            // Extract URL from "url 1x" format
                            $first_srcset = trim($srcset_parts[0]);
                            $url_parts = explode(' ', $first_srcset);
                            if (!empty($url_parts[0])) {
                                $image_url = trim($url_parts[0]);
                                ps_log_error("XPath: Image found from s-image srcset (first URL): {$image_url}");
                            }
                        }
                    }
                    
                    // Fallback to src attribute if srcset parsing failed
                    if (empty($image_url)) {
                        $src_attr = trim($img_node->getAttribute('src'));
                        
                        if (!empty($src_attr) && $src_attr !== 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') {
                            $image_url = $src_attr;
                            ps_log_error("XPath: Image found from s-image src (fallback): {$image_url}");
                        } else {
                            // Fallback to data-src only if src is empty or a placeholder
                            $data_src_attr = trim($img_node->getAttribute('data-src'));
                            if (!empty($data_src_attr) && $data_src_attr !== 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7') {
                                $image_url = $data_src_attr;
                                ps_log_error("XPath: Image found from s-image data-src (src was empty)");
                            }
                        }
                    }
                }
                
                if (empty($image_url)) {
                    ps_log_error("XPath: No image found with s-image class for product #{$index}");
                }

                // --- Advanced cleanup for Amazon image URLs ---
                if (!empty($image_url)) {
                    // For specific Amazon image patterns, ensure the URL structure is correct
                    
                    // Case 1: If it looks like a raw image filename (e.g., 61UQLUh8D8L._AC_UL320_.jpg)
                    if (preg_match('/^([A-Za-z0-9]{8,})\._.*_.jpg$/', basename($image_url), $matches)) {
                        $image_id = $matches[1]; // e.g., "61UQLUh8D8L"
                        $size_suffix = substr(strrchr(basename($image_url), '.'), 0, -4); // e.g., "._AC_UL320_"
                        
                        // Reconstruct as a properly formatted Amazon media URL
                        $image_url = "https://m.media-amazon.com/images/I/{$image_id}{$size_suffix}.jpg";
                        ps_log_error("XPath: Reconstructed Amazon image URL to standard format: {$image_url}");
                    }
                    // Case 2: If it's a www.amazon.com URL but missing the media path
                    elseif (strpos($image_url, 'amazon.com/') !== false && strpos($image_url, 'media-amazon.com/') === false) {
                        // Extract image ID and suffix if possible
                        if (preg_match('/([A-Za-z0-9]{8,})\._.*_.jpg$/', $image_url, $matches)) {
                            $image_id = $matches[1];
                            $size_suffix = substr(strrchr(basename($image_url), '.'), 0, -4);
                            
                            // Reconstruct proper URL
                            $image_url = "https://m.media-amazon.com/images/I/{$image_id}{$size_suffix}.jpg";
                            ps_log_error("XPath: Converted misformatted Amazon URL to standard format: {$image_url}");
                        }
                    }
                }
                // --- End Image URL cleaning ---

                // --- Extract Delivery Time (XPath) ---
                $delivery_time = '';
                $delivery_selectors = array(
                    // Primary selector matching the exact structure provided
                    './/div[contains(@class, "udm-primary-delivery-message")]', // This should find the specific Amazon delivery message
                    
                    // Backup selectors in case the primary one doesn't match
                    './/div[contains(@class, "a-row") and contains(@class, "a-color-base") and contains(text(), "delivery")]',
                    './/span[contains(@class, "a-text-bold") and contains(text(), "Delivery")]//following-sibling::span',
                    './/div[contains(@class, "a-row") and contains(@class, "a-size-base") and contains(text(), "FREE delivery")]',
                    './/span[contains(text(), "FREE delivery")]',
                    './/span[contains(text(), "Get it") and contains(text(), "by")]',
                    './/span[contains(@class, "a-color-base") and contains(text(), "Delivery")]',
                    './/div[contains(@class, "a-row")]//span[contains(text(), "Delivery") or contains(text(), "delivery")]'
                );
                
                foreach($delivery_selectors as $ds_idx => $ds) {
                    $nodes = $xpath->query($ds, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        ps_log_error("XPath: Found potential delivery node with selector #{$ds_idx} for product #{$index}");
                        
                        // Get the text content of the node and all its children
                        $delivery_text = trim($nodes->item(0)->textContent);
                        
                        // For the primary delivery message container, try to extract the date properly
                        if ($ds_idx === 0) {
                            // Look for the bold text inside the delivery message
                            $bold_nodes = $xpath->query('.//span[contains(@class, "a-text-bold")]', $nodes->item(0));
                            if ($bold_nodes && $bold_nodes->length > 0) {
                                $date_text = trim($bold_nodes->item(0)->textContent);
                                if (!empty($date_text)) {
                                    $delivery_text = "FREE delivery " . $date_text;
                                    ps_log_error("XPath: Successfully extracted delivery date: {$date_text}");
                                }
                            }
                        }
                        
                        if (!empty($delivery_text)) {
                            // Clean up the delivery text
                            $delivery_text = preg_replace('/\s+/', ' ', $delivery_text);
                            $delivery_time = $delivery_text;
                            ps_log_error("XPath: Delivery time found ({$delivery_time}) using selector #{$ds_idx} for product #{$index}");
                            break;
                        }
                    }
                }
                
                if (empty($delivery_time)) {
                    ps_log_error("XPath: Delivery time not found for product #{$index}");
                }

                // --- Extract Rating (XPath) ---
                // Extract rating value (usually out of 5 stars)
                $rating_selectors = array(
                    './/span[contains(@class, "a-icon-alt")]', // Common rating container with text like "4.5 out of 5 stars"
                    './/i[contains(@class, "a-icon-star-small")]/span', // Alternative rating container
                    './/div[contains(@class, "a-row")]/span[contains(@class, "a-declarative")]//span[contains(@class, "a-icon-alt")]' // Another common pattern
                );
                
                foreach($rating_selectors as $rs_idx => $rs) {
                    $nodes = $xpath->query($rs, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        $rating_text = trim($nodes->item(0)->textContent);
                        // Extract numeric rating from text like "4.5 out of 5 stars"
                        if (preg_match('/([0-9]\.[0-9]|\d)/', $rating_text, $matches)) {
                            $rating_value = floatval($matches[0]);
                            ps_log_error("XPath: Rating found ({$rating_value}) using selector #{$rs_idx} for product #{$index}");
                            break;
                        }
                    }
                }
                
                // Extract rating count - Enhanced with more robust selectors
                $rating_count_selectors = array(
                    './/span[contains(@class, "a-size-base") and contains(@class, "s-underline-text")]', // Common rating count element
                    './/div[contains(@class, "a-row")]//a[contains(@href, "customerReviews")]/span[contains(@class, "a-size-base")]', // Direct link to reviews
                    './/span[contains(@class, "a-size-small")]/a[contains(@href, "customerReviews")]', // Alternative format
                    './/div[contains(@class, "a-row")]//span[contains(text(), "rating")]', // Text containing "rating"
                    './/div[contains(@class, "a-row")]//span[contains(text(), "review")]', // Text containing "review"
                    './/a[contains(@href, "customerReviews")]', // Any link to customer reviews
                    './/a[contains(@href, "#customerReviews")]' // Alternative link format
                );
                
                foreach($rating_count_selectors as $rcs_idx => $rcs) {
                    $nodes = $xpath->query($rcs, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        foreach($nodes as $node) {
                            $count_text = trim($node->textContent);
                            ps_log_error("XPath: Potential rating count text: '{$count_text}' for product #{$index}");
                            
                            // Try to extract just the number of ratings with multiple patterns
                            if (preg_match('/([0-9,]+)\s*ratings?/i', $count_text, $matches) || 
                                preg_match('/([0-9,]+)\s*reviews?/i', $count_text, $matches) ||
                                preg_match('/\(([0-9,]+)\)/', $count_text, $matches)) {
                                
                                $count_clean = str_replace(',', '', $matches[1]);
                                $rating_count = intval($count_clean);
                                ps_log_error("XPath: Successfully extracted rating count ({$rating_count}) from '{$count_text}'");
                                break 2; // Break both loops if found
                            }
                            
                            // If no specific pattern matches, try extracting any numbers
                            if (empty($rating_count)) {
                                // Extract any numbers from the text
                                $count_clean = preg_replace('/[^0-9]/', '', $count_text);
                                if (!empty($count_clean) && strlen($count_clean) < 7) { // Avoid extracting things like product IDs
                                    $rating_count = intval($count_clean);
                                    ps_log_error("XPath: Extracted possible rating count ({$rating_count}) from '{$count_text}' using fallback method");
                                    // Don't break here, keep looking for a better match
                                }
                            }
                        }
                    }
                }
                
                // Final logging for debugging
                if (empty($rating_count)) {
                    ps_log_error("XPath: No rating count found for product #{$index}");
                } else {
                    ps_log_error("XPath: Final rating count: {$rating_count} for product #{$index}");
                }

                // --- Extract Product Rating Link (XPath) ---
                $rating_link = '';
                $rating_link_selectors = array(
                    './/a[contains(@href, "customerReviews")]', // Direct link to customer reviews
                    './/a[contains(@href, "#customerReviews")]' // Link to review section on product page
                );
                
                foreach($rating_link_selectors as $rls_idx => $rls) {
                    $nodes = $xpath->query($rls, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        $href = trim($nodes->item(0)->getAttribute('href'));
                        if (!empty($href)) {
                            // Make sure link is absolute
                            if (strpos($href, 'http') !== 0) {
                                // Check if it's a full path or just a fragment
                                if (strpos($href, '#') === 0) {
                                    // It's just a fragment, append to product link
                                    $rating_link = $link . $href;
                                } else {
                                    // It's a path, make it absolute
                                    $rating_link = rtrim('https://www.amazon.com', '/') . '/' . ltrim($href, '/');
                                }
                            } else {
                                $rating_link = $href;
                            }
                            
                            // Add affiliate tag if not present
                            if (strpos($rating_link, 'tag=') === false) {
                                $rating_link .= (strpos($rating_link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                            }
                            
                            ps_log_error("XPath: Rating link found ({$rating_link}) using selector #{$rls_idx} for product #{$index}");
                            break;
                        }
                    }
                }
                
                // If no specific rating link found, but we have a product link, create one by appending #customerReviews
                if (empty($rating_link) && !empty($link) && $rating_value > 0) {
                    $rating_link = $link . (strpos($link, '#') === false ? '#customerReviews' : '');
                    ps_log_error("XPath: Created rating link ({$rating_link}) from product link for product #{$index}");
                }

                // --- Extract Price Per Unit (XPath) ---
                $price_per_unit = '';
                $price_per_unit_value = 0;
                $unit = '';
                
                $price_per_unit_selectors = array(
                    './/span[contains(@class, "a-size-base") and contains(@class, "a-color-secondary") and contains(text(), "(")]/span[contains(@class, "a-price") and contains(@class, "a-text-price")]/span[contains(@class, "a-offscreen")]',
                    './/span[contains(@class, "a-size-base") and contains(@class, "a-color-secondary") and contains(text(), "($")]/span[contains(@class, "a-price")]//span[contains(@class, "a-offscreen")]'
                );
                
                foreach ($price_per_unit_selectors as $ppu_idx => $ppu) {
                    $nodes = $xpath->query($ppu, $product_node);
                    if ($nodes && $nodes->length > 0) {
                        $price_per_unit_text = trim($nodes->item(0)->textContent);
                        if (!empty($price_per_unit_text)) {
                            // Clean the price text and extract numeric value
                            $price_per_unit_value = (float) preg_replace('/[^0-9.]/', '', $price_per_unit_text);
                            $price_per_unit = number_format($price_per_unit_value, 2);
                            ps_log_error("XPath: Price per unit found: {$price_per_unit_text} for product #{$index}");
                            
                            // Now try to extract the unit 
                            $unit_selectors = array(
                                './/span[contains(@class, "a-size-base") and contains(@class, "a-color-secondary") and contains(text(), "(/")]',
                                './/span[contains(@class, "a-size-base") and contains(@class, "a-color-secondary") and contains(text(), "(")]'
                            );
                            
                            foreach ($unit_selectors as $u_idx => $us) {
                                $unit_nodes = $xpath->query($us, $product_node);
                                if ($unit_nodes && $unit_nodes->length > 0) {
                                    $unit_text = trim($unit_nodes->item(0)->textContent);
                                    // Extract unit from text like "($4.40/gram)" or similar
                                    if (preg_match('/\(.*?\/([^)]+)\)/', $unit_text, $matches)) {
                                        $unit = trim($matches[1]);
                                        ps_log_error("XPath: Unit found: {$unit} for product #{$index}");
                                        break;
                                    }
                                }
                            }
                            break;
                        }
                    }
                }
                
                if (empty($price_per_unit)) {
                    // If we couldn't find a price per unit, use the regular price
                    ps_log_error("XPath: No price per unit found for product #{$index}. Using regular price as fallback.");
                    $price_per_unit_value = $price_value > 0 ? ($price_value / 100) : 0;
                    $price_per_unit = number_format($price_per_unit_value, 2);
                    $unit = $price_value > 0 ? 'unit' : 'N/A';
                }

                if (empty($title) || empty($link)) {
                    ps_log_error("XPath: Skipping product #{$index} due to missing title or link.");
                    continue;
                }
                
                if (strpos($link, 'http') !== 0) {
                    $link = rtrim('https://www.amazon.com', '/') . '/' . ltrim($link, '/');
                }
                $link = add_query_arg('tag', $affiliate_id, $link);
                
                $product_data = array(
                    'brand' => $brand, // Added brand
                    'title' => $title,
                    'link' => $link,
                    'image' => $image_url,
                    'price' => $price_value > 0 ? number_format($price_value, 2) : 'N/A',
                    'price_value' => $price_value,
                    'price_per_unit' => $price_per_unit,
                    'price_per_unit_value' => $price_per_unit_value,
                    'unit' => $unit,
                    'description' => !empty($title) ? substr($title, 0, 100) . '...' : '',
                );
                
                // Only add rating information if it exists
                if ($rating_value > 0) {
                    $product_data['rating_number'] = number_format($rating_value, 1);
                    $product_data['rating'] = str_repeat('★', round($rating_value)) . str_repeat('☆', 5 - round($rating_value));
                    
                    if ($rating_count > 0) {
                        $product_data['rating_count'] = $rating_count;
                    }
                    
                    if (!empty($rating_link)) {
                        $product_data['rating_link'] = $rating_link;
                    } else {
                        $product_data['rating_link'] = $link . '#customerReviews';
                    }
                }
                
                // Add delivery time to product data (XPath section)
                if (!empty($delivery_time)) {
                    $product_data['delivery_time'] = $delivery_time;
                }

                $products[] = $product_data;
            }
        }
    } // End of XPath fallback
    
    ps_log_error("Finished all parsing attempts. Found " . count($products) . " total products.");
    return $products;
}

/**
 * Attempts to extract product information from JSON embedded in <script> tags.
 *
 * @param string $html The HTML content of the Amazon page.
 * @param string $affiliate_id The Amazon affiliate ID.
 * @return array An array of product data if found, otherwise an empty array.
 */
function ps_try_extract_products_from_json_scripts($html, $affiliate_id) {
    $extracted_products = array();
    ps_log_error("Attempting to extract products from JSON in script tags.");

    // Regular expression to find JSON-like structures within <script> tags.
    // This is a common pattern, but might need adjustment.
    // Looks for assignments to window objects or variables, or just plain JSON objects.
    preg_match_all('/<script[^>]*>(.*?)<\\/script>/is', $html, $scripts_matches);

    if (empty($scripts_matches[1])) {
        ps_log_error("JSON: No script tags found.");
        return $extracted_products;
    }

    $possible_json_keys = array('title', 'dpUrl', 'url', 'imgUrl', 'imageUrl', 'price', 'priceString', 'asin');

    foreach ($scripts_matches[1] as $script_content) {
        // Attempt to find JSON objects within the script content
        // This regex looks for something that starts with { and ends with } and is likely a JSON object
        // It also tries to capture assignments like `var data = {...};` or `window.obj = {...};`
        preg_match_all('/(?:var|let|const|window\\.[a-zA-Z0-9_]+)\\s*=\\s*(\\{.*?\\});|(\\{[\\s\\S]*?\\})/ms', $script_content, $json_matches_arr);
        
        $potential_json_strings = array();
        if (!empty($json_matches_arr[1])) {
            $potential_json_strings = array_merge($potential_json_strings, array_filter($json_matches_arr[1]));
        }
        if (!empty($json_matches_arr[2])) {
            $potential_json_strings = array_merge($potential_json_strings, array_filter($json_matches_arr[2]));
        }

        foreach ($potential_json_strings as $json_str) {
            // Clean up the JSON string: remove trailing commas, comments, etc.
            // This is a basic cleanup. More robust cleaning might be needed.
            $json_str = preg_replace('/,\\s*([}\\]])/', '$1', $json_str); // Remove trailing commas
            $json_str = preg_replace('/\\/\\*.*?\\*\\/|\\/\\/.*?\\n/', '', $json_str); // Remove comments

            $data = json_decode($json_str, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                // Check if this decoded data looks like a list of products or a single product
                // This requires heuristics based on common Amazon JSON structures.
                
                $items_to_process = array();
                // Heuristic 1: Does the root array look like a list of products?
                if (isset($data[0]) && is_array($data[0]) && ps_data_looks_like_product($data[0], $possible_json_keys)) {
                    $items_to_process = $data;
                     ps_log_error("JSON: Found a potential list of products directly in decoded JSON array.");
                } 
                // Heuristic 2: Are there nested arrays that look like product lists?
                // Example: 'results', 'items', 'products', 'initialData'
                else {
                    foreach ($data as $key => $value) {
                        if (is_array($value)) {
                            // If $value is an array of arrays (potential list of products)
                            if (isset($value[0]) && is_array($value[0]) && ps_data_looks_like_product($value[0], $possible_json_keys)) {
                                $items_to_process = $value;
                                ps_log_error("JSON: Found a potential list of products in nested key '{$key}'.");
                                break; 
                            }
                            // If $value itself looks like a single product object
                            elseif (ps_data_looks_like_product($value, $possible_json_keys)) {
                                $items_to_process = array($value); // Treat as a list of one
                                ps_log_error("JSON: Found a single potential product in nested key '{$key}'.");
                                break;
                            }
                        }
                    }
                }
                 // Heuristic 3: Check if the root $data itself is a product object (if not an array of products)
                if(empty($items_to_process) && ps_data_looks_like_product($data, $possible_json_keys)){
                    $items_to_process = array($data);
                    ps_log_error("JSON: The root decoded JSON object looks like a single product.");
                }


                if (!empty($items_to_process)) {
                    ps_log_error("JSON: Processing " . count($items_to_process) . " potential product items from JSON.");
                    foreach ($items_to_process as $item_data) {
                        if (!is_array($item_data)) continue;

                        $title = '';
                        $link = '';
                        $image_url = '';
                        $price_str = '';
                        $price_value = 0;
                        $rating_value = 0;
                        $rating_count = 0;
                        $delivery_time = '';

                        // Map common Amazon JSON keys to our variables
                        // This is highly dependent on observed JSON structures on Amazon pages
                        if (!empty($item_data['title'])) $title = trim($item_data['title']);
                        elseif (!empty($item_data['productTitle'])) $title = trim($item_data['productTitle']);
                        
                        if (!empty($item_data['dpUrl'])) $link = trim($item_data['dpUrl']);
                        elseif (!empty($item_data['url'])) $link = trim($item_data['url']);
                        elseif (!empty($item_data['productUrl'])) $link = trim($item_data['productUrl']);

                        if (!empty($item_data['imgUrl'])) $image_url = trim($item_data['imgUrl']);
                        elseif (!empty($item_data['imageUrl'])) $image_url = trim($item_data['imageUrl']);
                        elseif (!empty($item_data['thumbnailImage']) && !empty($item_data['thumbnailImage']['url'])) $image_url = trim($item_data['thumbnailImage']['url']);
                         elseif (!empty($item_data['image']) && is_string($item_data['image'])) $image_url = trim($item_data['image']);


                        if (!empty($item_data['priceString'])) $price_str = trim($item_data['priceString']);
                        elseif (!empty($item_data['price'])) $price_str = trim($item_data['price']); // Could be an object or string
                        elseif (!empty($item_data['price']['displayPrice'])) $price_str = trim($item_data['price']['displayPrice']);
                        elseif (!empty($item_data['priceInfo']['priceString'])) $price_str = trim($item_data['priceInfo']['priceString']);
                        
                        if (is_array($price_str) && isset($price_str['priceToPay'])) { // another common pattern
                            $price_str = $price_str['priceToPay'];
                        }
                        if (is_string($price_str)) {
                           $price_value = (float) preg_replace('/[^0-9.]/', '', $price_str);
                        }

                        // Extract rating information
                        if (!empty($item_data['rating'])) {
                            if (is_numeric($item_data['rating'])) {
                                $rating_value = floatval($item_data['rating']);
                            } elseif (is_string($item_data['rating']) && preg_match('/([0-9]\.[0-9]|\d)/', $item_data['rating'], $matches)) {
                                $rating_value = floatval($matches[0]);
                            }
                        }
                        elseif (!empty($item_data['ratings']) && !empty($item_data['ratings']['average'])) {
                            $rating_value = floatval($item_data['ratings']['average']);
                        }
                        elseif (!empty($item_data['averageRating'])) {
                            $rating_value = floatval($item_data['averageRating']);
                        }
                        
                        // Extract rating count - Enhanced with more robust handling
                        if (!empty($item_data['ratingCount'])) {
                            $rating_count = intval($item_data['ratingCount']);
                            ps_log_error("JSON: Rating count found directly: {$rating_count}");
                        }
                        elseif (!empty($item_data['ratings']) && !empty($item_data['ratings']['count'])) {
                            $rating_count = intval($item_data['ratings']['count']);
                            ps_log_error("JSON: Rating count found in ratings.count: {$rating_count}");
                        }
                        elseif (!empty($item_data['reviewCount'])) {
                            $rating_count = intval($item_data['reviewCount']);
                            ps_log_error("JSON: Rating count found in reviewCount: {$rating_count}");
                        }
                        elseif (!empty($item_data['reviews']) && !empty($item_data['reviews']['count'])) {
                            $rating_count = intval($item_data['reviews']['count']);
                            ps_log_error("JSON: Rating count found in reviews.count: {$rating_count}");
                        }
                        elseif (!empty($item_data['totalReviews'])) {
                            $rating_count = intval($item_data['totalReviews']);
                            ps_log_error("JSON: Rating count found in totalReviews: {$rating_count}");
                        }
                        
                        // Look for textual review mentions
                        if (empty($rating_count)) {
                            foreach ($item_data as $key => $value) {
                                if (is_string($value) && 
                                    (stripos($value, 'review') !== false || stripos($value, 'rating') !== false)) {
                                    ps_log_error("JSON: Found potential review text in key {$key}: {$value}");
                                    
                                    if (preg_match('/([0-9,]+)\s*ratings?/i', $value, $matches) || 
                                        preg_match('/([0-9,]+)\s*reviews?/i', $value, $matches) ||
                                        preg_match('/\(([0-9,]+)\)/', $value, $matches)) {
                                        
                                        $count_clean = str_replace(',', '', $matches[1]);
                                        $rating_count = intval($count_clean);
                                        ps_log_error("JSON: Successfully extracted rating count ({$rating_count}) from text");
                                        break;
                                    }
                                }
                            }
                        }
                        
                        if (empty($rating_count)) {
                            ps_log_error("JSON: No rating count found for product: {$title}");
                        } else {
                            ps_log_error("JSON: Final rating count: {$rating_count} for product: {$title}");
                        }

                        // Extract product rating link
                        $rating_link = '';
                        
                        // Check for direct review link in the data
                        if (!empty($item_data['reviewsUrl'])) {
                            $rating_link = $item_data['reviewsUrl'];
                        } elseif (!empty($item_data['customerReviewsUrl'])) {
                            $rating_link = $item_data['customerReviewsUrl'];
                        } elseif (!empty($item_data['reviews']) && !empty($item_data['reviews']['url'])) {
                            $rating_link = $item_data['reviews']['url'];
                        }
                        
                        // Make sure the link is absolute and has the affiliate tag
                        if (!empty($rating_link)) {
                            if (strpos($rating_link, 'http') !== 0) {
                                $rating_link = rtrim('https://www.amazon.com', '/') . '/' . ltrim($rating_link, '/');
                            }
                            
                            // Add affiliate tag if not present
                            if (strpos($rating_link, 'tag=') === false) {
                                $rating_link .= (strpos($rating_link, '?') === false ? '?' : '&') . 'tag=' . $affiliate_id;
                            }
                            
                            ps_log_error("JSON: Rating link found: {$rating_link}");
                        } 
                        // If no direct link found but we have ratings, create one
                        else if ($rating_value > 0 && !empty($link)) {
                            $rating_link = $link . (strpos($link, '#') === false ? '#customerReviews' : '');
                            ps_log_error("JSON: Created rating link from product link: {$rating_link}");
                        }

                        // Extract delivery time from JSON
                        if (!empty($item_data['deliveryInfo'])) {
                            if (is_string($item_data['deliveryInfo'])) {
                                $delivery_time = trim($item_data['deliveryInfo']);
                            } elseif (is_array($item_data['deliveryInfo']) && !empty($item_data['deliveryInfo']['text'])) {
                                $delivery_time = trim($item_data['deliveryInfo']['text']);
                            }
                        } elseif (!empty($item_data['delivery']) && is_string($item_data['delivery'])) {
                            $delivery_time = trim($item_data['delivery']);
                        } elseif (!empty($item_data['deliveryMessage']) && is_string($item_data['deliveryMessage'])) {
                            $delivery_time = trim($item_data['deliveryMessage']);
                        }
                        
                        if (!empty($delivery_time)) {
                            $delivery_time = preg_replace('/\s+/', ' ', $delivery_time);
                            ps_log_error("JSON: Delivery time found: {$delivery_time}");
                        }

                        if (!empty($title) && !empty($link) && $price_value > 0) {
                            if (strpos($link, 'http') !== 0) {
                                $link = rtrim('https://www.amazon.com', '/') . '/' . ltrim($link, '/');
                            }
                            $link = add_query_arg('tag', $affiliate_id, $link);

                            // --- Ensure image URL is clean and absolute here too ---
                            if (!empty($image_url)) {
                                // 1. Strip "local save" artifacts
                                $image_url = preg_replace('/(?:[^\/]+\\_files\\\/)/i', '', $image_url);

                                // 2. Remove leading "./" if not part of a domain
                                if (strpos($image_url, 'amazon.com') === false && strpos($image_url, '//') !== 0 && strpos($image_url, 'http') !== 0) {
                                     $image_url = ltrim($image_url, './');
                                }

                                // 3. Make absolute
                                if (strpos($image_url, 'http') !== 0 && strpos($image_url, 'data:image') !== 0) {
                                    if (strpos($image_url, '//') === 0) {
                                        $image_url = 'https:' . $image_url;
                                    } elseif (strpos($image_url, '/') === 0) {
                                        $image_url = rtrim('https://www.amazon.com', '/') . $image_url;
                                    } else {
                                        $image_url = rtrim('https://www.amazon.com', '/') . '/' . ltrim($image_url, '/');
                                    }
                                }
                            }
                            // --- End Image URL cleaning ---

                            // Try to extract price per unit data from JSON
                            $price_per_unit_value = 0;
                            $price_per_unit = '';
                            $unit = '';
                            
                            // Check if JSON has unit price info directly
                            if (!empty($item_data['pricePerUnit'])) {
                                if (is_string($item_data['pricePerUnit'])) {
                                    // Direct string value
                                    $price_per_unit = trim($item_data['pricePerUnit']);
                                    $price_per_unit_value = (float) preg_replace('/[^0-9.]/', '', $price_per_unit);
                                    ps_log_error("JSON: Found price per unit: {$price_per_unit}");
                                    
                                    // Look for unit in the same field
                                    if (preg_match('/\/([^0-9\s][^\/\s]*)/', $price_per_unit, $matches)) {
                                        $unit = trim($matches[1]);
                                        ps_log_error("JSON: Extracted unit: {$unit}");
                                    }
                                } elseif (is_array($item_data['pricePerUnit'])) {
                                    // Structure with value and unit
                                    if (!empty($item_data['pricePerUnit']['value'])) {
                                        $price_per_unit_value = (float) $item_data['pricePerUnit']['value'];
                                        $price_per_unit = number_format($price_per_unit_value, 2);
                                        ps_log_error("JSON: Found price per unit value: {$price_per_unit_value}");
                                    }
                                    if (!empty($item_data['pricePerUnit']['unit'])) {
                                        $unit = trim($item_data['pricePerUnit']['unit']);
                                        ps_log_error("JSON: Found unit: {$unit}");
                                    }
                                }
                            }
                            
                            // If we still don't have a unit price, use the regular price as fallback
                            if (empty($price_per_unit)) {
                                $price_per_unit_value = $price_value > 0 ? ($price_value / 100) : 0;
                                $price_per_unit = number_format($price_per_unit_value, 2);
                                $unit = $price_value > 0 ? 'unit' : 'N/A';
                                ps_log_error("JSON: Using regular price as fallback for price per unit");
                            }

                            $product_data = array(
                                'title' => $title,
                                'link' => $link,
                                'image' => $image_url,
                                'price' => number_format($price_value, 2),
                                'price_value' => $price_value,
                                'price_per_unit' => $price_per_unit,
                                'price_per_unit_value' => $price_per_unit_value,
                                'unit' => $unit,
                                'description' => substr($title, 0, 100) . '...',
                            );
                            
                            // Only add rating information if it exists
                            if ($rating_value > 0) {
                                $product_data['rating_number'] = number_format($rating_value, 1);
                                $product_data['rating'] = str_repeat('★', round($rating_value)) . str_repeat('☆', 5 - round($rating_value));
                                
                                if ($rating_count > 0) {
                                    $product_data['rating_count'] = $rating_count;
                                }
                                
                                if (!empty($rating_link)) {
                                    $product_data['rating_link'] = $rating_link;
                                } else {
                                    $product_data['rating_link'] = $link . '#customerReviews';
                                }
                            }
                            
                            // Add delivery time if available
                            if (!empty($delivery_time)) {
                                $product_data['delivery_time'] = $delivery_time;
                            }
                            
                            $extracted_products[] = $product_data;
                            ps_log_error("JSON: Successfully mapped product: {$title}");
                            if (count($extracted_products) >= 80) break; // Limit products from JSON
                        }
                    }
                    if (!empty($extracted_products)) return $extracted_products; // Return if we got something from this JSON blob
                }
            } else if (json_last_error() !== JSON_ERROR_NONE) {
                 // ps_log_error("JSON: Error decoding JSON string: " . json_last_error_msg() . ". String (first 200 chars): " . substr($json_str,0,200));
            }
        }
        if (!empty($extracted_products)) break; // If products found from one script, no need to check others for now
    }
    
    if (empty($extracted_products)) {
        ps_log_error("JSON: No products extracted after checking all script tags.");
    }
    return $extracted_products;
}

/**
 * Helper function to check if a data array looks like a product.
 * This is a heuristic and might need refinement.
 * @param array $data The array to check.
 * @param array $possible_json_keys Keys that often appear in product data.
 * @return bool True if it looks like product data.
 */
function ps_data_looks_like_product($data, $possible_json_keys) {
    if (!is_array($data) || empty($data)) {
        return false;
    }
    $found_keys = 0;
    $required_key_present = false;

    // Check for at least a few common keys
    foreach ($possible_json_keys as $key) {
        if (isset($data[$key]) && !empty($data[$key])) {
            $found_keys++;
        }
    }
    // A product usually has an ASIN or a specific product URL ('dpUrl' or similar)
    if (isset($data['asin']) || isset($data['dpUrl']) || isset($data['productUrl'])) {
        $required_key_present = true;
    }

    // Heuristic: at least 2-3 common keys present, and one of the required keys.
    return ($found_keys >= 2 && $required_key_present);
}

/**
 * Log error messages
 *
 * @param string $message The error message
 */
function ps_log_error($message) {
    // Create logs directory if it doesn't exist
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
    
    // Log the error
    $log_file = $logs_dir . '/error_log.txt';
    $timestamp = date('[Y-m-d H:i:s]');
    file_put_contents($log_file, "{$timestamp} {$message}\n", FILE_APPEND);
}

/**
 * Save a sample of the response for debugging
 *
 * @param string $html The HTML content
 */
function ps_save_response_sample($html) {
    // Only save a small portion to avoid filling up the disk
    $sample = substr($html, 0, 150000);
    
    // Create logs directory if it doesn't exist
    $logs_dir = PS_PLUGIN_DIR . 'logs';
    if (!file_exists($logs_dir)) {
        mkdir($logs_dir, 0755, true);
    }
    
    // Save the sample
    $file = $logs_dir . '/amazon_response_' . date('Y-m-d_H-i-s') . '.html';
    file_put_contents($file, $sample);
    
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