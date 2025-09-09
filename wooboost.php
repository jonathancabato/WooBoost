<?php
/**
 * Plugin Name: WooBoost
 * Plugin URI: https://github.com/your-username/wooboost
 * Description: A powerful WordPress plugin to boost WooCommerce functionality with advanced features and optimizations.
 * Version: 1.0.0
 * Author: WooBoost Team
 * Author URI: https://wooboost.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wooboost
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package WooBoost
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WOOBOOST_VERSION', '1.0.0');
define('WOOBOOST_PLUGIN_FILE', __FILE__);
define('WOOBOOST_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WOOBOOST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOOBOOST_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Class WooBoost_Main
 * 
 * Main plugin class that handles initialization
 */
class WooBoost_Main {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance of the class
     * 
     * @return WooBoost_Main
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
        
        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('wooboost', false, dirname(WOOBOOST_PLUGIN_BASENAME) . '/languages');
        
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Include main functionality
        $this->includes();
    }
    
    /**
     * Plugin loaded hook
     */
    public function plugins_loaded() {
        // Plugin loaded actions here
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Include main file
        if (file_exists(WOOBOOST_PLUGIN_DIR . 'includes/main.php')) {
            require_once WOOBOOST_PLUGIN_DIR . 'includes/main.php';
        }
    }
    
    /**
     * Check if WooCommerce is active
     * 
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    /**
     * Display notice when WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . esc_html__('WooBoost', 'wooboost') . '</strong> ';
        echo esc_html__('requires WooCommerce to be installed and active.', 'wooboost');
        echo '</p></div>';
    }
    
    /**
     * Plugin activation hook
     */
    public function activate() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            wp_die(__('WooBoost requires WordPress 5.0 or higher.', 'wooboost'));
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            wp_die(__('WooBoost requires PHP 7.4 or higher.', 'wooboost'));
        }
        
        // Activation logic here
        $this->create_tables();
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation hook
     */
    public function deactivate() {
        // Include user access class for cleanup
        if (file_exists(WOOBOOST_PLUGIN_DIR . 'includes/admin/class-wooboost-user-access.php')) {
            require_once WOOBOOST_PLUGIN_DIR . 'includes/admin/class-wooboost-user-access.php';
        }
        
        // Remove WooCommerce product capabilities from editor role
        if (class_exists('WooBoost_User_Access')) {
            WooBoost_User_Access::remove_product_capabilities_from_editor();
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables if needed
     */
    private function create_tables() {
        // Future database table creation logic
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        // Set default options
        if (!get_option('wooboost_version')) {
            add_option('wooboost_version', WOOBOOST_VERSION);
        }
    }
}

// Initialize the plugin
WooBoost_Main::get_instance();

// Admin notice: prompt for WOOBOOST_OPENAI_KEY in wp-config.php
add_action('admin_notices', function() {
    // Only show to administrators
    if (!current_user_can('manage_options')) {
        return;
    }

    // Don't show on non-admin screens
    if (!is_admin()) {
        return;
    }

    // If constant is defined, nothing to do
    if (defined('WOOBOOST_OPENAI_KEY') && !empty(WOOBOOST_OPENAI_KEY)) {
        return;
    }

    // Prepare the code snippet
    $snippet = "define('WOOBOOST_OPENAI_KEY', 'your-api-key-here');";
    ?>
    <div class="notice notice-warning is-dismissible">
        <p><strong><?php esc_html_e('WooBoost OpenAI key missing', 'wooboost'); ?></strong></p>
        <p><?php esc_html_e('WooBoost requires an OpenAI API key to generate content. Add the following line to your wp-config.php (before the line that says "That’s all, stop editing! Happy publishing."):', 'wooboost'); ?></p>
        <pre style="background:#f7f7f7;border:1px solid #ddd;padding:10px;"><?php echo esc_html($snippet); ?></pre>
        <p>
            <button class="button button-primary" id="wooboost-copy-key-snippet"><?php esc_html_e('Copy to clipboard', 'wooboost'); ?></button>
            <a href="https://platform.openai.com/account/api-keys" target="_blank" class="button" rel="noopener noreferrer"><?php esc_html_e('Get an OpenAI API key', 'wooboost'); ?></a>
        </p>
    </div>
    <script type="text/javascript">
    (function(){
        var btn = document.getElementById('wooboost-copy-key-snippet');
        if (!btn) return;
        btn.addEventListener('click', function(e){
            var text = "<?php echo esc_js($snippet); ?>";
            navigator.clipboard && navigator.clipboard.writeText(text).then(function(){
                btn.textContent = '<?php echo esc_js( __('Copied', 'wooboost') ); ?>';
                setTimeout(function(){ btn.textContent = '<?php echo esc_js( __('Copy to clipboard', 'wooboost') ); ?>'; }, 2000);
            }, function(){
                alert('<?php echo esc_js( __('Copy to clipboard failed. Please copy manually.', 'wooboost') ); ?>');
            });
        });
    })();
    </script>
    <?php
});
