<?php
/**
 * Gutenberg Blocks for Primates Shoppers Landing Page
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize blocks with error handling
 */
function ps_blocks_init() {
    try {
        error_log('Primates Shoppers: Initializing blocks system');
        
        // Only proceed if we have the required WordPress functions
        if (!function_exists('add_filter') || !function_exists('add_action')) {
            error_log('Primates Shoppers: WordPress functions not available, skipping blocks init');
            return false;
        }
        
        // Register block category
        add_filter('block_categories_all', 'ps_register_block_category', 10, 2);
        error_log('Primates Shoppers: Block category filter added');
        
        // Register blocks
        add_action('init', 'ps_register_blocks');
        error_log('Primates Shoppers: Block registration action added');
        
        // Enqueue block assets
        add_action('enqueue_block_editor_assets', 'ps_enqueue_block_editor_assets');
        add_action('wp_enqueue_scripts', 'ps_enqueue_block_frontend_assets');
        error_log('Primates Shoppers: Asset enqueue actions added');
        
        error_log('Primates Shoppers: Blocks system initialized successfully');
        return true;
        
    } catch (Exception $e) {
        error_log('Primates Shoppers: Error initializing blocks: ' . $e->getMessage());
        return false;
    }
}

/**
 * Register custom block category with safety checks
 */
function ps_register_block_category($categories, $post) {
    try {
        // Safety check to ensure we're working with an array
        if (!is_array($categories)) {
            error_log('Primates Shoppers: Warning - categories is not an array in ps_register_block_category');
            $categories = array();
        }
        
        // Check if our category already exists to prevent duplicates
        foreach ($categories as $category) {
            if (isset($category['slug']) && $category['slug'] === 'primates-shoppers') {
                error_log('Primates Shoppers: Block category already exists, skipping');
                return $categories;
            }
        }
        
        $new_category = array(
            'slug'  => 'primates-shoppers',
            'title' => __('Primates Shoppers', 'primates-shoppers'),
            'icon'  => 'store',
        );
        
        error_log('Primates Shoppers: Adding block category');
        return array_merge($categories, array($new_category));
        
    } catch (Exception $e) {
        error_log('Primates Shoppers: Error in ps_register_block_category: ' . $e->getMessage());
        return $categories; // Return original categories on error
    }
}

/**
 * Register all blocks
 */
function ps_register_blocks() {
    // Only register blocks if WordPress supports them
    if (!function_exists('register_block_type')) {
        return;
    }
    
    // Register Hero Section block
    if (file_exists(PS_PLUGIN_DIR . 'blocks/hero-section/block.json')) {
        register_block_type(PS_PLUGIN_DIR . 'blocks/hero-section');
    }
    
    // Register Value Proposition block
    if (file_exists(PS_PLUGIN_DIR . 'blocks/value-proposition/block.json')) {
        register_block_type(PS_PLUGIN_DIR . 'blocks/value-proposition');
    }
    
    // Register Search Section block with server-side rendering
    if (file_exists(PS_PLUGIN_DIR . 'blocks/search-section/block.json')) {
        register_block_type(PS_PLUGIN_DIR . 'blocks/search-section', array(
            'render_callback' => 'ps_render_search_section_block'
        ));
    }
    
    // Register Testimonials block
    if (file_exists(PS_PLUGIN_DIR . 'blocks/testimonials/block.json')) {
        register_block_type(PS_PLUGIN_DIR . 'blocks/testimonials');
    }
}

/**
 * Server-side rendering for search section block
 */
function ps_render_search_section_block($attributes) {
    $section_title = isset($attributes['sectionTitle']) ? $attributes['sectionTitle'] : 'Start Your Rewarding Shopping Journey';
    $section_subtitle = isset($attributes['sectionSubtitle']) ? $attributes['sectionSubtitle'] : 'Search millions of products and start earning rewards instantly';
    $anchor_id = isset($attributes['anchorId']) ? $attributes['anchorId'] : 'search-products';
    $background_color = isset($attributes['backgroundColor']) ? $attributes['backgroundColor'] : '#ffffff';
    
    // Feature attributes (only features 2 and 3 are used)
    $feature2_icon = isset($attributes['feature2Icon']) ? $attributes['feature2Icon'] : '🎯';
    $feature2_text = isset($attributes['feature2Text']) ? $attributes['feature2Text'] : 'Advanced filtering options';
    $feature3_icon = isset($attributes['feature3Icon']) ? $attributes['feature3Icon'] : '📊';
    $feature3_text = isset($attributes['feature3Text']) ? $attributes['feature3Text'] : 'Price per unit sorting';
    
    ob_start();
    ?>
    <div class="wp-block-primates-shoppers-search-section ps-search-section" id="<?php echo esc_attr($anchor_id); ?>" style="background-color: <?php echo esc_attr($background_color); ?>;">
        <div class="ps-container">
            <h2 class="ps-section-title"><?php echo esc_html($section_title); ?></h2>
            <p class="ps-section-subtitle"><?php echo esc_html($section_subtitle); ?></p>
            
            <!-- Features above search -->
            <div class="ps-search-features">
                <div class="ps-feature">
                    <span class="ps-feature-icon"><?php echo esc_html($feature2_icon); ?></span>
                    <span><?php echo esc_html($feature2_text); ?></span>
                </div>
                <div class="ps-feature">
                    <span class="ps-feature-icon"><?php echo esc_html($feature3_icon); ?></span>
                    <span><?php echo esc_html($feature3_text); ?></span>
                </div>
            </div>

            <!-- Embedded Search Form -->
            <div class="ps-search-form-container">
                <?php echo do_shortcode('[primates_shoppers]'); ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Enqueue block editor assets
 */
function ps_enqueue_block_editor_assets() {
    // Only enqueue if we're in the block editor
    if (!function_exists('wp_enqueue_script')) {
        return;
    }
    
    // Check if the JavaScript file exists before enqueuing
    $js_file = PS_PLUGIN_DIR . 'blocks/index-built.js';
    if (file_exists($js_file)) {
        wp_enqueue_script(
            'ps-blocks-editor',
            PS_PLUGIN_URL . 'blocks/index-built.js',
            array('wp-blocks', 'wp-i18n', 'wp-element', 'wp-block-editor', 'wp-components'),
            PS_VERSION,
            true
        );
    }
    
    // Check if the CSS file exists before enqueuing
    $css_file = PS_PLUGIN_DIR . 'blocks/blocks.css';
    if (file_exists($css_file)) {
        wp_enqueue_style(
            'ps-blocks-editor',
            PS_PLUGIN_URL . 'blocks/blocks.css',
            array(),
            PS_VERSION
        );
    }
}

/**
 * Enqueue block frontend assets
 */
function ps_enqueue_block_frontend_assets() {
    // Only enqueue on pages that have our blocks
    if (has_block('primates-shoppers/hero-section') || 
        has_block('primates-shoppers/value-proposition') || 
        has_block('primates-shoppers/search-section') || 
        has_block('primates-shoppers/testimonials')) {
        
        wp_enqueue_style(
            'ps-blocks-frontend',
            PS_PLUGIN_URL . 'blocks/blocks.css',
            array(),
            PS_VERSION
        );
        
        // Enqueue the same JavaScript from the original landing page for analytics
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                console.log("Landing page blocks loaded");
                
                // Track CTA clicks
                $(".ps-cta-button").on("click", function() {
                    var ctaId = $(this).attr("id") || "cta-click";
                    console.log("CTA clicked: " + ctaId);
                    
                    // Google Analytics event
                    if (typeof gtag !== "undefined") {
                        gtag("event", "cta_click", {
                            event_category: "Landing Page",
                            event_label: ctaId
                        });
                    }
                    
                    // Facebook Pixel event
                    if (typeof fbq !== "undefined") {
                        fbq("track", "Lead", {
                            content_name: "Landing Page CTA",
                            content_category: "landing_page"
                        });
                    }
                });
                
                // Scroll depth tracking
                var scrollDepths = [25, 50, 75, 100];
                var scrollFlags = {};
                
                $(window).scroll(function() {
                    var scrollPercent = Math.round(($(window).scrollTop() / ($(document).height() - $(window).height())) * 100);
                    
                    scrollDepths.forEach(function(depth) {
                        if (scrollPercent >= depth && !scrollFlags[depth]) {
                            scrollFlags[depth] = true;
                            console.log(depth + "% scroll depth reached");
                            
                            if (typeof gtag !== "undefined") {
                                gtag("event", "scroll_depth", {
                                    event_category: "Landing Page",
                                    event_label: depth + "%"
                                });
                            }
                        }
                    });
                });
            });
        ');
    }
}

// Initialize blocks with error handling
if (function_exists('ps_blocks_init')) {
    $init_result = ps_blocks_init();
    if (!$init_result) {
        error_log('Primates Shoppers: Block initialization failed');
    }
} else {
    error_log('Primates Shoppers: ps_blocks_init function not found');
}
?>