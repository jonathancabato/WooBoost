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
        // Admin access control
        add_action('admin_init', array($this, 'restrict_admin_access'));
        
        // UI modifications for demo users
        add_action('admin_enqueue_scripts', array($this, 'enqueue_demo_scripts'));
        
        // Remove bulk actions and row actions for demo users
        add_filter('bulk_actions-edit-product', array($this, 'remove_bulk_actions'));
        add_filter('post_row_actions', array($this, 'remove_row_actions'), 10, 2);
        
        // Prevent editing of existing products
        add_action('load-post.php', array($this, 'prevent_existing_product_edit'));
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
        
        // Define capabilities for demo role
        $capabilities = array(
            // Basic WordPress capabilities
            'read' => true,
            
            // Product management capabilities - only for new products
            'edit_products' => true,
            'create_products' => true,
            'publish_products' => true,
            'read_private_products' => true,
            
            // Required for admin access
            'access_admin' => true,
        );
        
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
     * Restrict admin access for demo users
     */
    public function restrict_admin_access() {
        if (!$this->is_demo_user()) {
            return;
        }
        
        global $pagenow;
        
        // Define allowed pages for demo users
        $allowed_pages = array(
            'index.php',    // Main dashboard
            'edit.php',     // Product list (when post_type=product)
            'post-new.php', // Add new product (when post_type=product)
            'post.php',     // Edit product (when post_type=product)
            'admin-ajax.php', // AJAX requests
            'admin-post.php', // Admin post processing
            'profile.php',  // User profile
            'user-edit.php', // Edit user profile
        );
        
        // Check if current page is allowed
        if (!in_array($pagenow, $allowed_pages)) {
            $this->redirect_to_products();
            return;
        }
        
        // Allow dashboard access
        if ($pagenow === 'index.php') {
            return; // Dashboard is allowed
        }
        
        // Allow profile pages
        if (in_array($pagenow, array('profile.php', 'user-edit.php'))) {
            return; // Profile pages are allowed
        }
        
        // For allowed pages, ensure they are product-related
        if (in_array($pagenow, array('edit.php', 'post-new.php', 'post.php'))) {
            $post_type = isset($_GET['post_type']) ? sanitize_text_field($_GET['post_type']) : '';
            
            // For post.php, get post type from post ID
            if ($pagenow === 'post.php' && isset($_GET['post'])) {
                $post_id = intval($_GET['post']);
                $post = get_post($post_id);
                if ($post) {
                    $post_type = $post->post_type;
                }
            }
            
            // For edit.php without post_type, default is 'post'
            if ($pagenow === 'edit.php' && empty($post_type)) {
                $post_type = 'post';
            }
            
            // Only allow product post type
            if ($post_type !== 'product') {
                $this->redirect_to_products();
            }
        }
    }
    
    /**
     * Redirect demo users to products page
     */
    private function redirect_to_products() {
        $redirect_url = admin_url('edit.php?post_type=product');
        wp_safe_redirect($redirect_url);
        exit;
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
     * Prevent demo users from editing existing products
     */
    public function prevent_existing_product_edit() {
        if (!$this->is_demo_user()) {
            return;
        }
        
        // Check if we're trying to edit an existing product
        if (isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit') {
            $post_id = intval($_GET['post']);
            $post = get_post($post_id);
            
            if ($post && $post->post_type === 'product') {
                // Redirect to products list with error message
                $redirect_url = add_query_arg(
                    array(
                        'post_type' => 'product',
                        'demo_error' => 'edit_existing'
                    ),
                    admin_url('edit.php')
                );
                wp_safe_redirect($redirect_url);
                exit;
            }
        }
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
        
        // Check if user is trying to edit an existing product
        if (isset($args[0]) && $args[0] === 'edit_post' && isset($args[2])) {
            $post_id = $args[2];
            $post = get_post($post_id);
            
            if ($post && $post->post_type === 'product' && $post->post_status !== 'auto-draft') {
                // Remove capability to edit existing products
                $allcaps['edit_post'] = false;
                $allcaps['edit_product'] = false;
                $allcaps['edit_products'] = false;
            }
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
        
        // Show demo error notices
        if (isset($_GET['demo_error'])) {
            $error_type = sanitize_text_field($_GET['demo_error']);
            
            switch ($error_type) {
                case 'edit_existing':
                    $message = __('Demo users cannot edit existing products. You can only create new products.', 'wooboost');
                    break;
                default:
                    $message = __('This action is restricted for demo users.', 'wooboost');
            }
            
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . esc_html__('Demo Restriction:', 'wooboost') . '</strong> ';
            echo esc_html($message) . '</p>';
            echo '</div>';
        }
        
        // Show general demo mode notice on dashboard and product pages only
        $screen = get_current_screen();
        if ($screen && ($screen->post_type === 'product' || $screen->id === 'edit-product' || $screen->id === 'dashboard')) {
            echo '<div class="notice notice-info">';
            echo '<p><strong>' . esc_html__('Demo Mode Active:', 'wooboost') . '</strong> ';
            echo esc_html__('You are in demo mode with limited access to WooCommerce product management.', 'wooboost');
            echo '</p>';
            echo '</div>';
        }
    }
}