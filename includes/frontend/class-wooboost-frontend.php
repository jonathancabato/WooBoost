<?php
/**
 * WooBoost Frontend Module
 * 
 * Handles frontend functionality and asset enqueuing
 *
 * @package WooBoost
 * @subpackage Includes/Frontend
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooBoost_Frontend class
 * 
 * Frontend functionality handler
 */
class WooBoost_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    /**
     * Enqueue admin assets only on product edit screens
     * 
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on product edit screens
        if (!$this->is_product_edit_screen($hook)) {
            return;
        }
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'wooboost-editor-ui',
            WOOBOOST_PLUGIN_URL . 'assets/js/editor-ui.js',
            array('jquery'),
            WOOBOOST_VERSION,
            true
        );
        
        // Enqueue CSS
        wp_enqueue_style(
            'wooboost-editor-ui',
            WOOBOOST_PLUGIN_URL . 'assets/css/editor-ui.css',
            array(),
            WOOBOOST_VERSION
        );
        
        // Localize script with REST API data
        wp_localize_script(
            'wooboost-editor-ui',
            'wooboostEditor',
            array(
                'restUrl'    => rest_url('wooboost/v1'),
                'nonce'      => wp_create_nonce('wp_rest'),
                'hideButton' => $this->get_user_hide_button_preference(),
                'strings'    => array(
                    'generateDescription' => __('Generate description with WooBoost', 'wooboost'),
                    'loading'            => __('Generating content...', 'wooboost'),
                    'error'              => __('Error generating content. Please try again.', 'wooboost'),
                    'success'            => __('Content generated successfully!', 'wooboost'),
                )
            )
        );
    }
    
    /**
     * Check if current screen is a product edit screen
     * 
     * @param string $hook Current admin page hook
     * @return bool True if product edit screen
     */
    private function is_product_edit_screen($hook) {
        global $post_type, $pagenow;
        
        // Check for product post type on edit screens
        if ($post_type === 'product' && in_array($pagenow, array('post.php', 'post-new.php'))) {
            return true;
        }
        
        // Additional check for WooCommerce product screens
        if (function_exists('wc_get_page_screen_id')) {
            $wc_screen_ids = array(
                wc_get_page_screen_id('product'),
                'product'
            );
            
            if (in_array($hook, $wc_screen_ids)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get user preference for hiding the button
     * 
     * @return bool True if user wants to hide the button
     */
    private function get_user_hide_button_preference() {
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            return false;
        }
        
        // Get user meta (to be implemented in Step 9)
        return get_user_meta($user_id, 'wooboost_hide_editor_button', true) === '1';
    }
}