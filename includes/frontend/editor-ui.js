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
         * DOM elements
         */
        elements: {
            button: null,
            showTab: null,
            editorContainer: null
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
            
            // Wait a bit for editor to load, then inject button
            this.waitForEditor();
        },
        
        /**
         * Wait for editor to be available and inject button
         */
        waitForEditor: function() {
            var self = this;
            var attempts = 0;
            var maxAttempts = 50; // 5 seconds
            
            function checkEditor() {
                attempts++;
                
                if (self.detectEditor() || attempts >= maxAttempts) {
                    if (self.elements.editorContainer) {
                        self.injectFloatingButton();
                    } else {
                        console.log('WooBoost: Could not find product description editor');
                    }
                    return;
                }
                
                // Try again in 100ms
                setTimeout(checkEditor, 100);
            }
            
            checkEditor();
        },
        
        /**
         * Detect the product description editor container
         */
        detectEditor: function() {
            // Try multiple selectors for different editor types
            var selectors = [
                // Classic editor
                '#postdivrich #wp-content-editor-container',
                '#postdivrich',
                // Block editor
                '.block-editor-writing-flow',
                '.edit-post-visual-editor',
                // TinyMCE editor
                '#wp-content-wrap',
                // Fallback: look for content textarea
                '#content'
            ];
            
            for (var i = 0; i < selectors.length; i++) {
                var $container = $(selectors[i]);
                if ($container.length > 0) {
                    this.elements.editorContainer = $container.first();
                    console.log('WooBoost: Found editor container using selector:', selectors[i]);
                    return true;
                }
            }
            
            return false;
        },
        
        /**
         * Inject the floating button
         */
        injectFloatingButton: function() {
            if (this.config.hideButton) {
                this.injectShowTab();
                return;
            }
            
            var $button = this.createFloatingButton();
            this.elements.button = $button;
            
            // Position the button relative to the editor container
            var $container = this.elements.editorContainer;
            
            // Make sure container has relative positioning
            if ($container.css('position') === 'static') {
                $container.css('position', 'relative');
            }
            
            // Append button to container
            $container.append($button);
            
            console.log('WooBoost: Floating button injected');
        },
        
        /**
         * Create the floating button element
         */
        createFloatingButton: function() {
            var $button = $('<button type="button" class="wooboost-floating-button" aria-label="' + 
                           this.config.strings.generateDescription + '">' +
                           this.getMagicWandSVG() +
                           '</button>');
            
            // Add click handler
            $button.on('click', this.onButtonClick.bind(this));
            
            // Add keyboard support
            $button.on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
            
            return $button;
        },
        
        /**
         * Create the show tab when button is hidden
         */
        injectShowTab: function() {
            var $tab = $('<button type="button" class="wooboost-show-tab" aria-label="Show WooBoost">' +
                        'Show WooBoost' +
                        '</button>');
            
            // Add click handler to show button
            $tab.on('click', this.onShowTabClick.bind(this));
            
            this.elements.showTab = $tab;
            
            // Position tab on right edge of editor
            var $container = this.elements.editorContainer;
            if ($container.css('position') === 'static') {
                $container.css('position', 'relative');
            }
            
            $container.append($tab);
            
            console.log('WooBoost: Show tab injected');
        },
        
        /**
         * Get magic wand SVG icon
         */
        getMagicWandSVG: function() {
            return '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
                   '<path d="M7.5 5.6L10 7 8.6 4.5 10 2 7.5 3.4 5 2l1.4 2.5L5 7zm12 9.8L17 14l1.4 2.5L17 19l2.5-1.4L22 19l-1.4-2.5L22 14zM22 2l-2.5 1.4L17 2l1.4 2.5L17 7l2.5-1.4L22 7l-1.4-2.5zm-7.63 5.29c-.39-.39-1.02-.39-1.41 0L1.29 18.96c-.39.39-.39 1.02 0 1.41s1.02.39 1.41 0L14.37 8.7c.39-.39.39-1.02 0-1.41z"/>' +
                   '</svg>';
        },
        
        /**
         * Handle button click
         */
        onButtonClick: function(e) {
            e.preventDefault();
            console.log('WooBoost: Button clicked');
            // TODO: In Step 5, open modal here
            alert('WooBoost button clicked! Modal will be implemented in Step 5.');
        },
        
        /**
         * Handle show tab click
         */
        onShowTabClick: function(e) {
            e.preventDefault();
            
            // Hide the tab and show the button
            if (this.elements.showTab) {
                this.elements.showTab.remove();
                this.elements.showTab = null;
            }
            
            // Update user preference (will be implemented in Step 9)
            // For now, just show the button
            this.config.hideButton = false;
            this.injectFloatingButton();
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