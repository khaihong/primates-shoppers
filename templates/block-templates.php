<?php
/**
 * Block Templates for Primates Shoppers Landing Page
 * 
 * These templates work with Full Site Editing (FSE) themes and the WordPress Site Editor.
 * They provide pre-built templates that can be selected when creating pages.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register block templates
 */
function ps_register_block_templates() {
    // Register a custom page template
    $template_path = PS_PLUGIN_DIR . 'templates/block-template-landing-page.html';
    
    // Create the template file if it doesn't exist
    if (!file_exists($template_path)) {
        ps_create_block_template_file();
    }
    
    // Register the template with WordPress
    add_filter('theme_page_templates', 'ps_add_page_template');
    add_filter('template_include', 'ps_load_page_template');
}

/**
 * Add our template to the page template dropdown
 */
function ps_add_page_template($templates) {
    $templates['primates-shoppers-landing.php'] = __('Primates Shoppers Landing Page', 'primates-shoppers');
    return $templates;
}

/**
 * Load our template when selected
 */
function ps_load_page_template($template) {
    global $post;
    
    // Check if the custom template is selected
    if (is_page() && get_page_template_slug($post->ID) === 'primates-shoppers-landing.php') {
        $template_path = PS_PLUGIN_DIR . 'templates/page-landing.php';
        if (file_exists($template_path)) {
            return $template_path;
        }
    }
    
    return $template;
}

/**
 * Create the block template HTML file
 */
function ps_create_block_template_file() {
    $template_content = ps_get_block_template_content();
    $template_path = PS_PLUGIN_DIR . 'templates/block-template-landing-page.html';
    
    // Ensure templates directory exists
    $templates_dir = dirname($template_path);
    if (!file_exists($templates_dir)) {
        wp_mkdir_p($templates_dir);
    }
    
    // Write the template file
    file_put_contents($template_path, $template_content);
}

/**
 * Get the block template content as HTML
 */
function ps_get_block_template_content() {
    return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"layout":{"type":"default"}} -->
<main class="wp-block-group" style="margin-top:0;margin-bottom:0">

<!-- wp:primates-shoppers/hero-section {"title":"Shop Smart, Share Rewards, Support Causes","subtitle":"The first platform that democratizes shopping rewards - every purchase you make generates revenue that\'s shared with you and donated to charities you care about.","ctaText":"Start Shopping \u0026 Earning","ctaUrl":"#search-products","subtext":"Search millions of products from Amazon, eBay \u0026 Walmart","backgroundColor":"linear-gradient(135deg, #667eea 0%, #764ba2 100%)","textColor":"#ffffff"} /-->

<!-- wp:primates-shoppers/value-proposition {"sectionTitle":"Revolutionary Revenue Sharing","card1Icon":"💰","card1Title":"Earn From Every Search","card1Description":"Get a share of advertising revenue from every product search you make. Your shopping activity generates real income.","card2Icon":"🤝","card2Title":"Support Charities","card2Description":"Choose which charities receive a portion of the revenue. Make a positive impact while you shop.","card3Icon":"🔍","card3Title":"Smart Product Search","card3Description":"Search across Amazon, eBay, and Walmart simultaneously. Find the best deals with advanced filtering and sorting.","backgroundColor":"#f8f9fa"} /-->

<!-- wp:primates-shoppers/search-section {"sectionTitle":"Start Your Rewarding Shopping Journey","sectionSubtitle":"Search millions of products and start earning rewards instantly","anchorId":"search-products","feature1Icon":"⚡","feature1Text":"Real-time price comparison","feature2Icon":"🎯","feature2Text":"Advanced filtering options","feature3Icon":"📊","feature3Text":"Price per unit sorting","feature4Icon":"🏷️","feature4Text":"Best deal detection","backgroundColor":"#ffffff"} /-->

<!-- wp:primates-shoppers/testimonials {"sectionTitle":"What Our Users Say","testimonial1Content":"Finally, a platform that shares the wealth! I\'ve earned over $200 while supporting my favorite environmental charity.","testimonial1Author":"Sarah M.","testimonial1Role":"Active Shopper","testimonial2Content":"The search functionality is amazing - I can compare prices across all major platforms in one place and earn money doing it.","testimonial2Author":"Mike R.","testimonial2Role":"Deal Hunter","testimonial3Content":"Love that I can contribute to charity just by shopping. It\'s a win-win-win situation for everyone involved.","testimonial3Author":"Lisa K.","testimonial3Role":"Charity Supporter","backgroundColor":"#f8f9fa"} /-->

</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
}

/**
 * Register template parts (for FSE themes)
 */
function ps_register_template_parts() {
    // Register our landing page as a template part
    if (function_exists('register_block_template')) {
        register_block_template(
            'primates-shoppers//landing-page',
            array(
                'title'       => __('Primates Shoppers Landing Page', 'primates-shoppers'),
                'description' => __('Complete landing page with hero, value proposition, search, and testimonials', 'primates-shoppers'),
                'content'     => function_exists('ps_get_landing_page_pattern_content') ? ps_get_landing_page_pattern_content() : '',
                'area'        => 'uncategorized',
            )
        );
    }
}

/**
 * Add landing page to theme.json (for FSE themes)
 */
function ps_add_to_theme_json() {
    $theme_json_additions = array(
        'version' => 2,
        'templateParts' => array(
            array(
                'name'  => 'primates-shoppers-landing-page',
                'title' => 'Primates Shoppers Landing Page',
                'area'  => 'uncategorized',
            ),
        ),
        'customTemplates' => array(
            array(
                'name'      => 'primates-shoppers-landing',
                'title'     => 'Primates Shoppers Landing Page',
                'postTypes' => array('page'),
            ),
        ),
    );
    
    return $theme_json_additions;
}

/**
 * Create a starter template function for developers
 */
function ps_create_landing_page_programmatically($title = 'Landing Page', $status = 'draft') {
    // Create a new page with the landing page blocks
    $page_data = array(
        'post_title'   => $title,
        'post_content' => function_exists('ps_get_landing_page_pattern_content') ? ps_get_landing_page_pattern_content() : ps_get_block_template_content(),
        'post_status'  => $status,
        'post_type'    => 'page',
        'meta_input'   => array(
            '_wp_page_template' => 'primates-shoppers-landing.php'
        ),
    );
    
    $page_id = wp_insert_post($page_data);
    
    if ($page_id && !is_wp_error($page_id)) {
        // Set the page template
        update_post_meta($page_id, '_wp_page_template', 'primates-shoppers-landing.php');
        
        // Log the creation
        error_log("Primates Shoppers: Created landing page with ID {$page_id}");
        
        return $page_id;
    }
    
    return false;
}

/**
 * Add admin menu item for creating landing pages
 */
function ps_add_admin_landing_page_menu() {
    add_submenu_page(
        'edit.php?post_type=page',
        __('Create Landing Page', 'primates-shoppers'),
        __('+ Landing Page', 'primates-shoppers'),
        'edit_pages',
        'ps-create-landing-page',
        'ps_admin_create_landing_page'
    );
}

/**
 * Admin page for creating landing pages
 */
function ps_admin_create_landing_page() {
    // Handle form submission
    if (isset($_POST['create_landing_page']) && wp_verify_nonce($_POST['ps_nonce'], 'create_landing_page')) {
        $title = sanitize_text_field($_POST['page_title']);
        $status = sanitize_text_field($_POST['page_status']);
        
        $page_id = ps_create_landing_page_programmatically($title, $status);
        
        if ($page_id) {
            $edit_url = admin_url("post.php?post={$page_id}&action=edit");
            echo '<div class="notice notice-success"><p>' . 
                 sprintf(__('Landing page created successfully! <a href="%s">Edit it now</a>', 'primates-shoppers'), $edit_url) . 
                 '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>' . __('Failed to create landing page.', 'primates-shoppers') . '</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Create Primates Shoppers Landing Page', 'primates-shoppers'); ?></h1>
        <p><?php _e('Create a new landing page with all the Primates Shoppers blocks pre-configured.', 'primates-shoppers'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('create_landing_page', 'ps_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="page_title"><?php _e('Page Title', 'primates-shoppers'); ?></label></th>
                    <td><input type="text" id="page_title" name="page_title" value="Landing Page" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="page_status"><?php _e('Page Status', 'primates-shoppers'); ?></label></th>
                    <td>
                        <select id="page_status" name="page_status">
                            <option value="draft"><?php _e('Draft', 'primates-shoppers'); ?></option>
                            <option value="publish"><?php _e('Published', 'primates-shoppers'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Create Landing Page', 'primates-shoppers'), 'primary', 'create_landing_page'); ?>
        </form>
        
        <hr>
        
        <h2><?php _e('Alternative Methods', 'primates-shoppers'); ?></h2>
        <ul>
            <li><strong><?php _e('Block Patterns:', 'primates-shoppers'); ?></strong> <?php _e('Create a new page and search for "Complete Landing Page" in the patterns library.', 'primates-shoppers'); ?></li>
            <li><strong><?php _e('Page Template:', 'primates-shoppers'); ?></strong> <?php _e('Create a new page and select "Primates Shoppers Landing Page" from the Page Attributes template dropdown.', 'primates-shoppers'); ?></li>
            <li><strong><?php _e('Individual Blocks:', 'primates-shoppers'); ?></strong> <?php _e('Add individual blocks from the "Primates Shoppers" category in the block inserter.', 'primates-shoppers'); ?></li>
        </ul>
    </div>
    <?php
}

// Initialize templates
add_action('init', 'ps_register_block_templates');
add_action('init', 'ps_register_template_parts');
add_action('admin_menu', 'ps_add_admin_landing_page_menu');
?> 