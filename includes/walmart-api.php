<?php
/**
 * Walmart Scraper integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search Walmart products using web scraping
 *
 * @param string $query The main search query
 * @param string $exclude_keywords Keywords to exclude from results
 * @param string $sort_by Sorting method (price, price_per_unit)
 * @param string $country Country code ('us' or 'ca')
 * @param float $min_rating Minimum rating filter (3.5, 4.0, 4.5)
 * @param int $page Page number for pagination (default: 1)
 * @return array Search results
 */
function ps_search_walmart_products($query, $exclude_keywords = '', $sort_by = 'price', $country = 'us', $min_rating = 4.0, $page = 1) {
    // If query is empty, return empty array
    if (empty($query)) {
        return array();
    }
    
    // ps_log_error("Initiating Walmart search for: '{$query}' in country: {$country}, page: {$page}");
    
    // Construct the Walmart search URL with pagination
    $search_url = ps_construct_walmart_search_url($query, $country, $page);
    // ps_log_error("Constructed Walmart search URL: {$search_url}");
    
    // Get the search results HTML
    $html_content = ps_fetch_walmart_search_results($search_url, $country);

    // If the fetch function returns an array (success or error), return it directly
    if (is_array($html_content) && (isset($html_content['success']) || isset($html_content['error']))) {
        // Extract pagination URLs for pages 2 and 3 if HTML is present
        if (isset($html_content['html'])) {
            $pagination_urls = ps_extract_walmart_pagination_urls($html_content['html'], $country);
            $html_content['pagination_urls'] = $pagination_urls;
        }
        return $html_content;
    }

    // Extract pagination URLs for pages 2 and 3
    $pagination_urls = ps_extract_walmart_pagination_urls($html_content, $country);
    if (empty($pagination_urls)) {
        $pagination_urls = (object)[];
    }

    // Fallback: unexpected result
    return array(
        'success' => false,
        'items' => array(),
        'count' => 0,
        'message' => 'Walmart search is not yet fully implemented. Please try Amazon or eBay.',
        'search_query' => $query,
        'country' => $country,
        'pagination_urls' => $pagination_urls
    );
}

/**
 * Extract products from embedded JSON data
 */
function ps_extract_walmart_products_from_json($html_content) {
    $products = array();
    
    // Find the JSON data in the HTML
    if (preg_match('/<script[^>]*id="__NEXT_DATA__"[^>]*>(.*?)<\/script>/s', $html_content, $matches)) {
        $json_data = json_decode($matches[1], true);
        
        if ($json_data === null) {
            // JSON parsing failed - log the raw JSON for debugging
            error_log("[JSON Debug] Failed to parse JSON data");
            return $products;
        }
        
        // Log the structure we found
        error_log("[JSON Debug] Found JSON data structure");
        
        // Try multiple possible paths for the products data
        $json_products = array();
        
        // Path 1: Direct searchResult items (most common)
        if (isset($json_data['props']['pageProps']['initialData']['searchResult']['items'])) {
            $json_products = $json_data['props']['pageProps']['initialData']['searchResult']['items'];
            error_log("[JSON Debug] Found " . count($json_products) . " products in searchResult.items");
        }
        // Path 2: Items in itemStacks
        elseif (isset($json_data['props']['pageProps']['initialData']['searchResult']['itemStacks'][0]['items'])) {
            $json_products = $json_data['props']['pageProps']['initialData']['searchResult']['itemStacks'][0]['items'];
            error_log("[JSON Debug] Found " . count($json_products) . " products in itemStacks[0].items");
        }
        // Path 3: Items in product stacks
        elseif (isset($json_data['props']['pageProps']['initialData']['searchResult']['itemStacks'])) {
            foreach ($json_data['props']['pageProps']['initialData']['searchResult']['itemStacks'] as $stack) {
                if (isset($stack['items'])) {
                    $json_products = array_merge($json_products, $stack['items']);
                }
            }
            error_log("[JSON Debug] Found " . count($json_products) . " products in multiple itemStacks");
        }
        else {
            error_log("[JSON Debug] No products found in expected paths");
        }
        
        if (!empty($json_products)) {
            
            foreach ($json_products as $item) {
                $product = array();
                
                // Extract basic product info
                $product['title'] = isset($item['name']) ? $item['name'] : '';
                $product['price'] = '';
                $product['rating_number'] = '';
                $product['rating_count'] = '';
                $product['image'] = '';
                $product['link'] = '';
                $product['availability'] = '';
                $product['brand'] = isset($item['brand']) ? $item['brand'] : '';
                
                // Extract price
                if (isset($item['priceInfo']['linePrice'])) {
                    $product['price'] = $item['priceInfo']['linePrice'];
                } elseif (isset($item['priceInfo']['linePriceDisplay'])) {
                    $product['price'] = $item['priceInfo']['linePriceDisplay'];
                }
                
                // Extract rating and reviews
                if (isset($item['averageRating'])) {
                    $product['rating_number'] = $item['averageRating'];
                }
                if (isset($item['numberOfReviews'])) {
                    $product['rating_count'] = $item['numberOfReviews'];
                }
                
                // Extract image
                if (isset($item['imageInfo']['thumbnailUrl'])) {
                    $product['image'] = $item['imageInfo']['thumbnailUrl'];
                } elseif (isset($item['imageInfo']['allImages'][0]['url'])) {
                    $product['image'] = $item['imageInfo']['allImages'][0]['url'];
                }
                
                // Extract product URL
                if (isset($item['canonicalUrl'])) {
                    $product['link'] = (strpos($item['canonicalUrl'], 'http') === 0) ? 
                        $item['canonicalUrl'] : 
                        'https://www.walmart.ca' . $item['canonicalUrl'];
                }
                
                // Extract availability
                if (isset($item['fulfillmentBadge'])) {
                    $product['availability'] = $item['fulfillmentBadge'];
                }
                
                // Add platform field and star rating
                $product['platform'] = 'walmart';
                
                // Add star rating display if rating exists
                if (!empty($product['rating_number']) && $product['rating_number'] > 0) {
                    $rating_numeric = round(floatval($product['rating_number']), 1); // round to 1 decimal
                    $product['rating_numeric'] = $rating_numeric;
                    $product['rating'] = str_repeat('★', round($rating_numeric)) . str_repeat('☆', 5 - round($rating_numeric));
                }
                // Add price_value for sorting
                if (!empty($product['price'])) {
                    if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $product['price'], $m)) {
                        $product['price_value'] = floatval($m[1]);
                    } else {
                        $product['price_value'] = 0;
                    }
                } else {
                    $product['price_value'] = 0;
                }
                
                // Only add if we have at least title and price
                if (!empty($product['title']) && !empty($product['price'])) {
                    $products[] = $product;
                }
            }
        }
    } else {
        // No __NEXT_DATA__ script found
        error_log("[JSON Debug] No __NEXT_DATA__ script found in HTML");
    }
    
    return $products;
}

/**
 * Extract pagination URLs for pages 2 and 3 from Walmart search results
 * @param string $html The Walmart search results HTML
 * @param string $country Country code ('us' or 'ca')
 * @return array Pagination URLs (e.g., ['page_2' => '...', 'page_3' => '...'])
 */
function ps_extract_walmart_pagination_urls($html, $country = 'us') {
    $pagination_urls = array();
    if (empty($html)) return $pagination_urls;

    // Walmart uses hrefs like ...&page=2 and ...&page=3
    for ($page_num = 2; $page_num <= 3; $page_num++) {
        if (preg_match('/href="([^"]*?[&?]page=' . $page_num . '[^"]*)"[^>]*>\s*' . $page_num . '\s*<\/a>/i', $html, $m)) {
            $url = html_entity_decode($m[1]);
            // Add domain if needed
            if (strpos($url, 'http') !== 0) {
                $base = ($country === 'ca') ? 'https://www.walmart.ca' : 'https://www.walmart.com';
                $url = $base . $url;
            }
            $pagination_urls['page_' . $page_num] = $url;
        }
    }
    return $pagination_urls;
}

/**
 * Construct Walmart search URL
 */
function ps_construct_walmart_search_url($query, $country = 'us', $page = 1) {
    if ($country === 'ca') {
        $base_url = 'https://www.walmart.ca/search?q=' . urlencode($query);
    } else {
        $base_url = 'https://www.walmart.com/search?q=' . urlencode($query);
    }
    
    if ($page > 1) {
        $base_url .= '&page=' . $page;
    }
    
    return $base_url;
}

/**
 * Fetch Walmart search results
 */
function ps_fetch_walmart_search_results($url, $country = 'us') {
    $args = array(
        'timeout' => 30,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    );

    // Try direct request first
    $response = wp_remote_get($url, $args);
    $body = !is_wp_error($response) ? wp_remote_retrieve_body($response) : '';
    $http_code = !is_wp_error($response) ? wp_remote_retrieve_response_code($response) : 0;

    $blocked_codes = array(403, 429, 503, 502, 504);
    $is_blocked_html = false;
    $block_detected = '';
    
    if (!empty($body)) {
        $block_indicators = [
            'Verify Your Identity',
            'We like real shoppers, not robots!',
            'px-captcha',
            'Bot Protection Page',
            'Please press and hold the button below to verify yourself',
            'Access Denied',
            'Security Check',
            'Please verify you are a human',
            'Cloudflare',
            'DDoS protection',
            'Rate limit exceeded',
            'Too many requests',
            'Please wait while we verify',
            'Checking your browser',
            'Just a moment',
            'Security verification',
            'Human verification',
            'Captcha',
            'reCAPTCHA',
            'hCaptcha',
            'Please complete the security check',
            'Your request has been blocked',
            'Access blocked',
            'Suspicious activity detected',
            'Please try again later',
            'Service temporarily unavailable',
            'Maintenance mode',
            'Under maintenance'
        ];
        
        foreach ($block_indicators as $indicator) {
            if (stripos($body, $indicator) !== false) {
                $is_blocked_html = true;
                $block_detected = $indicator;
                break;
            }
        }
        
        // Additional checks for common bot detection patterns
        if (!$is_blocked_html) {
            // Check for empty or minimal content that might indicate blocking
            if (strlen($body) < 1000 && (stripos($body, 'walmart') === false || stripos($body, 'search') === false)) {
                $is_blocked_html = true;
                $block_detected = 'Minimal content response';
            }
            
            // Check for JSON error responses
            if (stripos($body, '"error"') !== false && stripos($body, '"blocked"') !== false) {
                $is_blocked_html = true;
                $block_detected = 'JSON error response';
            }
        }
    }

    $should_retry_proxy = is_wp_error($response) || $http_code === 0 || in_array($http_code, $blocked_codes) || empty($body) || $is_blocked_html;
    
    // Log bot detection for debugging
    if ($is_blocked_html || in_array($http_code, $blocked_codes)) {
        error_log("[Walmart Bot Detection] HTTP Code: {$http_code}, Blocked HTML: " . ($is_blocked_html ? 'YES' : 'NO') . ", Detection: {$block_detected}");
    }

    // If blocked or no response, retry with proxy
    if ($should_retry_proxy && defined('PS_DECODO_PROXY_HOST') && defined('PS_DECODO_PROXY_PORT')) {
        error_log("[Walmart Proxy Retry] HTTP Code: {$http_code}, Blocked: " . ($is_blocked_html ? 'YES' : 'NO') . ", Detection: {$block_detected}");
        
        $proxy_host = PS_DECODO_PROXY_HOST;
        $proxy_port = PS_DECODO_PROXY_PORT;
        $proxy_username = defined('PS_DECODO_USER_BASE') ? PS_DECODO_USER_BASE . '-country-' . $country : '';
        $proxy_password = defined('PS_DECODO_PASSWORD') ? PS_DECODO_PASSWORD : '';

        error_log("[Walmart Proxy Retry] Using proxy: {$proxy_host}:{$proxy_port}, Username: {$proxy_username}");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $args['user-agent']);
        curl_setopt($ch, CURLOPT_PROXY, $proxy_host);
        curl_setopt($ch, CURLOPT_PROXYPORT, $proxy_port);
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        if ($proxy_username && $proxy_password) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxy_username:$proxy_password");
        }
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Cache-Control: max-age=0'
        ]);
        $body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log("[Walmart Proxy Retry] Proxy response - HTTP Code: {$http_code}, Body length: " . strlen($body) . ", Curl error: " . ($curl_error ?: 'None'));
        
        // Check if proxy request was also blocked
        if (!empty($body)) {
            $proxy_blocked = false;
            foreach ($block_indicators as $indicator) {
                if (stripos($body, $indicator) !== false) {
                    $proxy_blocked = true;
                    error_log("[Walmart Proxy Retry] Proxy request also blocked by: {$indicator}");
                    break;
                }
            }
        }
    }

    // Save full HTML to logs/walmart_response_TIMESTAMP.html for debugging
    if (!empty($body)) {
        $log_dir = __DIR__ . '/../logs';
        if (!file_exists($log_dir)) {
            mkdir($log_dir, 0777, true);
        }
        $timestamp = date('Ymd_His');
        $log_file = $log_dir . "/walmart_response_{$timestamp}.html";
        file_put_contents($log_file, $body);
    }

    if ($http_code !== 200 || empty($body)) {
        return array('error' => true, 'http_code' => $http_code);
    }

    // Parse HTML for products (new Walmart.ca structure)
    $dom = new DOMDocument();
    @$dom->loadHTML($body);
    $xpath = new DOMXPath($dom);

    $products = array();
    $seen = array(); // For deduplication by title+price
    
    // Start with JSON extraction first to ensure we get all products
    $json_products = ps_extract_walmart_products_from_json($body);
    $json_products_count = count($json_products);
    
    // Add JSON products first
    foreach ($json_products as $json_product) {
        $dedup_key = strtolower($json_product['title']) . '|' . $json_product['price'];
        if (!isset($seen[$dedup_key])) {
            $products[] = $json_product;
            $seen[$dedup_key] = true;
        }
    }
    
    // Find all product containers by [data-item-id] anywhere in the document
    $product_nodes = $xpath->query('//*[@data-item-id]');
    
    // Also look for products without data-item-id by finding containers with product-title
    $fallback_nodes = $xpath->query('//span[@data-automation-id="product-title"]/ancestor::*[contains(@class, "w-25") or contains(@class, "w-20") or contains(@class, "w-33") or contains(@class, "flex")][1]');
    
    // Merge the two node lists, avoiding duplicates
    $all_nodes = array();
    $node_hashes = array(); // Track nodes to avoid duplicates
    
    // Add all data-item-id nodes
    for ($i = 0; $i < $product_nodes->length; $i++) {
        $node = $product_nodes->item($i);
        $node_hash = spl_object_hash($node);
        $all_nodes[] = $node;
        $node_hashes[$node_hash] = true;
    }
    
    // Add fallback nodes only if they're not already in the list
    $fallback_nodes_added = 0;
    for ($i = 0; $i < $fallback_nodes->length; $i++) {
        $node = $fallback_nodes->item($i);
        $node_hash = spl_object_hash($node);
        if (!isset($node_hashes[$node_hash])) {
            $all_nodes[] = $node;
            $fallback_nodes_added++;
        }
    }
    
    $product_nodes = $all_nodes;
    
    // Extract total results count from page
    $total_results = 0;
    $results_text = $xpath->query('//h2[contains(text(), "Results for")]')->item(0);
    if ($results_text) {
        if (preg_match('/\((\d+)\)/', $results_text->textContent, $matches)) {
            $total_results = intval($matches[1]);
        }
    }
    
    $debug_info = array(
        'total_results_walmart' => $total_results,
        'json_products_found' => $json_products_count,
        'data_item_id_nodes' => $xpath->query('//*[@data-item-id]')->length,
        'fallback_nodes_found' => $fallback_nodes->length,
        'fallback_nodes_added' => $fallback_nodes_added,
        'total_nodes_found' => count($product_nodes),
        'products_with_title' => 0,
        'products_with_link' => 0,
        'products_included' => $json_products_count, // Start with JSON products
        'products_excluded_no_title' => 0,
        'products_excluded_no_link' => 0,
        'products_excluded_duplicate' => 0,
        'unique_product_ids' => array(),
        'duplicate_product_ids' => array(),
        'explanation' => ($total_results > count($product_nodes)) ? 
            "Found {$total_results} total results but only " . count($product_nodes) . " on current page. " . ($total_results - count($product_nodes)) . " products are on other pages." :
            "All {$total_results} results are on the current page."
    );
    
    foreach ($product_nodes as $node) {
        // Title - try multiple selectors
        $title_node = $xpath->query('.//span[@data-automation-id="product-title"]', $node)->item(0);
        if (!$title_node) {
            $title_node = $xpath->query('.//span[contains(@class, "product-title")]', $node)->item(0);
        }
        if (!$title_node) {
            $title_node = $xpath->query('.//a[contains(@href, "/ip/")]', $node)->item(0);
        }
        $title = $title_node ? trim($title_node->textContent) : '';
        if ($title) $debug_info['products_with_title']++;
        
        // Link - try multiple selectors
        $link_node = $xpath->query('.//a[contains(@href, "/ip/")]', $node)->item(0);
        if (!$link_node) {
            $link_node = $xpath->query('.//a[contains(@href, "/product/")]', $node)->item(0);
        }
        $link = $link_node ? $link_node->getAttribute('href') : '';
        if ($link) $debug_info['products_with_link']++;
        
        if ($link && strpos($link, 'http') !== 0) {
            $base = ($country === 'ca') ? 'https://www.walmart.ca' : 'https://www.walmart.com';
            $link = $base . $link;
        }
        
        // Price
        $price = '';
        $price_node = $xpath->query('.//div[@data-automation-id="product-price"]//div | .//div[@data-automation-id="product-price"]//span', $node)->item(0);
        if ($price_node) {
            if (preg_match('/\$[0-9,.]+/', $price_node->textContent, $m)) {
                $price = $m[0];
            } else {
                $price = trim($price_node->textContent);
            }
        }
        
        // Image
        $image = '';
        $img_node = $xpath->query('.//img[@data-testid="productTileImage"]', $node)->item(0);
        if ($img_node) {
            $image = $img_node->getAttribute('src');
            if (!$image && $img_node->hasAttribute('srcset')) {
                $srcset = $img_node->getAttribute('srcset');
                $parts = preg_split('/\s*,\s*/', $srcset);
                if (count($parts) > 0) {
                    $image = preg_replace('/\s+\d+[wx]$/', '', $parts[0]);
                }
            }
        }
        
        // Brand
        $brand = '';
        $brand_node = $xpath->query('.//div[contains(@class, "b") and contains(@class, "f6") and contains(@class, "black")]', $node)->item(0);
        if ($brand_node) {
            $brand = trim($brand_node->textContent);
        }
        
        // Rating number
        $rating_number = '';
        $rating_node = $xpath->query('.//span[@data-testid="product-ratings"]', $node)->item(0);
        if ($rating_node && $rating_node->hasAttribute('data-value')) {
            $rating_number = $rating_node->getAttribute('data-value');
        }
        
        // Rating count
        $rating_count = '';
        $rating_count_node = $xpath->query('.//span[@data-testid="product-reviews"]', $node)->item(0);
        if ($rating_count_node && $rating_count_node->hasAttribute('data-value')) {
            $rating_count = $rating_count_node->getAttribute('data-value');
        }
        
        // Get product ID for deduplication tracking
        $product_id = $node->getAttribute('data-item-id');
        
        // Deduplication key: title + price
        $dedup_key = strtolower($title) . '|' . $price;
        
        // Track exclusion reasons
        if (!$title) {
            $debug_info['products_excluded_no_title']++;
        } else if (!$link) {
            $debug_info['products_excluded_no_link']++;
        } else if (isset($seen[$dedup_key])) {
            $debug_info['products_excluded_duplicate']++;
            if ($product_id) {
                $debug_info['duplicate_product_ids'][] = $product_id;
            }
        } else {
            $product = array(
                'title' => $title,
                'link' => $link,
                'price' => $price,
                'image' => $image,
                'brand' => $brand,
                'rating_number' => $rating_number,
                'rating_count' => $rating_count,
                'platform' => 'walmart',
            );
            
            // Add star rating display if rating exists
            if (!empty($rating_number) && $rating_number > 0) {
                $rating_numeric = round(floatval($rating_number), 1); // round to 1 decimal
                $product['rating_numeric'] = $rating_numeric;
                // Generate star rating display using the same format as Amazon
                $product['rating'] = str_repeat('★', round($rating_numeric)) . str_repeat('☆', 5 - round($rating_numeric));
            }
            // Add price_value for sorting
            if (!empty($price)) {
                if (preg_match('/([0-9]+(?:\.[0-9]+)?)/', $price, $m)) {
                    $product['price_value'] = floatval($m[1]);
                } else {
                    $product['price_value'] = 0;
                }
            } else {
                $product['price_value'] = 0;
            }
            
            $products[] = $product;
            $seen[$dedup_key] = true;
            $debug_info['products_included']++;
            if ($product_id) {
                $debug_info['unique_product_ids'][] = $product_id;
            }
        }
    }

    $debug_info['total_products_final'] = count($products);
    $debug_info['html_products_added'] = $debug_info['products_included'] - $json_products_count;
    
    // Update explanation based on final count
    if (count($products) >= $total_results) {
        $debug_info['explanation'] = "Found all {$total_results} products on current page (" . count($products) . " total with " . $json_products_count . " from JSON and " . $debug_info['html_products_added'] . " additional from HTML).";
    } else {
        $debug_info['explanation'] = "Found {$total_results} total results but extracted " . count($products) . " products. " . ($total_results - count($products)) . " products may be on other pages or have different structure.";
    }

    // Always return the expected structure
    if (count($products) > 0) {
        return array(
            'success' => true,
            'items' => $products,
            'count' => count($products),
            'debug_info' => $debug_info
        );
    } else {
        return array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => 'No products found on Walmart. The page structure may have changed or Walmart is blocking automated access.',
            'debug_info' => $debug_info
        );
    }
} 
