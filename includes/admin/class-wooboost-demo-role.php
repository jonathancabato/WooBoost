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
        // Access control for demo users
        add_action('admin_init', array($this, 'restrict_admin_access'));
        
        // Menu filtering for demo users
        add_action('admin_menu', array($this, 'filter_admin_menu'), 999);
        add_action('admin_bar_menu', array($this, 'filter_admin_bar'), 999);
        
        // UI modifications for demo users
        add_action('admin_enqueue_scripts', array($this, 'enqueue_demo_scripts'));
        
        // Remove bulk actions and row actions for demo users
        add_filter('bulk_actions-edit-product', array($this, 'remove_bulk_actions'));
        add_filter('post_row_actions', array($this, 'remove_row_actions'), 10, 2);
        
        // Prevent delete capabilities
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
        
        // Define minimal capabilities for demo user - only product management
        $capabilities = array(
            // Basic WordPress capabilities
            'read' => true,
            
            // Product management capabilities only
            'edit_products' => true,
            'edit_published_products' => true,
            'edit_private_products' => true,
            'edit_others_products' => true,
            'publish_products' => true,
            'read_private_products' => true,
            'create_products' => true,
            
            // File upload for product images
            'upload_files' => true,
            
            // Explicitly deny delete capabilities
            'delete_products' => false,
            'delete_private_products' => false,
            'delete_published_products' => false,
            'delete_others_products' => false,
            
            // Deny other capabilities that might give unwanted access
            'edit_posts' => false,
            'edit_pages' => false,
            'edit_themes' => false,
            'edit_plugins' => false,
            'manage_options' => false,
            'manage_categories' => false,
            'manage_links' => false,
            'edit_users' => false,
            'list_users' => false,
            'promote_users' => false,
            'delete_users' => false,
            'remove_users' => false,
        );
        
        // Add the demo role
        add_role(
            self::ROLE_NAME,
            __('Demo User', 'wooboost'),
            $capabilities
        );
        
        // Log role creation
        if (class_exists('WooBoost_Logger')) {
            WooBoost_Logger::info('Demo user role created with restricted capabilities');
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
            'edit.php',      // Product list (when post_type=product)
            'post-new.php',  // Add new product (when post_type=product)
            'post.php',      // Edit product (when post_type=product)
            'admin-ajax.php', // AJAX requests
            'admin.php'      // For admin.php pages we'll handle separately
        );
        
        // Allow specific admin.php pages
        $allowed_admin_pages = array(
            'profile.php',   // User profile
        );
        
        // Check if current page is allowed
        $is_allowed = false;
        
        if (in_array($pagenow, $allowed_pages)) {
            // For edit.php, post-new.php, post.php - only allow if post_type is product
            if (in_array($pagenow, array('edit.php', 'post-new.php', 'post.php'))) {
                $post_type = isset($_GET['post_type']) ? $_GET['post_type'] : '';
                if ($pagenow === 'post.php') {
                    // For post.php, check the post type of the post being edited
                    $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
                    if ($post_id) {
                        $post = get_post($post_id);
                        $post_type = $post ? $post->post_type : '';
                    }
                }
                $is_allowed = ($post_type === 'product');
            } else {
                $is_allowed = true;
            }
        } elseif ($pagenow === 'admin.php') {
            // For admin.php pages, check the page parameter
            $page = isset($_GET['page']) ? $_GET['page'] : '';
            $is_allowed = in_array($page, $allowed_admin_pages);
        }
        
        // If not allowed, redirect to product list (avoid redirect loops)
        if (!$is_allowed && !($pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'product')) {
            wp_redirect(admin_url('edit.php?post_type=product'));
            exit;
        }
    }
    
    /**
     * Filter admin menu for demo users
     */
    public function filter_admin_menu() {
        if (!$this->is_demo_user()) {
            return;
        }
        
        global $menu, $submenu;
        
        // Define allowed menu items (using menu slugs)
        $allowed_menus = array(
            'edit.php?post_type=product', // Products menu
        );
        
        // Remove all menu items except allowed ones
        if (is_array($menu)) {
            foreach ($menu as $key => $menu_item) {
                $menu_slug = isset($menu_item[2]) ? $menu_item[2] : '';
                if (!in_array($menu_slug, $allowed_menus)) {
                    unset($menu[$key]);
                }
            }
        }
        
        // Filter submenu items for products - only allow specific items
        if (isset($submenu['edit.php?post_type=product'])) {
            $allowed_submenus = array(
                'edit.php?post_type=product',     // All Products
                'post-new.php?post_type=product', // Add New
            );
            
            foreach ($submenu['edit.php?post_type=product'] as $key => $submenu_item) {
                $submenu_slug = isset($submenu_item[2]) ? $submenu_item[2] : '';
                if (!in_array($submenu_slug, $allowed_submenus)) {
                    unset($submenu['edit.php?post_type=product'][$key]);
                }
            }
        }
        
        // Remove all other WooCommerce menu items
        unset($submenu['woocommerce']); // Remove WooCommerce submenu items
        
        // Remove specific menu items that might still appear
        remove_menu_page('woocommerce');
        remove_menu_page('edit.php');
        remove_menu_page('edit.php?post_type=page');
        remove_menu_page('upload.php');
        remove_menu_page('edit-comments.php');
        remove_menu_page('themes.php');
        remove_menu_page('plugins.php');
        remove_menu_page('users.php');
        remove_menu_page('tools.php');
        remove_menu_page('options-general.php');
        remove_menu_page('index.php');
    }
    
    /**
     * Filter admin bar for demo users
     */
    public function filter_admin_bar($wp_admin_bar) {
        if (!$this->is_demo_user()) {
            return;
        }
        
        // Remove various admin bar items
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('about');
        $wp_admin_bar->remove_node('wporg');
        $wp_admin_bar->remove_node('documentation');
        $wp_admin_bar->remove_node('support-forums');
        $wp_admin_bar->remove_node('feedback');
        $wp_admin_bar->remove_node('site-name');
        $wp_admin_bar->remove_node('view-site');
        $wp_admin_bar->remove_node('dashboard');
        $wp_admin_bar->remove_node('themes');
        $wp_admin_bar->remove_node('widgets');
        $wp_admin_bar->remove_node('menus');
        $wp_admin_bar->remove_node('new-content');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('search');
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
        unset($actions['duplicate']);
        
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
        
        // Show demo mode notice on allowed pages
        $screen = get_current_screen();
        if ($screen && $screen->post_type === 'product') {
            echo '<div class="notice notice-info">';
            echo '<p><strong>' . esc_html__('Demo Mode Active:', 'wooboost') . '</strong> ';
            echo esc_html__('You have access to WooCommerce product management only. You can view, create, and edit products but cannot delete them. Access to other admin areas is restricted.', 'wooboost');
            echo '</p>';
            echo '</div>';
        }
    }
}