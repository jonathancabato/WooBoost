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
    
    // Hide Update button on product edit pages
    hideUpdateButton();
    
    // Hide Delete/Trash buttons
    hideDeleteButtons();
    
    // Remove Quick Edit functionality
    removeQuickEdit();
    
    // Prevent form submission for updates on existing products
    preventProductUpdates();
    
    /**
     * Add demo user indicator to admin
     */
    function addDemoUserIndicator() {
        var indicator = $('<div class="demo-user-indicator">DEMO MODE</div>');
        
        // Adjust for admin bar
        if ($('#wpadminbar').length) {
            indicator.addClass('with-admin-bar');
        }
        
        $('body').append(indicator);
    }
    
    /**
     * Hide Update button on product edit pages
     */
    function hideUpdateButton() {
        // Hide the main Update/Publish button
        $('#publishing-action #publish').hide();
        
        // Also hide the "Update" text in the publishing box
        $('#publishing-action .spinner').siblings('input[type="submit"]').hide();
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
    }
    
    /**
     * Prevent form submission for updates on existing products
     */
    function preventProductUpdates() {
        // Check if we're editing an existing product
        var postId = $('#post_ID').val();
        
        if (postId && postId !== '0') {
            // This is an existing product, prevent updates
            $('#post').on('submit', function(e) {
                e.preventDefault();
                
                // Show notification
                showNotification('Demo users cannot update existing products', 'error');
                
                return false;
            });
            
            // Also disable auto-save
            if (typeof autosave !== 'undefined') {
                autosave = function() {
                    // Disable autosave for demo users on existing products
                    return false;
                };
            }
        }
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
    
    // Additional security: Monitor for attempts to access restricted URLs
    $(document).on('click', 'a', function(e) {
        var href = $(this).attr('href');
        
        if (href && !isAllowedUrl(href)) {
            e.preventDefault();
            showNotification('Access to this page is restricted for demo users', 'error');
            return false;
        }
    });
    
    /**
     * Check if URL is allowed for demo users
     */
    function isAllowedUrl(url) {
        // List of allowed URL patterns
        var allowedPatterns = [
            /index\.php/, // Dashboard
            /edit\.php\?post_type=product/,
            /post-new\.php\?post_type=product/,
            /post\.php.*post_type=product/,
            /post\.php.*post=\d+/, // Edit existing posts (will be validated server-side)
            /profile\.php/, // User profile
            /user-edit\.php/, // Edit user profile
            /admin-ajax\.php/,
            /admin-post\.php/,
            /#/, // Anchor links
            /javascript:/, // JavaScript links
        ];
        
        // Check if URL matches any allowed pattern
        for (var i = 0; i < allowedPatterns.length; i++) {
            if (allowedPatterns[i].test(url)) {
                return true;
            }
        }
        
        // Check if it's an external link
        if (url.indexOf('http') === 0 && url.indexOf(window.location.hostname) === -1) {
            return true; // Allow external links
        }
        
        return false;
    }
});