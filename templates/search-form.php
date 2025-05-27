<?php
/**
 * Primates Shoppers search form template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Try to detect the user's country from server side
function ps_detect_user_country() {
    $country = 'us'; // Default to US
    
    // Check if the country is in the $_SERVER variables
    $ip_address = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $_SERVER['REMOTE_ADDR'];
    
    // Try to use server headers if available (e.g., Cloudflare, etc.)
    if (isset($_SERVER['HTTP_CF_IPCOUNTRY']) && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        $detected_country = strtolower($_SERVER['HTTP_CF_IPCOUNTRY']);
        if ($detected_country === 'ca') {
            $country = 'ca';
        }
    }
    // Otherwise, check Accept-Language header
    else if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($langs as $lang) {
            if (strpos(strtolower($lang), 'en-ca') !== false || strpos(strtolower($lang), 'fr-ca') !== false) {
                $country = 'ca';
                break;
            }
        }
    }
    
    return $country;
}

// Get detected country
$detected_country = ps_detect_user_country();

// Check if we have cached data for this user
$user_has_cached_data = false;
$user_id = ps_get_user_identifier();
global $wpdb;
$table_name = $wpdb->prefix . 'ps_cache';
$has_cache_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
        WHERE query_data LIKE %s AND expires_at > NOW()",
        '%"user_id":"' . $user_id . '"%'
    )
);
$user_has_cached_data = !empty($has_cache_count) && $has_cache_count > 0;
?>
<div class="ps-search-container">
    <form id="ps-search-form" class="ps-search-form">
        <div class="ps-search-row">
            <div class="ps-country-selector">
                <div class="ps-country-options">
                    <label class="ps-country-option">
                        <input type="radio" name="country" value="us" <?php echo ($detected_country === 'us') ? 'checked' : ''; ?>>
                        <span class="ps-country-flag">🇺🇸</span>
                    </label>
                    <label class="ps-country-option">
                        <input type="radio" name="country" value="ca" <?php echo ($detected_country === 'ca') ? 'checked' : ''; ?>>
                        <span class="ps-country-flag">🇨🇦</span>
                    </label>
                </div>
            </div>
        </div>
        <table class="ps-search-table">
            <tr>
                <td class="ps-search-label">
                    <label for="ps-search-query">Search:</label>
                </td>
                <td class="ps-search-input">
                    <input type="text" id="ps-search-query" name="query" placeholder="Enter search keywords...">
                </td>
                <td class="ps-search-label">
                    <label for="ps-sort-by">Sort:</label>
                </td>
                <td class="ps-search-input">
                    <select id="ps-sort-by" name="sort_by">
                        <option value="price">Price (low to high)</option>
                        <option value="price_per_unit">Price per unit (low to high)</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td class="ps-search-label">
                    <label for="ps-exclude-keywords">Exclude:</label>
                </td>
                <td class="ps-search-input">
                    <input type="text" id="ps-exclude-keywords" name="exclude" placeholder="Keywords to exclude...">
                </td>
                <td class="ps-search-label">
                    <label for="ps-min-rating">Rating:</label>
                </td>
                <td class="ps-search-input">
                    <select id="ps-min-rating" name="min_rating">
                        <option value="4.5">4.5+ stars</option>
                        <option value="4.0" selected>4.0+ stars</option>
                        <option value="3.5">3.5+ stars</option>
                    </select>
                </td>
            </tr>
        </table>
        <div class="ps-search-row">
            <div class="ps-search-actions">
                <button type="button" id="ps-filter-button" class="ps-filter-button" <?php echo !$user_has_cached_data ? 'style="display:none;"' : ''; ?>>Filter Results</button>
                <button type="button" id="ps-show-all-button" class="ps-show-all-button" <?php echo !$user_has_cached_data ? 'style="display:none;"' : ''; ?>>Show All</button>
                <button type="submit" class="ps-search-button">Search Amazon</button>
                <div id="ps-loading" class="ps-loading" style="display: none;">
                    <span class="ps-spinner"></span> <span id="ps-loading-text">Searching...</span>
                </div>
            </div>
        </div>
        <!-- Hidden field to indicate filtering cached results -->
        <input type="hidden" id="ps-filter-cached" name="filter_cached" value="false">
    </form>
    <div id="ps-results-count" class="ps-results-count" style="display: none;"></div>
    <div id="ps-cached-notice" class="ps-cached-notice" style="display: none;">Showing cached results from previous searches. <span class="ps-cached-time"></span></div>
    <div id="ps-results" class="ps-results-grid"></div>
</div>

<script type="text/html" id="ps-product-template">
    <div class="ps-product">
        <div class="ps-product-image-container">
            <a href="{{link}}" target="_blank">
                <img src="{{image}}" alt="{{title}}" class="ps-product-image-tag">
            </a>
        </div>
        <div class="ps-product-info">
            {{#if brand}}
            <div class="ps-product-brand">{{brand}}</div>
            {{/if}}
            <h3 class="ps-product-title">
                <a href="{{link}}" target="_blank">{{title}}</a>
            </h3>
            {{#if rating}}
            <div class="ps-product-rating">
                <a href="{{rating_link}}" target="_blank">
                    {{#if rating_number}}
                    <span class="ps-rating-number">{{rating_number}}</span>
                    {{/if}}
                    <span class="ps-stars">{{rating}}</span>
                    {{#if rating_count}}
                    <span class="ps-rating-count">({{rating_count}})</span>
                    {{/if}}
                </a>
            </div>
            {{else}}
            <div class="ps-rating-spacer"></div>
            {{/if}}
            <div class="ps-product-pricing">
                <div class="ps-product-price">{{price}}</div>
                {{#if price_per_unit}}
                <div class="ps-product-price-unit">({{price_per_unit}})</div>
                {{/if}}
            </div>
            {{#if delivery_time}}
            <div class="ps-delivery-time">{{delivery_time}}</div>
            {{/if}}
        </div>
    </div>
</script>