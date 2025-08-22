<?php
/**
 * SEO Meta Tags and Structured Data for Primates Shoppers Landing Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Check if the current page has landing page blocks
 */
function ps_has_landing_page_blocks() {
    if (!is_page()) {
        return false;
    }
    
    $post = get_post();
    if (!$post) {
        return false;
    }
    
    // Check for any of our landing page blocks
    return (
        has_block('primates-shoppers/hero-section', $post) ||
        has_block('primates-shoppers/value-proposition', $post) ||
        has_block('primates-shoppers/search-section', $post) ||
        has_block('primates-shoppers/testimonials', $post)
    );
}

/**
 * Add SEO meta tags for the landing page
 */
function ps_add_landing_page_meta_tags() {
    if (is_page() && (has_shortcode(get_post()->post_content, 'primates_landing_page') || ps_has_landing_page_blocks())) {
        ?>
        <!-- SEO Meta Tags for Primates Shoppers Landing Page -->
        <meta name="description" content="Revolutionary shopping platform that democratizes advertising revenue. Search products from Amazon, eBay & Walmart while earning rewards and supporting charities. Start shopping and earning today!">
        <meta name="keywords" content="shopping rewards, affiliate marketing, charity donations, product search, Amazon, eBay, Walmart, price comparison, democratize revenue, ethical shopping">
        <meta name="author" content="Primates Shoppers">
        <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
        
        <!-- Open Graph Meta Tags -->
        <meta property="og:title" content="Shop Smart, Share Rewards, Support Causes - Primates Shoppers">
        <meta property="og:description" content="The first platform that democratizes shopping rewards. Search millions of products from top retailers while earning money and supporting charities.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="<?php echo esc_url(get_permalink()); ?>">
        <meta property="og:site_name" content="Primates Shoppers">
        <meta property="og:locale" content="en_US">
        
        <!-- Twitter Card Meta Tags -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Shop Smart, Share Rewards, Support Causes - Primates Shoppers">
        <meta name="twitter:description" content="Revolutionary shopping platform that shares advertising revenue with users and charities. Search Amazon, eBay & Walmart products.">
        <meta name="twitter:creator" content="@primateshoppers">
        
        <!-- Additional SEO Meta Tags -->
        <meta name="theme-color" content="#667eea">
        <meta name="msapplication-TileColor" content="#667eea">
        <link rel="canonical" href="<?php echo esc_url(get_permalink()); ?>">
        
        <!-- Structured Data for Organization -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": "Primates Shoppers",
            "description": "Revolutionary shopping platform that democratizes advertising and affiliate marketing revenue to users and charities",
            "url": "<?php echo esc_url(home_url()); ?>",
            "logo": "<?php echo esc_url(home_url()); ?>/wp-content/uploads/primates-shoppers-logo.png",
            "sameAs": [
                "https://facebook.com/primateshoppers",
                "https://twitter.com/primateshoppers",
                "https://instagram.com/primateshoppers"
            ],
            "foundingDate": "2024",
            "founders": [
                {
                    "@type": "Person",
                    "name": "Primates Shoppers Team"
                }
            ],
            "address": {
                "@type": "PostalAddress",
                "addressCountry": "US"
            }
        }
        </script>
        
        <!-- Structured Data for Website -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": "Primates Shoppers",
            "description": "Shop smart, share rewards, and support causes with our revolutionary revenue-sharing platform",
            "url": "<?php echo esc_url(home_url()); ?>",
            "potentialAction": {
                "@type": "SearchAction",
                "target": {
                    "@type": "EntryPoint",
                    "urlTemplate": "<?php echo esc_url(home_url()); ?>/?s={search_term_string}"
                },
                "query-input": "required name=search_term_string"
            }
        }
        </script>
        
        <!-- Structured Data for Service -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "Service",
            "name": "Primates Shoppers Product Search",
            "description": "Advanced product search across Amazon, eBay, and Walmart with revenue sharing for users and charities",
            "provider": {
                "@type": "Organization",
                "name": "Primates Shoppers"
            },
            "serviceType": "Product Search and Revenue Sharing Platform",
            "areaServed": "Worldwide",
            "hasOfferCatalog": {
                "@type": "OfferCatalog",
                "name": "Product Search Services",
                "itemListElement": [
                    {
                        "@type": "Offer",
                        "itemOffered": {
                            "@type": "Service",
                            "name": "Amazon Product Search",
                            "description": "Search and compare Amazon products with revenue sharing"
                        }
                    },
                    {
                        "@type": "Offer",
                        "itemOffered": {
                            "@type": "Service",
                            "name": "eBay Product Search",
                            "description": "Search and compare eBay products with revenue sharing"
                        }
                    },
                    {
                        "@type": "Offer",
                        "itemOffered": {
                            "@type": "Service",
                            "name": "Walmart Product Search",
                            "description": "Search and compare Walmart products with revenue sharing"
                        }
                    }
                ]
            }
        }
        </script>
        
        <!-- Structured Data for FAQ -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "FAQPage",
            "mainEntity": [
                {
                    "@type": "Question",
                    "name": "How does revenue sharing work?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "When you search for products on our platform, we generate advertising and affiliate revenue from the retailers. We then democratically share a portion of this revenue with you and donate another portion to charities you choose to support."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Which platforms can I search?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "You can search products from Amazon, eBay, and Walmart all in one place. Our advanced search allows you to compare prices and features across all three platforms simultaneously."
                    }
                },
                {
                    "@type": "Question",
                    "name": "How much revenue do I earn?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Revenue sharing varies based on search activity and engagement. A portion of advertising and affiliate revenue is democratically distributed among active users, with another portion going to charity."
                    }
                },
                {
                    "@type": "Question",
                    "name": "Is the service free to use?",
                    "acceptedAnswer": {
                        "@type": "Answer",
                        "text": "Yes, our product search service is completely free to use. You earn money by using our platform, rather than paying for it."
                    }
                }
            ]
        }
        </script>
        
        <!-- Structured Data for BreadcrumbList -->
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": "Home",
                    "item": "<?php echo esc_url(home_url()); ?>"
                },
                {
                    "@type": "ListItem",
                    "position": 2,
                    "name": "Shop Smart & Share Rewards",
                    "item": "<?php echo esc_url(get_permalink()); ?>"
                }
            ]
        }
        </script>
        <?php
    }
}
add_action('wp_head', 'ps_add_landing_page_meta_tags');

/**
 * Add additional performance and SEO optimizations
 */
function ps_landing_page_optimizations() {
    if (is_page() && (has_shortcode(get_post()->post_content, 'primates_landing_page') || ps_has_landing_page_blocks())) {
        ?>
        <!-- Preload critical resources -->
        <link rel="preload" href="<?php echo get_template_directory_uri(); ?>/fonts/primary-font.woff2" as="font" type="font/woff2" crossorigin>
        
        <!-- DNS prefetch for external resources -->
        <link rel="dns-prefetch" href="//fonts.googleapis.com">
        <link rel="dns-prefetch" href="//www.google-analytics.com">
        <link rel="dns-prefetch" href="//connect.facebook.net">
        
        <!-- Preconnect to critical third-party domains -->
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        
        <!-- Critical CSS inline (for above-the-fold content) -->
        <style>
        .ps-landing-page { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .ps-hero-section { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 80px 20px; text-align: center; }
        .ps-hero-title { font-size: 3.5rem; font-weight: 700; margin-bottom: 1rem; line-height: 1.2; }
        @media (max-width: 768px) { .ps-hero-title { font-size: 2.5rem; } }
        </style>
        <?php
    }
}
add_action('wp_head', 'ps_landing_page_optimizations', 1);

/**
 * Add Google Analytics tracking for the landing page
 */
function ps_add_analytics_tracking() {
    if (is_page() && (has_shortcode(get_post()->post_content, 'primates_landing_page') || ps_has_landing_page_blocks())) {
        ?>
        <!-- Google Analytics 4 -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID"></script>
        <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'GA_MEASUREMENT_ID', {
            page_title: 'Primates Shoppers Landing Page',
            page_location: window.location.href,
            custom_map: {
                'custom_parameter_1': 'landing_page_view'
            }
        });
        
        // Track landing page view
        gtag('event', 'page_view', {
            event_category: 'Landing Page',
            event_label: 'Initial Load',
            value: 1
        });
        </script>
        
        <!-- Facebook Pixel -->
        <script>
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        
        fbq('init', 'YOUR_PIXEL_ID');
        fbq('track', 'PageView');
        fbq('track', 'ViewContent', {
            content_name: 'Landing Page',
            content_category: 'Shopping Platform'
        });
        </script>
        <noscript>
        <img height="1" width="1" style="display:none" 
        src="https://www.facebook.com/tr?id=YOUR_PIXEL_ID&ev=PageView&noscript=1"/>
        </noscript>
        <?php
    }
}
add_action('wp_head', 'ps_add_analytics_tracking');

/**
 * Add hreflang tags for international SEO (if applicable)
 */
function ps_add_hreflang_tags() {
    if (is_page() && (has_shortcode(get_post()->post_content, 'primates_landing_page') || ps_has_landing_page_blocks())) {
        ?>
        <link rel="alternate" hreflang="en" href="<?php echo esc_url(get_permalink()); ?>">
        <link rel="alternate" hreflang="en-us" href="<?php echo esc_url(get_permalink()); ?>">
        <link rel="alternate" hreflang="x-default" href="<?php echo esc_url(get_permalink()); ?>">
        <?php
    }
}
add_action('wp_head', 'ps_add_hreflang_tags'); 