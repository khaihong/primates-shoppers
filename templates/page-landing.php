<?php
/**
 * Template Name: Primates Shoppers Landing Page
 * 
 * This template creates a complete landing page with all blocks pre-configured.
 * Users can select this template when creating a new page.
 */

get_header(); ?>

<div class="ps-landing-page-template">
    <?php
    // Check if we have post content or if we should use the default blocks
    if (have_posts()) :
        while (have_posts()) : the_post();
            
            // If the page has custom content, display it
            if (trim(get_the_content())) {
                the_content();
            } else {
                // Otherwise, display the default landing page blocks
                echo ps_get_default_landing_page_content();
            }
            
        endwhile;
    else :
        // Fallback: display default landing page content
        echo ps_get_default_landing_page_content();
    endif;
    ?>
</div>

<?php get_footer(); ?>

<?php
/**
 * Get the default landing page content with all blocks
 */
function ps_get_default_landing_page_content() {
    return '
    <!-- Hero Section -->
    <div class="wp-block-primates-shoppers-hero-section ps-hero-section" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#ffffff">
        <div class="ps-hero-content">
            <h1 class="ps-hero-title">Shop Smart, Share Rewards, Support Causes</h1>
            <p class="ps-hero-subtitle">The first platform that democratizes shopping rewards - every purchase you make generates revenue that\'s shared with you and donated to charities you care about.</p>
            <div class="ps-hero-cta">
                <a href="#search-products" class="ps-cta-button ps-cta-primary" id="ps-hero-cta">Start Shopping &amp; Earning</a>
                <p class="ps-hero-subtext">Search millions of products from Amazon, eBay &amp; Walmart</p>
            </div>
        </div>
        <div class="ps-hero-visual">
            <div class="ps-platform-logos">
                <div class="ps-platform-logo">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjRkY5OTAwIi8+Cjwvc3ZnPgo=" alt="Amazon" class="ps-logo-amazon" style="height: 120px;"/>
                </div>
                <div class="ps-platform-logo">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjMDA0Q0Q3Ii8+Cjwvc3ZnPgo=" alt="eBay" class="ps-logo-ebay" style="height: 120px;"/>
                </div>
                <div class="ps-platform-logo">
                    <img src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjMwIiB2aWV3Qm94PSIwIDAgMTAwIDMwIiBmaWxsPSJub25lIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciPgo8cGF0aCBkPSJNMjAgMTVMMjUgMjBIMzVMMzAgMTVIMjVMMjAgMTBIMTVMMjAgMTVaIiBmaWxsPSIjMDA0Njg1Ci8+Cjwvc3ZnPgo=" alt="Walmart" class="ps-logo-walmart" style="height: 120px;"/>
                </div>
            </div>
        </div>
    </div>

    <!-- Value Proposition Section -->
    <div class="wp-block-primates-shoppers-value-proposition ps-value-section" style="background-color:#f8f9fa">
        <div class="ps-container">
            <h2 class="ps-section-title">Revolutionary Revenue Sharing</h2>
            <div class="ps-value-grid">
                <div class="ps-value-card">
                    <div class="ps-value-icon">💰</div>
                    <h3>Earn From Every Search</h3>
                    <p>Get a share of advertising revenue from every product search you make. Your shopping activity generates real income.</p>
                </div>
                <div class="ps-value-card">
                    <div class="ps-value-icon">🤝</div>
                    <h3>Support Charities</h3>
                    <p>Choose which charities receive a portion of the revenue. Make a positive impact while you shop.</p>
                </div>
                <div class="ps-value-card">
                    <div class="ps-value-icon">🔍</div>
                    <h3>Smart Product Search</h3>
                    <p>Search across Amazon, eBay, and Walmart simultaneously. Find the best deals with advanced filtering and sorting.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="wp-block-primates-shoppers-search-section ps-search-section" id="search-products" style="background-color:#ffffff;">
        <div class="ps-container">
            <h2 class="ps-section-title">Start Your Rewarding Shopping Journey</h2>
            <p class="ps-section-subtitle">Search millions of products and start earning rewards instantly</p>
            
            <!-- Embedded Search Form -->
            <div class="ps-search-form-container">
                ' . do_shortcode('[primates_shoppers]') . '
            </div>

            <!-- Features below search -->
            <div class="ps-search-features">
                <div class="ps-feature">
                    <span class="ps-feature-icon">⚡</span>
                    <span>Real-time price comparison</span>
                </div>
                <div class="ps-feature">
                    <span class="ps-feature-icon">🎯</span>
                    <span>Advanced filtering options</span>
                </div>
                <div class="ps-feature">
                    <span class="ps-feature-icon">📊</span>
                    <span>Price per unit sorting</span>
                </div>
                <div class="ps-feature">
                    <span class="ps-feature-icon">🏷️</span>
                    <span>Best deal detection</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials Section -->
    <div class="wp-block-primates-shoppers-testimonials ps-testimonials" style="background-color:#f8f9fa">
        <div class="ps-container">
            <h2 class="ps-section-title">What Our Users Say</h2>
            <div class="ps-testimonials-grid">
                <div class="ps-testimonial">
                    <div class="ps-testimonial-content">
                        "Finally, a platform that shares the wealth! I\'ve earned over $200 while supporting my favorite environmental charity."
                    </div>
                    <div class="ps-testimonial-author">
                        <strong>Sarah M.</strong> - Active Shopper
                    </div>
                </div>
                <div class="ps-testimonial">
                    <div class="ps-testimonial-content">
                        "The search functionality is amazing - I can compare prices across all major platforms in one place and earn money doing it."
                    </div>
                    <div class="ps-testimonial-author">
                        <strong>Mike R.</strong> - Deal Hunter
                    </div>
                </div>
                <div class="ps-testimonial">
                    <div class="ps-testimonial-content">
                        "Love that I can contribute to charity just by shopping. It\'s a win-win-win situation for everyone involved."
                    </div>
                    <div class="ps-testimonial-author">
                        <strong>Lisa K.</strong> - Charity Supporter
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Sharing Explanation Section -->
    <div class="ps-revenue-explanation" style="padding: 80px 20px; background: #f8f9ff;">
        <div class="ps-container" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
            <h2 class="ps-section-title" style="text-align: center; font-size: 2.5rem; font-weight: 700; margin-bottom: 3rem; color: #2c3e50;">How Our Revenue Sharing Works</h2>
            <div class="ps-explanation-content" style="max-width: 800px; margin: 0 auto;">
                <p class="ps-explanation-text" style="font-size: 1.1rem; line-height: 1.7; margin-bottom: 1.5rem; color: #2c3e50; text-align: center;">
                    At no extra cost to you
                    <span class="ps-info-popup" style="position: relative; display: inline-block; cursor: help;">
                        <span class="ps-info-icon" style="display: inline-block; width: 16px; height: 16px; background: #667eea; color: white; border-radius: 50%; text-align: center; line-height: 16px; font-size: 10px; font-weight: bold; margin-left: 5px; cursor: pointer; transition: background-color 0.3s ease;">i</span>
                        <div class="ps-popup-content" style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #2c3e50; color: white; padding: 15px; border-radius: 8px; font-size: 14px; line-height: 1.4; max-width: 300px; width: max-content; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 1000; margin-bottom: 10px;">
                            Most large online retailers pay commissions to sites like this one for directing sales to them. But instead of pocketing the money, we will donate 80% of it to organizations that our users choose to support.<br><br>
                            We would actually love to give 80% back to our users, but most affiliate contracts forbid doing so. Booo! So the next best thing is to support worthy causes, as decided by our users.
                        </div>
                    </span>
                    , your shopping activity generates revenue that we share with charities you choose to support.
                </p>
                <p class="ps-explanation-text" style="font-size: 1.1rem; line-height: 1.7; margin-bottom: 1.5rem; color: #2c3e50; text-align: center;">
                    Instead of making the rich richer
                    <span class="ps-info-popup" style="position: relative; display: inline-block; cursor: help;">
                        <span class="ps-info-icon" style="display: inline-block; width: 16px; height: 16px; background: #667eea; color: white; border-radius: 50%; text-align: center; line-height: 16px; font-size: 10px; font-weight: bold; margin-left: 5px; cursor: pointer; transition: background-color 0.3s ease;">i</span>
                        <div class="ps-popup-content" style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: #2c3e50; color: white; padding: 15px; border-radius: 8px; font-size: 14px; line-height: 1.4; max-width: 300px; width: max-content; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); opacity: 0; visibility: hidden; transition: all 0.3s ease; z-index: 1000; margin-bottom: 10px;">
                            And that\'s just the beginning. As our community grows, we will soon be able to charge for advertising on this site, also. And then 80% of that revenue WILL go directly to our users who create content that attracts the advertising.
                        </div>
                    </span>
                    , we\'re democratizing the wealth by sharing it with causes that matter to our community.
                </p>
            </div>
        </div>
    </div>

    <style>
    /* Info Popup Styles */
    .ps-info-popup .ps-info-icon:hover {
        background: #5a6fd8 !important;
    }
    
    .ps-info-popup .ps-popup-content::after {
        content: \'\';
        position: absolute;
        top: 100%;
        left: 50%;
        transform: translateX(-50%);
        border: 8px solid transparent;
        border-top-color: #2c3e50;
    }
    
    .ps-info-popup:hover .ps-popup-content {
        opacity: 1 !important;
        visibility: visible !important;
    }
    </style>';
}
?> 