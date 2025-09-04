<?php
/**
 * WooBoost Main Include File
 * 
 * Central file to load all modules and functionality
 *
 * @package WooBoost
 * @subpackage Includes
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooBoost_Core class
 * 
 * Core functionality loader
 */
class WooBoost_Core {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        $this->load_modules();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Future hooks here
    }
    
    /**
     * Load all modules
     */
    private function load_modules() {
        $this->load_core_modules();
        // Future module loading here
        // $this->load_admin_modules();
        // $this->load_frontend_modules();
    }
    
    /**
     * Load admin modules
     */
    private function load_admin_modules() {
        // Future admin module loading
    }
    
    /**
     * Load frontend modules
     */
    private function load_frontend_modules() {
        // Future frontend module loading
    }
    
    /**
     * Load core modules
     */
    private function load_core_modules() {
        // Load OpenAI client
        if (file_exists(WOOBOOST_PLUGIN_DIR . 'includes/core/class-wooboost-openai.php')) {
            require_once WOOBOOST_PLUGIN_DIR . 'includes/core/class-wooboost-openai.php';
        }
    }
}

// Initialize core
new WooBoost_Core();
