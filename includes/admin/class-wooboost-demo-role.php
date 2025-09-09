<?php
/**
 * WooBoost Demo User Role Management
 * 
 * Handles creation and management of the demo user role with restricted access
 *
 * @package WooBoost
 * @subpackage Includes/Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooBoost_Demo_Role class
 * 
 * Manages demo user role functionality
 */
class WooBoost_Demo_Role {
    
    /**
     * Demo role name
     */
    const ROLE_NAME = 'demo';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // UI modifications for demo users
        add_action('admin_enqueue_scripts', array($this, 'enqueue_demo_scripts'));
        
        // Remove bulk actions and row actions for demo users
        add_filter('bulk_actions-edit-product', array($this, 'remove_bulk_actions'));
        add_filter('post_row_actions', array($this, 'remove_row_actions'), 10, 2);
        
        // Prevent editing of existing products
        add_filter('user_has_cap', array($this, 'filter_user_capabilities'), 10, 4);
        
        // Show admin notices for demo restrictions
        add_action('admin_notices', array($this, 'show_demo_notices'));
    }
    
    /**
     * Create demo role
     */
    public static function create_demo_role() {
        // Remove role if it exists to ensure clean setup
        remove_role(self::ROLE_NAME);
        
        // Get shop manager capabilities as base
        $shop_manager = get_role('shop_manager');
        $capabilities = $shop_manager ? $shop_manager->capabilities : array();
        
        // Ensure basic WordPress capabilities
        $capabilities = array_merge($capabilities, array(
            'read' => true,
            'edit_posts' => true,
            'edit_published_posts' => true,
            'upload_files' => true,
            'edit_products' => true,
            'edit_published_products' => true,
            'edit_private_products' => true,
            'edit_others_products' => true,
            'publish_products' => true,
            'read_private_products' => true,
            'delete_products' => false, // Restrict deleting
            'delete_private_products' => false,
            'delete_published_products' => false,
            'delete_others_products' => false,
        ));
        
        // Add the demo role
        add_role(
            self::ROLE_NAME,
            __('Demo User', 'wooboost'),
            $capabilities
        );
        
        // Log role creation
        if (class_exists('WooBoost_Logger')) {
            WooBoost_Logger::info('Demo user role created successfully');
        }
    }
    
    /**
     * Remove demo role
     */
    public static function remove_demo_role() {
        remove_role(self::ROLE_NAME);
        
        // Log role removal
        if (class_exists('WooBoost_Logger')) {
            WooBoost_Logger::info('Demo user role removed');
        }
    }
    
    /**
     * Check if current user is demo user
     * 
     * @return bool
     */
    public function is_demo_user() {
        $user = wp_get_current_user();
        return in_array(self::ROLE_NAME, $user->roles);
    }
    
    /**
     * Enqueue scripts for demo user UI modifications
     */
    public function enqueue_demo_scripts() {
        if (!$this->is_demo_user()) {
            return;
        }
        
        // Enqueue custom CSS and JS for demo users
        wp_enqueue_script(
            'wooboost-demo-ui',
            WOOBOOST_PLUGIN_URL . 'assets/js/demo-ui.js',
            array('jquery'),
            WOOBOOST_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wooboost-demo-ui',
            WOOBOOST_PLUGIN_URL . 'assets/css/demo-ui.css',
            array(),
            WOOBOOST_VERSION
        );
    }
    
    /**
     * Remove bulk actions for demo users on product list
     * 
     * @param array $actions
     * @return array
     */
    public function remove_bulk_actions($actions) {
        if (!$this->is_demo_user()) {
            return $actions;
        }
        
        // Remove delete action
        unset($actions['trash']);
        
        return $actions;
    }
    
    /**
     * Remove row actions for demo users on product list
     * 
     * @param array $actions
     * @param WP_Post $post
     * @return array
     */
    public function remove_row_actions($actions, $post) {
        if (!$this->is_demo_user() || $post->post_type !== 'product') {
            return $actions;
        }
        
        // Remove delete and quick edit actions
        unset($actions['trash']);
        unset($actions['inline hide-if-no-js']);
        
        return $actions;
    }
    
    /**
     * Filter user capabilities for demo users
     * 
     * @param array $allcaps All capabilities
     * @param array $caps Required capabilities
     * @param array $args Additional arguments
     * @param WP_User $user Current user
     * @return array
     */
    public function filter_user_capabilities($allcaps, $caps, $args, $user) {
        if (!in_array(self::ROLE_NAME, $user->roles)) {
            return $allcaps;
        }
        
        // Only restrict delete capabilities, not edit capabilities
        if (isset($args[0]) && strpos($args[0], 'delete_') === 0) {
            $allcaps[$args[0]] = false;
        }
        
        return $allcaps;
    }
    
    /**
     * Show admin notices for demo restrictions
     */
    public function show_demo_notices() {
        if (!$this->is_demo_user()) {
            return;
        }
        
        // Show general demo mode notice on dashboard and product pages only
        $screen = get_current_screen();
        if ($screen && ($screen->post_type === 'product' || $screen->id === 'edit-product' || $screen->id === 'dashboard')) {
            echo '<div class="notice notice-info">';
            echo '<p><strong>' . esc_html__('Demo Mode Active:', 'wooboost') . '</strong> ';
            echo esc_html__('You are in demo mode with limited access to WooCommerce product management. You can view and edit products but cannot delete them.', 'wooboost');
            echo '</p>';
            echo '</div>';
        }
    }
}