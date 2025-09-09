/**
 * WooBoost Demo User UI Scripts
 * 
 * JavaScript functionality for demo user restrictions
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Add demo user class to body
    $('body').addClass('demo-user');
    
    // Add demo user indicator
    addDemoUserIndicator();
    
    // Hide Delete/Trash buttons
    hideDeleteButtons();
    
    // Remove Quick Edit functionality
    removeQuickEdit();
    
    // Hide other UI elements that shouldn't be accessible
    hideRestrictedElements();
    
    /**
     * Add demo user indicator to admin
     */
    function addDemoUserIndicator() {
        var indicator = $('<div class="demo-user-indicator">DEMO MODE - Products Only</div>');
        
        // Adjust for admin bar
        if ($('#wpadminbar').length) {
            indicator.addClass('with-admin-bar');
        }
        
        $('body').append(indicator);
    }
    
    /**
     * Hide Delete/Trash buttons
     */
    function hideDeleteButtons() {
        // Hide delete action in post edit screen
        $('#delete-action').hide();
        
        // Hide move to trash link
        $('a[href*="action=trash"]').hide();
        
        // Hide bulk delete options
        $('.bulkactions option[value="trash"]').remove();
        
        // Hide individual row delete actions
        $('.post-type-product .row-actions .trash').hide();
    }
    
    /**
     * Remove Quick Edit functionality
     */
    function removeQuickEdit() {
        // Hide Quick Edit buttons
        $('.post-type-product .row-actions .inline').hide();
        
        // Disable quick edit functionality
        $('.editinline').remove();
        
        // Remove quick edit event handlers
        $(document).off('click', '.editinline');

        // Remove Duplicate action in product list
        $('.post-type-product .row-actions .duplicate').hide();
    }
    
    /**
     * Hide restricted UI elements
     */
    function hideRestrictedElements() {
        // Hide any remaining menu items that shouldn't be visible
        $('#menu-posts, #menu-pages, #menu-media, #menu-comments').hide();
        $('#menu-themes, #menu-plugins, #menu-users, #menu-tools').hide();
        $('#menu-settings, #menu-dashboard').hide();
        
        // Hide WooCommerce menu items except Products
        $('#toplevel_page_woocommerce').hide();
        
        // Hide screen options and help tabs
        $('#screen-options-link-wrap, #contextual-help-link-wrap').hide();
        
        // Hide admin bar items that might still be visible
        $('#wp-admin-bar-wp-logo, #wp-admin-bar-site-name').hide();
        $('#wp-admin-bar-dashboard, #wp-admin-bar-themes').hide();
        $('#wp-admin-bar-widgets, #wp-admin-bar-menus').hide();
        $('#wp-admin-bar-new-content, #wp-admin-bar-comments').hide();
        
        // Hide footer links
        $('#footer-thankyou, #footer-upgrade').hide();
    }
    
    /**
     * Show notification to user
     */
    function showNotification(message, type) {
        type = type || 'info';
        
        var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Add dismiss button functionality
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut();
        });
        
        // Insert after the first heading or at the top of the content
        if ($('.wrap h1').length) {
            $('.wrap h1').first().after(notice);
        } else {
            $('.wrap').prepend(notice);
        }
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
    }
});