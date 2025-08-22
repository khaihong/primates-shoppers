<?php
/**
 * Block Patterns for Primates Shoppers Landing Page
 * 
 * Block patterns allow users to insert pre-designed layouts with a single click.
 * These patterns can be customized after insertion.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register block patterns
 */
function ps_register_block_patterns() {
    // Only register patterns if WordPress supports them
    if (!function_exists('register_block_pattern') || !function_exists('register_block_pattern_category')) {
        return;
    }
    
    // Register pattern category
    register_block_pattern_category(
        'primates-shoppers',
        array(
            'label'       => __('Primates Shoppers', 'primates-shoppers'),
            'description' => __('Pre-designed layouts for Primates Shoppers landing pages', 'primates-shoppers'),
        )
    );

    // Register the complete landing page pattern
    register_block_pattern(
        'primates-shoppers/complete-landing-page',
        array(
            'title'       => __('Complete Landing Page', 'primates-shoppers'),
            'description' => __('A complete landing page with hero, value proposition, search, and testimonials sections', 'primates-shoppers'),
            'content'     => ps_get_landing_page_pattern_content(),
            'categories'  => array('primates-shoppers', 'page'),
            'keywords'    => array('landing', 'marketing', 'conversion', 'complete'),
            'viewportWidth' => 1200,
        )
    );

    // Register individual section patterns
    register_block_pattern(
        'primates-shoppers/hero-section-pattern',
        array(
            'title'       => __('Hero Section', 'primates-shoppers'),
            'description' => __('Landing page hero with call-to-action', 'primates-shoppers'),
            'content'     => ps_get_hero_section_pattern_content(),
            'categories'  => array('primates-shoppers', 'header'),
            'keywords'    => array('hero', 'header', 'cta', 'banner'),
        )
    );

    register_block_pattern(
        'primates-shoppers/value-proposition-pattern',
        array(
            'title'       => __('Value Proposition', 'primates-shoppers'),
            'description' => __('Three-column benefits section', 'primates-shoppers'),
            'content'     => ps_get_value_proposition_pattern_content(),
            'categories'  => array('primates-shoppers', 'columns'),
            'keywords'    => array('benefits', 'features', 'value', 'columns'),
        )
    );

    register_block_pattern(
        'primates-shoppers/search-section-pattern',
        array(
            'title'       => __('Product Search Section', 'primates-shoppers'),
            'description' => __('Search form with feature highlights', 'primates-shoppers'),
            'content'     => ps_get_search_section_pattern_content(),
            'categories'  => array('primates-shoppers', 'call-to-action'),
            'keywords'    => array('search', 'form', 'products', 'features'),
        )
    );

    register_block_pattern(
        'primates-shoppers/testimonials-pattern',
        array(
            'title'       => __('Customer Testimonials', 'primates-shoppers'),
            'description' => __('Three customer testimonials with quotes', 'primates-shoppers'),
            'content'     => ps_get_testimonials_pattern_content(),
            'categories'  => array('primates-shoppers', 'testimonials'),
            'keywords'    => array('testimonials', 'reviews', 'customers', 'social-proof'),
        )
    );
}

/**
 * Get the complete landing page pattern content
 */
function ps_get_landing_page_pattern_content() {
    return '<!-- wp:primates-shoppers/hero-section {"title":"Shop Smart, Share Rewards, Support Causes","subtitle":"The first platform that democratizes shopping rewards - every purchase you make generates revenue that\'s shared with you and donated to charities you care about.","ctaText":"Start Shopping \u0026 Earning","ctaUrl":"#search-products","subtext":"Search millions of products from Amazon, eBay \u0026 Walmart","backgroundColor":"linear-gradient(135deg, #667eea 0%, #764ba2 100%)","textColor":"#ffffff"} -->
<div class="wp-block-primates-shoppers-hero-section ps-hero-section" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#ffffff"><div class="ps-hero-content"><h1 class="ps-hero-title">Shop Smart, Share Rewards, Support Causes</h1><p class="ps-hero-subtitle">The first platform that democratizes shopping rewards - every purchase you make generates revenue that\'s shared with you and donated to charities you care about.</p><div class="ps-hero-cta"><a href="#search-products" class="ps-cta-button ps-cta-primary" id="ps-hero-cta">Start Shopping &amp; Earning</a><p class="ps-hero-subtext">Search millions of products from Amazon, eBay &amp; Walmart</p></div></div><div class="ps-hero-visual"><div class="ps-platform-logos"><div class="ps-platform-logo"><img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjRkY5OTAwIi8+Cjwvc3ZnPgo=" alt="Amazon" class="ps-logo-amazon"/></div><div class="ps-platform-logo"><img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjMDA0Q0Q3Ii8+Cjwvc3ZnPgo=" alt="eBay" class="ps-logo-ebay"/></div><div class="ps-platform-logo"><img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjMDA0Njg1Ii8+Cjwvc3ZnPgo=" alt="Walmart" class="ps-logo-walmart"/></div></div></div></div>
<!-- /wp:primates-shoppers/hero-section -->

<!-- wp:primates-shoppers/value-proposition {"sectionTitle":"Revolutionary Revenue Sharing","card1Icon":"💰","card1Title":"Earn From Every Search","card1Description":"Get a share of advertising revenue from every product search you make. Your shopping activity generates real income.","card2Icon":"🤝","card2Title":"Support Charities","card2Description":"Choose which charities receive a portion of the revenue. Make a positive impact while you shop.","card3Icon":"🔍","card3Title":"Smart Product Search","card3Description":"Search across Amazon, eBay, and Walmart simultaneously. Find the best deals with advanced filtering and sorting.","backgroundColor":"#f8f9fa"} -->
<div class="wp-block-primates-shoppers-value-proposition ps-value-section" style="background-color:#f8f9fa"><div class="ps-container"><h2 class="ps-section-title">Revolutionary Revenue Sharing</h2><div class="ps-value-grid"><div class="ps-value-card"><div class="ps-value-icon">💰</div><h3>Earn From Every Search</h3><p>Get a share of advertising revenue from every product search you make. Your shopping activity generates real income.</p></div><div class="ps-value-card"><div class="ps-value-icon">🤝</div><h3>Support Charities</h3><p>Choose which charities receive a portion of the revenue. Make a positive impact while you shop.</p></div><div class="ps-value-card"><div class="ps-value-icon">🔍</div><h3>Smart Product Search</h3><p>Search across Amazon, eBay, and Walmart simultaneously. Find the best deals with advanced filtering and sorting.</p></div></div></div></div>
<!-- /wp:primates-shoppers/value-proposition -->

<!-- wp:primates-shoppers/search-section {"sectionTitle":"Start Your Rewarding Shopping Journey","sectionSubtitle":"Search millions of products and start earning rewards instantly","anchorId":"search-products","feature2Icon":"🎯","feature2Text":"Advanced filtering options","feature3Icon":"📊","feature3Text":"Price per unit sorting","backgroundColor":"#ffffff"} -->
<div class="wp-block-primates-shoppers-search-section ps-search-section" id="search-products" style="background-color:#ffffff;"><div class="ps-container"><h2 class="ps-section-title">Start Your Rewarding Shopping Journey</h2><p class="ps-section-subtitle">Search millions of products and start earning rewards instantly</p><div class="ps-search-features"><div class="ps-feature"><span class="ps-feature-icon">🎯</span><span>Advanced filtering options</span></div><div class="ps-feature"><span class="ps-feature-icon">📊</span><span>Price per unit sorting</span></div></div><div class="ps-search-form-container"></div></div></div>
<!-- /wp:primates-shoppers/search-section -->

<!-- wp:primates-shoppers/testimonials {"sectionTitle":"What Our Users Say","testimonial1Content":"Finally, a platform that shares the wealth! I\'ve earned over $200 while supporting my favorite environmental charity.","testimonial1Author":"Sarah M.","testimonial1Role":"Active Shopper","testimonial2Content":"The search functionality is amazing - I can compare prices across all major platforms in one place and earn money doing it.","testimonial2Author":"Mike R.","testimonial2Role":"Deal Hunter","testimonial3Content":"Love that I can contribute to charity just by shopping. It\'s a win-win-win situation for everyone involved.","testimonial3Author":"Lisa K.","testimonial3Role":"Charity Supporter","backgroundColor":"#f8f9fa"} -->
<div class="wp-block-primates-shoppers-testimonials ps-testimonials" style="background-color:#f8f9fa"><div class="ps-container"><h2 class="ps-section-title">What Our Users Say</h2><div class="ps-testimonials-grid"><div class="ps-testimonial"><div class="ps-testimonial-content">"Finally, a platform that shares the wealth! I\'ve earned over $200 while supporting my favorite environmental charity."</div><div class="ps-testimonial-author"><strong>Sarah M.</strong> - Active Shopper</div></div><div class="ps-testimonial"><div class="ps-testimonial-content">"The search functionality is amazing - I can compare prices across all major platforms in one place and earn money doing it."</div><div class="ps-testimonial-author"><strong>Mike R.</strong> - Deal Hunter</div></div><div class="ps-testimonial"><div class="ps-testimonial-content">"Love that I can contribute to charity just by shopping. It\'s a win-win-win situation for everyone involved."</div><div class="ps-testimonial-author"><strong>Lisa K.</strong> - Charity Supporter</div></div></div></div></div>
<!-- /wp:primates-shoppers/testimonials -->';
}

/**
 * Get individual section pattern contents
 */
function ps_get_hero_section_pattern_content() {
    return '<!-- wp:primates-shoppers/hero-section {"title":"Shop Smart, Share Rewards, Support Causes","subtitle":"The first platform that democratizes shopping rewards - every purchase you make generates revenue that\'s shared with you and donated to charities you care about.","ctaText":"Start Shopping \u0026 Earning","ctaUrl":"#search-products","subtext":"Search millions of products from Amazon, eBay \u0026 Walmart","backgroundColor":"linear-gradient(135deg, #667eea 0%, #764ba2 100%)","textColor":"#ffffff"} /-->';
}

function ps_get_value_proposition_pattern_content() {
    return '<!-- wp:primates-shoppers/value-proposition {"sectionTitle":"Revolutionary Revenue Sharing","card1Icon":"💰","card1Title":"Earn From Every Search","card1Description":"Get a share of advertising revenue from every product search you make. Your shopping activity generates real income.","card2Icon":"🤝","card2Title":"Support Charities","card2Description":"Choose which charities receive a portion of the revenue. Make a positive impact while you shop.","card3Icon":"🔍","card3Title":"Smart Product Search","card3Description":"Search across Amazon, eBay, and Walmart simultaneously. Find the best deals with advanced filtering and sorting.","backgroundColor":"#f8f9fa"} /-->';
}

function ps_get_search_section_pattern_content() {
    return '<!-- wp:primates-shoppers/search-section {"sectionTitle":"Start Your Rewarding Shopping Journey","sectionSubtitle":"Search millions of products and start earning rewards instantly","anchorId":"search-products","feature2Icon":"🎯","feature2Text":"Advanced filtering options","feature3Icon":"📊","feature3Text":"Price per unit sorting","backgroundColor":"#ffffff"} /-->';
}

function ps_get_testimonials_pattern_content() {
    return '<!-- wp:primates-shoppers/testimonials {"sectionTitle":"What Our Users Say","testimonial1Content":"Finally, a platform that shares the wealth! I\'ve earned over $200 while supporting my favorite environmental charity.","testimonial1Author":"Sarah M.","testimonial1Role":"Active Shopper","testimonial2Content":"The search functionality is amazing - I can compare prices across all major platforms in one place and earn money doing it.","testimonial2Author":"Mike R.","testimonial2Role":"Deal Hunter","testimonial3Content":"Love that I can contribute to charity just by shopping. It\'s a win-win-win situation for everyone involved.","testimonial3Author":"Lisa K.","testimonial3Role":"Charity Supporter","backgroundColor":"#f8f9fa"} /-->';
}

// Initialize patterns
add_action('init', 'ps_register_block_patterns');
?>