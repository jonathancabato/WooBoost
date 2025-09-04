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
            editorContainer: null,
            modal: null,
            modalOverlay: null
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
            this.openModal();
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
            
            console.log('WooBoost: Making API call', {
                endpoint: endpoint,
                method: method,
                data: data,
                restUrl: this.config.restUrl,
                nonce: this.config.nonce
            });
            
            var requestConfig = {
                url: this.config.restUrl + endpoint,
                method: method,
                xhrFields: {
                    withCredentials: true
                },
                beforeSend: function(xhr) {
                    console.log('WooBoost: Setting request headers');
                    xhr.setRequestHeader('X-WooBoost-Nonce', WooBoostEditor.config.nonce);
                    xhr.setRequestHeader('X-WP-Nonce', WooBoostEditor.config.nonce);
                }
            };
            
            if (method === 'POST') {
                requestConfig.data = JSON.stringify(data);
                requestConfig.contentType = 'application/json';
                console.log('WooBoost: POST request data', requestConfig.data);
            }
            
            var ajaxPromise = $.ajax(requestConfig);
            
            ajaxPromise.done(function(response, textStatus, jqXHR) {
                console.log('WooBoost: API call successful', {
                    response: response,
                    status: textStatus,
                    headers: jqXHR.getAllResponseHeaders()
                });
            });
            
            ajaxPromise.fail(function(jqXHR, textStatus, errorThrown) {
                console.error('WooBoost: API call failed', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    responseText: jqXHR.responseText,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    headers: jqXHR.getAllResponseHeaders()
                });
            });
            
            return ajaxPromise;
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
        },
        
        /**
         * Open the generation modal - Step 5
         */
        openModal: function() {
            if (this.elements.modal) {
                this.showModal();
                return;
            }
            
            this.createModal();
            this.showModal();
        },
        
        /**
         * Create the modal HTML - Step 5
         */
        createModal: function() {
            var productData = this.extractProductData();
            
            var modalHTML = '<div class="wooboost-modal-overlay" id="wooboost-modal-overlay">' +
                '<div class="wooboost-modal" role="dialog" aria-labelledby="wooboost-modal-title" aria-modal="true">' +
                    '<div class="wooboost-modal-header">' +
                        '<h2 class="wooboost-modal-title" id="wooboost-modal-title">Generate Product Content</h2>' +
                        '<button type="button" class="wooboost-modal-close" aria-label="Close modal">&times;</button>' +
                    '</div>' +
                    '<div class="wooboost-modal-body">' +
                        '<form id="wooboost-generation-form">' +
                            '<div class="wooboost-form-section">' +
                                '<h3 class="wooboost-form-section-title">Content Options</h3>' +
                                
                                '<div class="wooboost-form-row">' +
                                    '<label class="wooboost-form-label" for="wooboost-model">AI Model</label>' +
                                    '<select class="wooboost-form-select" id="wooboost-model" name="model">' +
                                        '<option value="gpt-3.5-turbo">GPT-3.5 Turbo (Faster)</option>' +
                                        '<option value="gpt-4">GPT-4 (Higher Quality)</option>' +
                                        '<option value="gpt-4-turbo-preview">GPT-4 Turbo (Latest)</option>' +
                                    '</select>' +
                                    '<div class="wooboost-form-help">Choose the AI model for content generation</div>' +
                                '</div>' +
                                
                                '<div class="wooboost-form-row">' +
                                    '<label class="wooboost-form-label" for="wooboost-length">Content Length</label>' +
                                    '<select class="wooboost-form-select" id="wooboost-length" name="length">' +
                                        '<option value="Small">Small (Brief description)</option>' +
                                        '<option value="Medium" selected>Medium (Balanced)</option>' +
                                        '<option value="Large">Large (Comprehensive)</option>' +
                                        '<option value="Detailed">Detailed (Extensive)</option>' +
                                    '</select>' +
                                    '<div class="wooboost-form-help">Choose how much content to generate</div>' +
                                '</div>' +
                                
                                '<div class="wooboost-form-row">' +
                                    '<label class="wooboost-form-label" for="wooboost-creativity">Creativity Level</label>' +
                                    '<select class="wooboost-form-select" id="wooboost-creativity" name="creativity">' +
                                        '<option value="Low">Low (Conservative)</option>' +
                                        '<option value="Medium" selected>Medium (Balanced)</option>' +
                                        '<option value="High">High (Creative)</option>' +
                                        '<option value="Max">Max (Very Creative)</option>' +
                                    '</select>' +
                                    '<div class="wooboost-form-help">Control how creative the AI should be</div>' +
                                '</div>' +
                                
                                '<div class="wooboost-form-row">' +
                                    '<label class="wooboost-form-label" for="wooboost-style">Writing Style</label>' +
                                    '<select class="wooboost-form-select" id="wooboost-style" name="style">' +
                                        '<option value="Formal" selected>Formal (Professional)</option>' +
                                        '<option value="Casual">Casual (Friendly)</option>' +
                                        '<option value="Persuasive">Persuasive (Sales-focused)</option>' +
                                        '<option value="Creative">Creative (Unique)</option>' +
                                    '</select>' +
                                    '<div class="wooboost-form-help">Choose the tone and style of writing</div>' +
                                '</div>' +
                                
                                '<div class="wooboost-form-row">' +
                                    '<label class="wooboost-form-label" for="wooboost-format">Output Format</label>' +
                                    '<select class="wooboost-form-select" id="wooboost-format" name="format">' +
                                        '<option value="Plain text" selected>Plain text</option>' +
                                        '<option value="Rich typography">Rich typography (Bold, italics)</option>' +
                                    '</select>' +
                                    '<div class="wooboost-form-help">Choose how to format the generated content</div>' +
                                '</div>' +
                            '</div>' +
                            
                            '<div class="wooboost-form-section">' +
                                '<h3 class="wooboost-form-section-title">Product Information</h3>' +
                                '<div class="wooboost-product-info">' +
                                    '<div class="wooboost-product-info-title">Current Product Data</div>' +
                                    '<div class="wooboost-product-info-item"><strong>Title:</strong> ' + (productData.title || 'Not set') + '</div>' +
                                    '<div class="wooboost-product-info-item"><strong>Categories:</strong> ' + (productData.categories || 'None') + '</div>' +
                                    '<div class="wooboost-product-info-item"><strong>Tags:</strong> ' + (productData.tags || 'None') + '</div>' +
                                    '<div class="wooboost-product-info-item"><strong>Price:</strong> ' + (productData.price || 'Not set') + '</div>' +
                                '</div>' +
                                '<div class="wooboost-form-help">This information will be used to generate relevant content for your product.</div>' +
                            '</div>' +
                            
                            '<div id="wooboost-error-container"></div>' +
                        '</form>' +
                    '</div>' +
                    '<div class="wooboost-modal-footer">' +
                        '<button type="button" class="wooboost-btn wooboost-btn-secondary" id="wooboost-cancel-btn">Cancel</button>' +
                        '<button type="button" class="wooboost-btn wooboost-btn-primary" id="wooboost-generate-btn">' +
                            '<span class="wooboost-btn-text">Generate Content</span>' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            // Append to body
            $('body').append(modalHTML);
            
            // Store references
            this.elements.modalOverlay = $('#wooboost-modal-overlay');
            this.elements.modal = this.elements.modalOverlay.find('.wooboost-modal');
            
            // Attach event handlers
            this.attachModalEvents();
        },
        
        /**
         * Show the modal - Step 5
         */
        showModal: function() {
            this.elements.modalOverlay.addClass('active');
            
            // Focus management
            this.elements.modal.find('#wooboost-model').focus();
            
            // Prevent body scroll
            $('body').addClass('wooboost-modal-open');
            
            // Add style to prevent body scroll
            if (!$('#wooboost-modal-style').length) {
                $('<style id="wooboost-modal-style">.wooboost-modal-open { overflow: hidden; }</style>').appendTo('head');
            }
        },
        
        /**
         * Hide the modal - Step 5
         */
        hideModal: function() {
            this.elements.modalOverlay.removeClass('active');
            
            // Restore body scroll
            $('body').removeClass('wooboost-modal-open');
            
            // Return focus to button
            if (this.elements.button) {
                this.elements.button.focus();
            }
            
            // Clean up event listeners
            $(document).off('keydown.wooboost-modal');
        },
        
        /**
         * Attach modal event handlers - Step 5
         */
        attachModalEvents: function() {
            var self = this;
            
            // Close button
            this.elements.modal.find('.wooboost-modal-close').on('click', function() {
                self.hideModal();
            });
            
            // Cancel button
            $('#wooboost-cancel-btn').on('click', function() {
                self.hideModal();
            });
            
            // Generate button
            $('#wooboost-generate-btn').on('click', function() {
                self.handleGenerateClick();
            });
            
            // Overlay click to close
            this.elements.modalOverlay.on('click', function(e) {
                if (e.target === self.elements.modalOverlay[0]) {
                    self.hideModal();
                }
            });
            
            // Escape key to close
            $(document).on('keydown.wooboost-modal', function(e) {
                if (e.key === 'Escape') {
                    self.hideModal();
                }
            });
            
            // Trap focus in modal
            this.trapFocus();
        },
        
        /**
         * Handle generate button click - Step 6
         */
        handleGenerateClick: function() {
            console.log('WooBoost: Generate button clicked');
            
            // Get form data
            var formData = this.getFormData();
            
            // Validate form
            if (!this.validateForm(formData)) {
                return;
            }
            
            // Clear any previous errors
            this.clearErrors();
            
            // Show loading state
            this.setGenerateLoading(true);
            
            // Make the API call
            this.generateContent(formData);
        },
        
        /**
         * Generate content via API - Step 6
         */
        generateContent: function(formData) {
            var self = this;
            
            this.apiCall('/generate', 'POST', formData)
                .done(function(response) {
                    console.log('WooBoost: Content generated successfully', response);
                    
                    if (response.success && response.data) {
                        self.handleGenerationSuccess(response.data);
                    } else {
                        self.handleGenerationError('Invalid response format from server');
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('WooBoost: Content generation failed', xhr, status, error);
                    
                    var errorMessage = 'Content generation failed';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (error) {
                        errorMessage = 'Network error: ' + error;
                    }
                    
                    self.handleGenerationError(errorMessage);
                })
                .always(function() {
                    self.setGenerateLoading(false);
                });
        },
        
        /**
         * Handle successful content generation - Step 6
         */
        handleGenerationSuccess: function(data) {
            console.log('WooBoost: Handling generation success', data);
            
            // For Step 6, we'll show the generated content in the modal
            // Step 7 will implement the actual editor insertion
            this.showGeneratedContent(data);
        },
        
        /**
         * Handle content generation error - Step 6
         */
        handleGenerationError: function(errorMessage) {
            console.error('WooBoost: Generation error:', errorMessage);
            
            this.showErrors([errorMessage]);
            
            // Scroll to error message
            var $errorContainer = $('#wooboost-error-container');
            if ($errorContainer.length) {
                $errorContainer[0].scrollIntoView({ behavior: 'smooth' });
            }
        },
        
        /**
         * Show generated content in modal - Step 6
         */
        showGeneratedContent: function(data) {
            var $modalBody = this.elements.modal.find('.wooboost-modal-body');
            
            // Create content preview section
            var contentHTML = '<div class="wooboost-generated-content">' +
                '<h3 class="wooboost-form-section-title">Generated Content</h3>';
            
            if (data.excerpt) {
                contentHTML += '<div class="wooboost-content-section">' +
                    '<h4>Product Excerpt:</h4>' +
                    '<div class="wooboost-content-preview">' + this.escapeHtml(data.excerpt) + '</div>' +
                '</div>';
            }
            
            if (data.description) {
                contentHTML += '<div class="wooboost-content-section">' +
                    '<h4>Product Description:</h4>' +
                    '<div class="wooboost-content-preview">' + this.escapeHtml(data.description) + '</div>' +
                '</div>';
            }
            
            // Add action buttons for Step 7
            contentHTML += '<div class="wooboost-content-actions">' +
                '<button type="button" class="wooboost-btn wooboost-btn-secondary" id="wooboost-regenerate-btn">Regenerate</button>' +
                '<button type="button" class="wooboost-btn wooboost-btn-primary" id="wooboost-use-content-btn">Use This Content</button>' +
            '</div>';
            
            contentHTML += '</div>';
            
            // Remove existing generated content if any
            $modalBody.find('.wooboost-generated-content').remove();
            
            // Append new content
            $modalBody.append(contentHTML);
            
            // Attach event handlers for new buttons
            var self = this;
            $('#wooboost-regenerate-btn').on('click', function() {
                self.regenerateContent();
            });
            
            $('#wooboost-use-content-btn').on('click', function() {
                self.useGeneratedContent(data);
            });
            
            // Scroll to generated content
            $('.wooboost-generated-content')[0].scrollIntoView({ behavior: 'smooth' });
        },
        
        /**
         * Regenerate content - Step 6
         */
        regenerateContent: function() {
            // Remove generated content section
            $('.wooboost-generated-content').remove();
            
            // Trigger generation again with current form data
            var formData = this.getFormData();
            if (this.validateForm(formData)) {
                this.clearErrors();
                this.setGenerateLoading(true);
                this.generateContent(formData);
            }
        },
        
        /**
         * Use generated content - Step 6 (placeholder for Step 7)
         */
        useGeneratedContent: function(data) {
            // Step 7 will implement the actual editor insertion
            // For now, just show a message and close modal
            alert('Step 6 Complete! Content generated successfully.\n\nStep 7 will implement inserting this content into the product editor.\n\nGenerated content:\n- Excerpt: ' + data.excerpt + '\n- Description: ' + data.description);
            this.hideModal();
        },
        
        /**
         * Escape HTML for safe display
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Get form data - Step 5
         */
        getFormData: function() {
            var $form = $('#wooboost-generation-form');
            var productData = this.extractProductData();
            
            return {
                model: $form.find('#wooboost-model').val(),
                length: $form.find('#wooboost-length').val(),
                creativity: $form.find('#wooboost-creativity').val(),
                style: $form.find('#wooboost-style').val(),
                format: $form.find('#wooboost-format').val(),
                product_data: productData
            };
        },
        
        /**
         * Validate form data - Step 5
         */
        validateForm: function(formData) {
            this.clearErrors();
            
            var errors = [];
            
            if (!formData.model) {
                errors.push('Please select an AI model');
            }
            
            if (!formData.length) {
                errors.push('Please select a content length');
            }
            
            if (!formData.product_data.title) {
                errors.push('Product title is required. Please set a title for your product first.');
            }
            
            if (errors.length > 0) {
                this.showErrors(errors);
                return false;
            }
            
            return true;
        },
        
        /**
         * Show validation errors - Step 5
         */
        showErrors: function(errors) {
            var errorHTML = '<div class="wooboost-error-message">';
            errorHTML += '<strong>Please fix the following errors:</strong><ul>';
            
            for (var i = 0; i < errors.length; i++) {
                errorHTML += '<li>' + errors[i] + '</li>';
            }
            
            errorHTML += '</ul></div>';
            
            $('#wooboost-error-container').html(errorHTML);
        },
        
        /**
         * Clear validation errors - Step 5
         */
        clearErrors: function() {
            $('#wooboost-error-container').empty();
        },
        
        /**
         * Set loading state for generate button - Step 5
         */
        setGenerateLoading: function(loading) {
            var $btn = $('#wooboost-generate-btn');
            var $text = $btn.find('.wooboost-btn-text');
            
            if (loading) {
                $btn.prop('disabled', true);
                $text.html('<span class="wooboost-loading-spinner"></span> Generating...');
            } else {
                $btn.prop('disabled', false);
                $text.html('Generate Content');
            }
        },
        
        /**
         * Extract product data from current page - Step 5
         */
        extractProductData: function() {
            var data = {};
            
            // Get product title
            var $title = $('#title');
            if ($title.length) {
                data.title = $title.val() || '';
            }
            
            // Get categories
            var categories = [];
            $('#product_catchecklist input:checked').each(function() {
                var $label = $('label[for="' + $(this).attr('id') + '"]');
                if ($label.length) {
                    categories.push($label.text().trim());
                }
            });
            data.categories = categories.join(', ');
            
            // Get tags
            var tags = [];
            $('.tagchecklist .screen-reader-text').each(function() {
                var tag = $(this).parent().text().replace($(this).text(), '').trim();
                if (tag) {
                    tags.push(tag);
                }
            });
            data.tags = tags.join(', ');
            
            // Get price
            var $price = $('#_regular_price');
            if ($price.length) {
                data.price = $price.val() || '';
            }
            
            // Get existing description for context
            var $content = $('#content');
            if ($content.length) {
                data.existing_description = $content.val() || '';
            }
            
            // Get short description
            var $excerpt = $('#excerpt');
            if ($excerpt.length) {
                data.short_description = $excerpt.val() || '';
            }
            
            return data;
        },
        
        /**
         * Trap focus within modal - Step 5
         */
        trapFocus: function() {
            var self = this;
            
            this.elements.modal.on('keydown', function(e) {
                if (e.key !== 'Tab') {
                    return;
                }
                
                var focusableElements = self.elements.modal.find('button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
                var firstElement = focusableElements.first();
                var lastElement = focusableElements.last();
                
                if (e.shiftKey) {
                    // Shift + Tab
                    if (document.activeElement === firstElement[0]) {
                        e.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    // Tab
                    if (document.activeElement === lastElement[0]) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            });
        }
    };
    
})(jQuery);