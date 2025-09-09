<?php
/**
 * WooBoost User Access Manager
 * 
 * Manages user access based on roles for WooCommerce product custom post type.
 * Restricts editors to only access product edit and add new product pages.
 *
 * @package WooBoost
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WooBoost_User_Access {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Add WooCommerce product capabilities to editor role
        add_action('init', array($this, 'add_product_capabilities_to_editor'));
        
        // Hook into WordPress admin initialization
        add_action('admin_init', array($this, 'restrict_editor_access'));
        
        // Remove admin menu items for editors
        add_action('admin_menu', array($this, 'remove_admin_menu_items'), 999);
        
        // Remove admin bar items for editors
        add_action('wp_before_admin_bar_render', array($this, 'remove_admin_bar_items'));
        
        // Customize admin menu for editors
        add_action('admin_menu', array($this, 'customize_editor_menu'), 999);
        
        // Redirect unauthorized access attempts
        add_action('current_screen', array($this, 'redirect_unauthorized_access'));
    }
    
    /**
     * Add WooCommerce product capabilities to editor role
     */
    public function add_product_capabilities_to_editor() {
        // Get the editor role
        $editor_role = get_role('editor');
        
        if (!$editor_role) {
            return;
        }
        
        // Check if capabilities are already added to avoid redundant operations
        if ($editor_role->has_cap('edit_products')) {
            return;
        }
        
        // Add WooCommerce product capabilities
        $product_capabilities = array(
            'edit_products',              // Edit products
            'read_products',              // Read products
            'delete_products',            // Delete products
            'edit_others_products',       // Edit others' products
            'publish_products',           // Publish products
            'read_private_products',      // Read private products
            'delete_private_products',    // Delete private products
            'delete_published_products',  // Delete published products
            'delete_others_products',     // Delete others' products
            'edit_private_products',      // Edit private products
            'edit_published_products'     // Edit published products
            // Note: Removed manage_product_terms, edit_product_terms, delete_product_terms, assign_product_terms
            // to prevent access to categories, tags, attributes, and reviews
        );
        
        // Add each capability to the editor role
        foreach ($product_capabilities as $capability) {
            $editor_role->add_cap($capability);
        }
        
        // Log the capability addition for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WooBoost: Added WooCommerce product capabilities to editor role');
        }
    }
    
    /**
     * Restrict editor access to only WooCommerce product pages
     */
    public function restrict_editor_access() {
        // Only apply to editors
        if (!current_user_can('editor') || current_user_can('administrator')) {
            return;
        }
        
        global $pagenow;
        
        // Get current post type
        $post_type = $this->get_current_post_type();
        
        // Define allowed pages for editors
        $allowed_pages = array(
            'edit.php',     // Product list page
            'post-new.php', // Add new product page
            'post.php',     // Edit existing product page
            'admin-ajax.php' // Allow AJAX requests
        );
        
        // Check if current page is allowed
        $is_allowed_page = in_array($pagenow, $allowed_pages);
        
        // For edit.php and post-new.php, ensure it's for products only
        if (($pagenow === 'edit.php' || $pagenow === 'post-new.php') && $post_type !== 'product') {
            $is_allowed_page = false;
        }
        
        // For post.php, check if editing a product
        if ($pagenow === 'post.php') {
            $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
            if ($post_id && get_post_type($post_id) !== 'product') {
                $is_allowed_page = false;
            }
        }
        
        // Allow dashboard access but will be customized
        if ($pagenow === 'index.php') {
            $is_allowed_page = true;
        }
        
        // If not allowed, redirect to products page
        if (!$is_allowed_page && !wp_doing_ajax()) {
            wp_safe_redirect(admin_url('edit.php?post_type=product'));
            exit;
        }
    }
    
    /**
     * Get current post type from various sources
     */
    private function get_current_post_type() {
        global $post, $typenow, $current_screen;
        
        // Try to get post type from various sources
        if ($post && $post->post_type) {
            return $post->post_type;
        }
        
        if ($typenow) {
            return $typenow;
        }
        
        if ($current_screen && $current_screen->post_type) {
            return $current_screen->post_type;
        }
        
        // Check GET parameters
        if (isset($_GET['post_type'])) {
            return sanitize_text_field($_GET['post_type']);
        }
        
        // Check POST parameters
        if (isset($_POST['post_type'])) {
            return sanitize_text_field($_POST['post_type']);
        }
        
        // Try to get from post ID
        if (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            return get_post_type($post_id);
        }
        
        return '';
    }
    
    /**
     * Remove admin menu items for editors
     */
    public function remove_admin_menu_items() {
        // Only apply to editors
        if (!current_user_can('editor') || current_user_can('administrator')) {
            return;
        }
        
        // Remove all default WordPress menu items
        remove_menu_page('index.php');                  // Dashboard
        remove_menu_page('edit.php');                   // Posts
        remove_menu_page('upload.php');                 // Media
        remove_menu_page('edit.php?post_type=page');    // Pages
        remove_menu_page('edit-comments.php');          // Comments
        remove_menu_page('themes.php');                 // Appearance
        remove_menu_page('plugins.php');                // Plugins
        remove_menu_page('users.php');                  // Users
        remove_menu_page('tools.php');                  // Tools
        remove_menu_page('options-general.php');        // Settings
        
        // Remove WooCommerce menu items except products
        remove_menu_page('woocommerce');                 // WooCommerce main
        remove_menu_page('edit.php?post_type=shop_order'); // Orders
        remove_menu_page('edit.php?post_type=shop_coupon'); // Coupons
        
        // Remove other WooCommerce related menus
        remove_submenu_page('woocommerce', 'wc-reports');
        remove_submenu_page('woocommerce', 'wc-settings');
        remove_submenu_page('woocommerce', 'wc-status');
        remove_submenu_page('woocommerce', 'wc-addons');
    }
    
    /**
     * Customize admin menu for editors
     */
    public function customize_editor_menu() {
        // Only apply to editors
        if (!current_user_can('editor') || current_user_can('administrator')) {
            return;
        }
        
        // Add a custom dashboard for editors
        add_menu_page(
            __('Product Dashboard', 'wooboost'),
            __('Dashboard', 'wooboost'),
            'edit_posts',
            'product-dashboard',
            array($this, 'render_product_dashboard'),
            'dashicons-dashboard',
            2
        );
        
        // Add products menu
        add_menu_page(
            __('Products', 'wooboost'),
            __('Products', 'wooboost'),
            'edit_posts',
            'edit.php?post_type=product',
            '',
            'dashicons-products',
            20
        );
        
        // Add submenu items for products
        add_submenu_page(
            'edit.php?post_type=product',
            __('All Products', 'wooboost'),
            __('All Products', 'wooboost'),
            'edit_posts',
            'edit.php?post_type=product'
        );
        
        add_submenu_page(
            'edit.php?post_type=product',
            __('Add New Product', 'wooboost'),
            __('Add New', 'wooboost'),
            'edit_posts',
            'post-new.php?post_type=product'
        );
    }
    
    /**
     * Remove admin bar items for editors
     */
    public function remove_admin_bar_items() {
        // Only apply to editors
        if (!current_user_can('editor') || current_user_can('administrator')) {
            return;
        }
        
        global $wp_admin_bar;
        
        // Remove various admin bar items
        $wp_admin_bar->remove_node('wp-logo');
        $wp_admin_bar->remove_node('about');
        $wp_admin_bar->remove_node('wporg');
        $wp_admin_bar->remove_node('documentation');
        $wp_admin_bar->remove_node('support-forums');
        $wp_admin_bar->remove_node('feedback');
        $wp_admin_bar->remove_node('new-content');
        $wp_admin_bar->remove_node('comments');
        $wp_admin_bar->remove_node('customize');
        
        // Add custom admin bar item for products
        $wp_admin_bar->add_node(array(
            'id'    => 'new-product',
            'title' => __('+ New Product', 'wooboost'),
            'href'  => admin_url('post-new.php?post_type=product'),
            'meta'  => array(
                'class' => 'ab-item'
            )
        ));
    }
    
    /**
     * Redirect unauthorized access attempts
     */
    public function redirect_unauthorized_access($current_screen) {
        // Only apply to editors
        if (!current_user_can('editor') || current_user_can('administrator')) {
            return;
        }
        
        // Skip AJAX requests
        if (wp_doing_ajax()) {
            return;
        }
        
        // Define allowed screen IDs
        $allowed_screens = array(
            'edit-product',
            'product',
            'product-dashboard'
        );
        
        // Check if current screen is allowed
        if ($current_screen && !in_array($current_screen->id, $allowed_screens)) {
            // Redirect to products page
            wp_safe_redirect(admin_url('edit.php?post_type=product'));
            exit;
        }
    }
    
    /**
     * Render custom product dashboard for editors
     */
    public function render_product_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php _e('Product Dashboard', 'wooboost'); ?></h1>
            
            <div class="dashboard-widgets-wrap">
                <div id="dashboard-widgets" class="metabox-holder">
                    <div class="postbox-container">
                        <div class="meta-box-sortables">
                            
                            <!-- Quick Stats -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Product Overview', 'wooboost'); ?></h2>
                                </div>
                                <div class="inside">
                                    <?php
                                    $product_count = wp_count_posts('product');
                                    $published = $product_count->publish ?? 0;
                                    $draft = $product_count->draft ?? 0;
                                    ?>
                                    <p><strong><?php printf(__('Published Products: %d', 'wooboost'), $published); ?></strong></p>
                                    <p><strong><?php printf(__('Draft Products: %d', 'wooboost'), $draft); ?></strong></p>
                                    
                                    <p>
                                        <a href="<?php echo admin_url('edit.php?post_type=product'); ?>" class="button button-primary">
                                            <?php _e('View All Products', 'wooboost'); ?>
                                        </a>
                                        <a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" class="button">
                                            <?php _e('Add New Product', 'wooboost'); ?>
                                        </a>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Recent Products -->
                            <div class="postbox">
                                <div class="postbox-header">
                                    <h2 class="hndle"><?php _e('Recent Products', 'wooboost'); ?></h2>
                                </div>
                                <div class="inside">
                                    <?php
                                    $recent_products = get_posts(array(
                                        'post_type' => 'product',
                                        'numberposts' => 5,
                                        'post_status' => array('publish', 'draft')
                                    ));
                                    
                                    if ($recent_products) {
                                        echo '<ul>';
                                        foreach ($recent_products as $product) {
                                            echo '<li>';
                                            echo '<a href="' . get_edit_post_link($product->ID) . '">' . esc_html($product->post_title) . '</a>';
                                            echo ' - <em>' . esc_html($product->post_status) . '</em>';
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                    } else {
                                        echo '<p>' . __('No products found.', 'wooboost') . '</p>';
                                    }
                                    ?>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Remove WooCommerce product capabilities from editor role
     * Called during plugin deactivation
     */
    public static function remove_product_capabilities_from_editor() {
        // Get the editor role
        $editor_role = get_role('editor');
        
        if (!$editor_role) {
            return;
        }
        
        // WooCommerce product capabilities to remove
        $product_capabilities = array(
            'edit_products',
            'read_products',
            'delete_products',
            'edit_others_products',
            'publish_products',
            'read_private_products',
            'delete_private_products',
            'delete_published_products',
            'delete_others_products',
            'edit_private_products',
            'edit_published_products'
            // Note: Only removing core product capabilities, not term management ones
        );
        
        // Remove each capability from the editor role
        foreach ($product_capabilities as $capability) {
            $editor_role->remove_cap($capability);
        }
        
        // Log the capability removal for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('WooBoost: Removed WooCommerce product capabilities from editor role');
        }
    }
}

// Initialize the class if we're in admin area and WooCommerce is active
if (is_admin() && class_exists('WooCommerce')) {
    new WooBoost_User_Access();
}
