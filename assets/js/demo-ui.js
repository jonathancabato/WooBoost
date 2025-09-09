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