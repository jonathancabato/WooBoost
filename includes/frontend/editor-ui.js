/**
 * WooBoost Editor UI Scripts
 * 
 * JavaScript for the ChatGPT content generator in the product editor
 *
 * @package WooBoost
 * @subpackage Assets/JS
 * @since 1.0.0
 */

(function($) {
    'use strict';
    
    // Wait for DOM ready
    $(document).ready(function() {
        // Initialize WooBoost editor functionality
        if (typeof wooboostEditor !== 'undefined') {
            WooBoostEditor.init();
        }
    });
    
    /**
     * WooBoost Editor functionality
     */
    window.WooBoostEditor = {
        
        /**
         * Configuration
         */
        config: {
            restUrl: '',
            nonce: '',
            hideButton: false
        },
        
        /**
         * Initialize the editor functionality
         */
        init: function() {
            // Set configuration from localized data
            if (typeof wooboostEditor !== 'undefined') {
                this.config = $.extend(this.config, wooboostEditor);
            }
            
            console.log('WooBoost Editor initialized');
            console.log('REST URL:', this.config.restUrl);
            console.log('Hide button:', this.config.hideButton);
            
            // TODO: In Step 4, we'll inject the floating button here
            // TODO: In Step 5, we'll create the modal UI here
        },
        
        /**
         * Make REST API call
         */
        apiCall: function(endpoint, method, data) {
            method = method || 'GET';
            data = data || {};
            
            var requestConfig = {
                url: this.config.restUrl + endpoint,
                method: method,
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WooBoost-Nonce', WooBoostEditor.config.nonce);
                }
            };
            
            if (method === 'POST') {
                requestConfig.data = JSON.stringify(data);
                requestConfig.contentType = 'application/json';
            }
            
            return $.ajax(requestConfig);
        },
        
        /**
         * Test the models endpoint (for Step 3 verification)
         */
        testModelsEndpoint: function() {
            return this.apiCall('/models');
        },
        
        /**
         * Test the generate endpoint (for Step 3 verification)
         */
        testGenerateEndpoint: function() {
            var testData = {
                model: 'gpt-3.5-turbo',
                length: 'Medium',
                creativity: 'Medium',
                style: 'Formal',
                format: 'Plain text',
                product_data: {
                    title: 'Test Product'
                }
            };
            
            return this.apiCall('/generate', 'POST', testData);
        }
    };
    
})(jQuery);