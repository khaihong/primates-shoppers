<?php
/**
 * Primates Shoppers search form template
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="ps-search-container">
    <form id="ps-search-form" class="ps-search-form">
        <div class="ps-search-row">
            <div class="ps-search-field">
                <label for="ps-search-query">Search Amazon Products</label>
                <input type="text" id="ps-search-query" name="query" placeholder="Enter product name..." required>
            </div>
        </div>
        
        <div class="ps-search-row">
            <div class="ps-search-field">
                <label for="ps-exclude-keywords">Exclude Keywords</label>
                <input type="text" id="ps-exclude-keywords" name="exclude" placeholder="Keywords to exclude">
            </div>
            
            <div class="ps-search-field">
                <label for="ps-sort-by">Sort By</label>
                <select id="ps-sort-by" name="sort_by">
                    <option value="price">Price (Low to High)</option>
                    <option value="price_per_unit">Price Per Unit (Low to High)</option>
                </select>
            </div>
        </div>
        
        <div class="ps-search-row">
            <div class="ps-search-field">
                <button type="submit" class="ps-search-button">Search</button>
            </div>
        </div>
    </form>
    
    <div id="ps-loading" class="ps-loading" style="display: none;">
        <span>Searching...</span>
    </div>
    
    <div id="ps-results-count" class="ps-results-count" style="display: none;"></div>
    <div id="ps-results" class="ps-results"></div>
    
    <template id="ps-product-template">
        <div class="ps-product">
            <div class="ps-product-image">
                <a href="{{link}}" target="_blank" rel="nofollow">
                    <img src="{{image}}" alt="{{title}}">
                </a>
                <a href="{{link}}" class="ps-buy-button" style="font-size: 7px; width: 100%; text-align: center; padding: 3px; display: block;" target="_blank" rel="nofollow">View on Amazon</a>
            </div>
            <div class="ps-product-details">
                {{#if_brand}}
                <div class="ps-product-brand">{{brand}}</div>
                {{/if_brand}}
                <h3 class="ps-product-title">
                    <a href="{{link}}" target="_blank" rel="nofollow">{{title}}</a>
                </h3>
                {{#if_rating}}
                <div class="ps-product-rating">
                    <a href="{{rating_link}}" target="_blank" rel="nofollow">
                        <span class="ps-rating-number">{{rating_number}}</span>
                        <span class="ps-rating-stars">{{rating}}</span>
                        {{#if_rating_count}}
                        <span class="ps-rating-count">({{rating_count}})</span>
                        {{/if_rating_count}}
                    </a>
                </div>
                {{/if_rating}}
                <div class="ps-product-price">
                    <span class="ps-price">${{price}}</span>
                    {{#if_price_per_unit}}
                    <span class="ps-price-per-unit">${{price_per_unit}}{{unit}}</span>
                    {{/if_price_per_unit}}
                </div>
                {{#if_delivery}}
                <div class="ps-delivery-time">
                    <span class="ps-delivery-icon">🚚</span> {{delivery_time}}
                </div>
                {{/if_delivery}}
            </div>
        </div>
    </template>
</div>