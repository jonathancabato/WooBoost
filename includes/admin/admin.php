<?php
/**
 * WooBoost Admin Module
 * 
 * Handles all admin-related functionality
 *
 * @package WooBoost
 * @subpackage Includes/Admin
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include user access management
require_once WOOBOOST_PLUGIN_DIR . 'includes/admin/class-wooboost-user-access.php';

// Include capabilities test (for debugging)
require_once WOOBOOST_PLUGIN_DIR . 'includes/admin/capabilities-test.php';
