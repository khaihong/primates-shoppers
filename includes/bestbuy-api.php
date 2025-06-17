<?php
/**
 * Best Buy Scraper integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Search Best Buy products using web scraping
 *
 * @param string $query The main search query
 * @param string $exclude_keywords Keywords to exclude from results
 * @param string $sort_by Sorting method (price, price_per_unit)
 * @param string $country Country code ('us' or 'ca')
 * @param float $min_rating Minimum rating filter (3.5, 4.0, 4.5)
 * @param int $page Page number for pagination (default: 1)
 * @return array Search results
 */
function ps_search_bestbuy_products($query, $exclude_keywords = '', $sort_by = 'price', $country = 'us', $min_rating = 4.0, $page = 1) {
    // If query is empty, return empty array
    if (empty($query)) {
        return array();
    }
    
    ps_log_error("Initiating Best Buy search for: '{$query}' in country: {$country}, page: {$page}");
    
    // Construct the Best Buy search URL with pagination
    $search_url = ps_construct_bestbuy_search_url($query, $country, $page);
    ps_log_error("Constructed Best Buy search URL: {$search_url}");
    
    // Get the search results HTML
    $html_content = ps_fetch_bestbuy_search_results($search_url, $country);
    
    // Check if we got an error response
    if (is_array($html_content) && isset($html_content['error']) && $html_content['error'] === true) {
        $http_code = $html_content['http_code'];
        ps_log_error("Failed to fetch Best Buy search results for query: '{$query}' page {$page} - HTTP {$http_code}");
        
        // Check if it's a blocking error (503, 429, etc.)
        if (in_array($http_code, [503, 429, 403, 502, 504])) {
            // Create Best Buy search URL for the user to continue searching manually
            $bestbuy_base = ($country === 'ca') ? 'https://www.bestbuy.ca' : 'https://www.bestbuy.com';
            $bestbuy_search_url = $bestbuy_base . '/site/searchpage.jsp?st=' . urlencode($query);
            
            return array(
                'success' => false,
                'items' => array(),
                'count' => 0,
                'message' => 'Best Buy is blocking requests. Please try again later.<br><br>Or continue search on <a href="' . $bestbuy_search_url . '" target="_blank" rel="noopener">' . $bestbuy_search_url . '</a>',
                'bestbuy_search_url' => $bestbuy_search_url,
                'search_query' => $query,
                'country' => $country,
                'http_code' => $http_code
            );
        } else {
            // Create Best Buy search URL for the user to continue searching manually
            $bestbuy_base = ($country === 'ca') ? 'https://www.bestbuy.ca' : 'https://www.bestbuy.com';
            $bestbuy_search_url = $bestbuy_base . '/site/searchpage.jsp?st=' . urlencode($query);
            
            return array(
                'success' => false,
                'items' => array(),
                'count' => 0,
                'message' => 'Failed to connect to Best Buy. Please try again later.<br><br>Or continue search on <a href="' . $bestbuy_search_url . '" target="_blank" rel="noopener">' . $bestbuy_search_url . '</a>',
                'bestbuy_search_url' => $bestbuy_search_url,
                'search_query' => $query,
                'country' => $country,
                'http_code' => $http_code
            );
        }
    }
    
    if (empty($html_content)) {
        ps_log_error("Failed to fetch Best Buy search results for query: '{$query}' page {$page} - No response received");
        
        // Create Best Buy search URL for the user to continue searching manually
        $bestbuy_base = ($country === 'ca') ? 'https://www.bestbuy.ca' : 'https://www.bestbuy.com';
        $bestbuy_search_url = $bestbuy_base . '/site/searchpage.jsp?st=' . urlencode($query);
        
        return array(
            'success' => false,
            'items' => array(),
            'count' => 0,
            'message' => 'No response received from Best Buy. Please try again later.<br><br>Or continue search on <a href="' . $bestbuy_search_url . '" target="_blank" rel="noopener">' . $bestbuy_search_url . '</a>',
            'bestbuy_search_url' => $bestbuy_search_url,
            'search_query' => $query,
            'country' => $country
        );
    }
    
    // TODO: Implement Best Buy parsing
    ps_log_error("Best Buy search not yet fully implemented for query: '{$query}'");
    
    return array(
        'success' => false,
        'items' => array(),
        'count' => 0,
        'message' => 'Best Buy search is not yet fully implemented. Please try Amazon or eBay.',
        'search_query' => $query,
        'country' => $country
    );
}

/**
 * Construct Best Buy search URL
 */
function ps_construct_bestbuy_search_url($query, $country = 'us', $page = 1) {
    if ($country === 'ca') {
        $base_url = 'https://www.bestbuy.ca/en-ca/search?search=' . urlencode($query);
    } else {
        $base_url = 'https://www.bestbuy.com/site/searchpage.jsp?st=' . urlencode($query);
    }
    
    if ($page > 1) {
        $base_url .= '&cp=' . $page;
    }
    
    return $base_url;
}

/**
 * Fetch Best Buy search results
 */
function ps_fetch_bestbuy_search_results($url, $country = 'us') {
    // For now, return placeholder implementation
    ps_log_error("Best Buy fetch not yet implemented for URL: {$url}");
    
    return array(
        'error' => true,
        'http_code' => 501 // Not implemented
    );
} 