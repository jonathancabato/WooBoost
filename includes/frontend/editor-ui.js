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
         * Allowed AI models for WooBoost
         * This array must match the backend model configuration
         * Confirmed existing and cost-effective models
         */
        allowedModels: {
            'gpt-5-mini': {
                id: 'gpt-5-mini',
                name: 'GPT-5 Mini',
                description: 'Fast and efficient model'
            },
            'gpt-5-nano': {
                id: 'gpt-5-nano',
                name: 'GPT-5 Nano',
                description: 'Ultra-fast and cost-effective (Default)'
            },
            'gpt-4o-mini': {
                id: 'gpt-4o-mini',
                name: 'GPT-4o Mini',
                description: 'Optimized GPT-4 model'
            },
            'gpt-4.1-nano': {
                id: 'gpt-4.1-nano',
                name: 'GPT-4.1 Nano',
                description: 'Latest GPT-4.1 nano variant'
            }
        },
        
        /**
         * Default model ID
         */
        defaultModel: 'gpt-5-nano',
        
        /**
         * Configuration
         */
        config: {
            restUrl: '',
            nonce: '',
            hideButton: false
        },
        
        /**
         * Editor state tracking - Step 7
         */
        editorState: {
            type: null, // 'tinymce', 'block', 'textarea'
            isReady: false,
            contentField: null,
            excerptField: null
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
         * Modal state tracking for improved UX
         */
        modalState: {
            current: 'idle', // 'idle', 'loading', 'generated'
            lastGeneratedData: null
        },
        
        /**
         * Model utility methods
         */
        modelUtils: {
            /**
             * Get array of allowed model IDs
             */
            getAllowedModelIds: function() {
                return Object.keys(WooBoostEditor.allowedModels);
            },
            
            /**
             * Validate if a model is allowed
             */
            isModelAllowed: function(modelId) {
                return WooBoostEditor.allowedModels.hasOwnProperty(modelId);
            },
            
            /**
             * Validate and sanitize model selection
             */
            validateModel: function(modelId) {
                if (!modelId || !this.isModelAllowed(modelId)) {
                    return WooBoostEditor.defaultModel;
                }
                return modelId;
            },
            
            /**
             * Generate HTML options for model select dropdown
             */
            generateModelOptions: function() {
                var options = '';
                for (var modelId in WooBoostEditor.allowedModels) {
                    var model = WooBoostEditor.allowedModels[modelId];
                    var selected = modelId === WooBoostEditor.defaultModel ? ' selected' : '';
                    options += '<option value="' + model.id + '"' + selected + '>' + 
                              model.name + ' - ' + model.description + '</option>';
                }
                return options;
            }
        },

        /**
         * API request management
         */
        apiRequestManager: {
            currentRequestId: null,
            restartTimer: null,
            
            generateRequestId: function() {
                return 'req_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            },
            
            setCurrentRequest: function(requestId) {
                this.currentRequestId = requestId;
            },
            
            isValidRequest: function(requestId) {
                return this.currentRequestId === requestId;
            },
            
            invalidateCurrentRequest: function() {
                this.currentRequestId = null;
                if (this.restartTimer) {
                    clearTimeout(this.restartTimer);
                    this.restartTimer = null;
                }
            }
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
         * Detect the product description editor container - Enhanced for Step 7
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
                    
                    // Determine and store editor type - Step 7
                    this.determineEditorType();
                    
                    return true;
                }
            }
            
            return false;
        },
        
        /**
         * Determine the active editor type and store state - Step 7
         */
        determineEditorType: function() {
            // Reset state
            this.editorState = {
                type: null,
                isReady: false,
                contentField: null,
                excerptField: null
            };
            
            // Check for Block Editor (Gutenberg)
            if (typeof wp !== 'undefined' && wp.data && wp.blocks && 
                (document.body.classList.contains('block-editor-page') || 
                 $('.block-editor-writing-flow').length > 0)) {
                this.editorState.type = 'block';
                this.editorState.isReady = true;
                console.log('WooBoost: Detected Block Editor (Gutenberg)');
            }
            // Check for TinyMCE Editor
            else if (typeof tinyMCE !== 'undefined' && 
                     ($('#wp-content-wrap').length > 0 || $('#postdivrich').length > 0)) {
                this.editorState.type = 'tinymce';
                // TinyMCE might not be ready yet, check if initialized
                if (tinyMCE.get('content')) {
                    this.editorState.isReady = true;
                    console.log('WooBoost: Detected TinyMCE Editor (ready)');
                } else {
                    console.log('WooBoost: Detected TinyMCE Editor (initializing...)');
                    // Wait for TinyMCE to initialize
                    this.waitForTinyMCE();
                }
            }
            // Fallback to textarea
            else if ($('#content').length > 0) {
                this.editorState.type = 'textarea';
                this.editorState.isReady = true;
                this.editorState.contentField = $('#content');
                console.log('WooBoost: Detected basic textarea editor');
            }
            
            // Always check for excerpt field
            if ($('#excerpt').length > 0) {
                this.editorState.excerptField = $('#excerpt');
                console.log('WooBoost: Found excerpt field');
            }
        },
        
        /**
         * Wait for TinyMCE to initialize - Step 7
         */
        waitForTinyMCE: function() {
            var self = this;
            var attempts = 0;
            var maxAttempts = 50; // 5 seconds
            
            function checkTinyMCE() {
                attempts++;
                
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                    self.editorState.isReady = true;
                    console.log('WooBoost: TinyMCE is now ready');
                    return;
                }
                
                if (attempts < maxAttempts) {
                    setTimeout(checkTinyMCE, 100);
                } else {
                    console.log('WooBoost: TinyMCE initialization timeout, falling back to textarea');
                    self.editorState.type = 'textarea';
                    self.editorState.isReady = true;
                    self.editorState.contentField = $('#content');
                }
            }
            
            checkTinyMCE();
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
                model: WooBoostEditor.defaultModel,
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
                                        this.modelUtils.generateModelOptions() +
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
                                        '<option value="HTML (Simplified)">HTML (Simplified) - Bold, italics</option>' +
                                        '<option value="HTML (Detailed)">HTML (Detailed) - Headers, lists, structure</option>' +
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
                        '<button type="button" class="wooboost-btn wooboost-btn-primary" id="wooboost-generate-btn">' +
                            '<span class="wooboost-btn-text">Generate Content</span>' +
                        '</button>' +
                        '<button type="button" class="wooboost-btn wooboost-btn-secondary" id="wooboost-cancel-btn">Cancel</button>' +
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
            
            // Reset modal state when opening
            this.setModalState('idle');
            
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
         * Set modal state and update UI accordingly
         */
        setModalState: function(newState) {
            console.log('WooBoost: Setting modal state to:', newState);
            this.modalState.current = newState;
            this.updateUIForState();
        },

        /**
         * Update UI elements based on current modal state
         */
        updateUIForState: function() {
            var $modal = this.elements.modal;
            var $contentOptions = $modal.find('.wooboost-form-section').first();
            var $modalFooter = $modal.find('.wooboost-modal-footer');
            var $generatedContent = $modal.find('.wooboost-generated-content');
            var $loadingState = $modal.find('.wooboost-loading-state');

            // Reset all states
            $contentOptions.show();
            $modalFooter.show();
            $generatedContent.hide();
            $loadingState.remove();

            switch (this.modalState.current) {
                case 'idle':
                    // Show Content Options and original buttons
                    $contentOptions.show();
                    $modalFooter.show();
                    $generatedContent.remove();
                    break;

                case 'loading':
                    // Hide Content Options and footer, show loading state
                    $contentOptions.hide();
                    $modalFooter.hide();
                    this.showLoadingState();
                    break;

                case 'generated':
                    // Hide Content Options and footer, show generated content
                    $contentOptions.hide();
                    $modalFooter.hide();
                    // Generated content will be shown by showGeneratedContent method
                    break;
            }
        },

        /**
         * Show loading state with spinner and cancel/restart options
         */
        showLoadingState: function() {
            var $modalBody = this.elements.modal.find('.wooboost-modal-body');
            
            var loadingHTML = '<div class="wooboost-loading-state">' +
                '<div class="wooboost-loading-content">' +
                    '<div class="wooboost-loading-spinner-large"></div>' +
                    '<div class="wooboost-loading-message">Generating content… please wait.</div>' +
                    '<div class="wooboost-loading-actions">' +
                        '<button type="button" class="wooboost-btn wooboost-btn-secondary wooboost-cancel-loading-btn" id="wooboost-cancel-loading-btn">Cancel</button>' +
                        '<a href="#" class="wooboost-restart-link" id="wooboost-restart-link" style="display: none;">Stuck? Restart.</a>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            // Remove existing loading state if any
            $modalBody.find('.wooboost-loading-state').remove();
            
            // Add loading state after the form
            $modalBody.find('#wooboost-generation-form').after(loadingHTML);
            
            // Attach event handlers
            var self = this;
            
            // Cancel button handler
            $('#wooboost-cancel-loading-btn').on('click', function(e) {
                e.preventDefault();
                self.cancelGeneration();
            });
            
            // Restart link handler
            $('#wooboost-restart-link').on('click', function(e) {
                e.preventDefault();
                self.restartGeneration();
            });
            
            // Show restart link after 20 seconds
            this.apiRequestManager.restartTimer = setTimeout(function() {
                $('#wooboost-restart-link').fadeIn(300);
            }, 20000);
        },

        /**
         * Cancel the current generation process
         */
        cancelGeneration: function() {
            console.log('WooBoost: Cancelling generation process');
            
            // Invalidate current request to ignore any incoming response
            this.apiRequestManager.invalidateCurrentRequest();
            
            // Reset to idle state
            this.setModalState('idle');
        },

        /**
         * Restart the generation process
         */
        restartGeneration: function() {
            console.log('WooBoost: Restarting generation process');
            
            // Invalidate current request to ignore any incoming response
            this.apiRequestManager.invalidateCurrentRequest();
            
            // Reset to idle state
            this.setModalState('idle');
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
            
            // Format change handler to update content preview
            $('#wooboost-format').on('change', function() {
                self.updateContentPreviewFormat();
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
            
            // Set loading state (this will hide content options and show spinner)
            this.setModalState('loading');
            
            // Make the API call
            this.generateContent(formData);
        },
        
        /**
         * Generate content via API - Step 6
         */
        generateContent: function(formData) {
            var self = this;
            
            // Generate a unique request ID for this API call
            var requestId = this.apiRequestManager.generateRequestId();
            this.apiRequestManager.setCurrentRequest(requestId);
            
            console.log('WooBoost: Starting API request with ID:', requestId);
            
            this.apiCall('/generate', 'POST', formData)
                .done(function(response) {
                    // Check if this response is still valid (user hasn't cancelled/restarted)
                    if (!self.apiRequestManager.isValidRequest(requestId)) {
                        console.log('WooBoost: Ignoring outdated API response for request:', requestId);
                        return;
                    }
                    
                    console.log('WooBoost: Content generated successfully', response);
                    
                    if (response.success && response.data) {
                        self.handleGenerationSuccess(response.data, requestId);
                    } else {
                        self.handleGenerationError('Invalid response format from server', requestId);
                    }
                })
                .fail(function(xhr, status, error) {
                    // Check if this response is still valid (user hasn't cancelled/restarted)
                    if (!self.apiRequestManager.isValidRequest(requestId)) {
                        console.log('WooBoost: Ignoring outdated API error for request:', requestId);
                        return;
                    }
                    
                    console.error('WooBoost: Content generation failed', xhr, status, error);
                    
                    var errorMessage = 'Content generation failed';
                    
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMessage = xhr.responseJSON.data.message;
                    } else if (error) {
                        errorMessage = 'Network error: ' + error;
                    }
                    
                    self.handleGenerationError(errorMessage, requestId);
                });
        },
        
        /**
         * Handle successful content generation - Step 6
         */
        handleGenerationSuccess: function(data, requestId) {
            console.log('WooBoost: Handling generation success for request:', requestId, data);
            
            // Store the generated data
            this.modalState.lastGeneratedData = data;
            
            // Clear the current request since it's completed
            this.apiRequestManager.invalidateCurrentRequest();
            
            // Set state to generated (this will hide loading and prepare for content display)
            this.setModalState('generated');
            
            // Show the generated content
            this.showGeneratedContent(data);
        },

        /**
         * Handle content generation error - Step 6
         */
        handleGenerationError: function(errorMessage, requestId) {
            console.error('WooBoost: Generation error for request:', requestId, errorMessage);
            
            // Clear the current request since it failed
            this.apiRequestManager.invalidateCurrentRequest();
            
            // Return to idle state to show the form again
            this.setModalState('idle');
            
            this.showErrors([errorMessage]);
            
            // Scroll to error message
            var $errorContainer = $('#wooboost-error-container');
            if ($errorContainer.length) {
                $errorContainer[0].scrollIntoView({ behavior: 'smooth' });
            }
        },        /**
         * Show generated content in modal - Step 6
         */
        showGeneratedContent: function(data) {
            var $modalBody = this.elements.modal.find('.wooboost-modal-body');
            
            // Get the selected format from the form
            var selectedFormat = $('#wooboost-format').val() || 'Plain text';
            var isHtmlFormat = selectedFormat === 'HTML (Simplified)' || selectedFormat === 'HTML (Detailed)';
            
            // Create content preview section
            var contentHTML = '<div class="wooboost-generated-content">' +
                '<h3 class="wooboost-form-section-title">Generated Content</h3>';
            
            if (data.excerpt) {
                var excerptContent = isHtmlFormat ? this.sanitizeHtml(data.excerpt) : this.escapeHtml(data.excerpt);
                
                contentHTML += '<div class="wooboost-content-section">' +
                    '<h4>Product Excerpt:</h4>' +
                    '<div class="wooboost-content-preview" data-content-type="' + (isHtmlFormat ? 'html' : 'text') + '">' + 
                    excerptContent + 
                    '</div>' +
                '</div>';
            }
            
            if (data.description) {
                var descriptionContent = isHtmlFormat ? this.sanitizeHtml(data.description) : this.escapeHtml(data.description);
                
                contentHTML += '<div class="wooboost-content-section">' +
                    '<h4>Product Description:</h4>' +
                    '<div class="wooboost-content-preview" data-content-type="' + (isHtmlFormat ? 'html' : 'text') + '">' + 
                    descriptionContent + 
                    '</div>' +
                '</div>';
            }
            
            // Add action buttons for Step 7
            contentHTML += '<div class="wooboost-content-actions">' +
                '<button type="button" class="wooboost-btn wooboost-btn-primary" id="wooboost-use-content-btn">Use This Content</button>' +
                '<button type="button" class="wooboost-btn wooboost-btn-secondary" id="wooboost-regenerate-btn">Regenerate</button>' +
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
         * Update content preview format when user changes the format selection
         */
        updateContentPreviewFormat: function() {
            var selectedFormat = $('#wooboost-format').val() || 'Plain text';
            var isHtmlFormat = selectedFormat === 'HTML (Simplified)' || selectedFormat === 'HTML (Detailed)';
            
            // Find all content preview elements that have generated content
            var $previews = $('.wooboost-content-preview[data-content-type]');
            
            if ($previews.length === 0) {
                return; // No generated content to update
            }
            
            var self = this;
            $previews.each(function() {
                var $preview = $(this);
                var currentType = $preview.attr('data-content-type');
                
                // Get the raw content stored in the lastGeneratedData
                var rawContent = null;
                if (self.modalState.lastGeneratedData) {
                    // Determine if this is excerpt or description based on the section title
                    var sectionTitle = $preview.closest('.wooboost-content-section').find('h4').text();
                    if (sectionTitle.indexOf('Excerpt') !== -1) {
                        rawContent = self.modalState.lastGeneratedData.excerpt;
                    } else if (sectionTitle.indexOf('Description') !== -1) {
                        rawContent = self.modalState.lastGeneratedData.description;
                    }
                }
                
                if (rawContent) {
                    // Update content type attribute
                    $preview.attr('data-content-type', isHtmlFormat ? 'html' : 'text');
                    
                    // Update content based on format selection
                    if (isHtmlFormat) {
                        $preview.html(self.sanitizeHtml(rawContent));
                    } else {
                        $preview.text(rawContent);
                    }
                }
            });
        },
        
        /**
         * Regenerate content - Step 6
         */
        regenerateContent: function() {
            console.log('WooBoost: Regenerating content');
            
            // Reset to idle state - this will show the Content Options again
            this.setModalState('idle');
            
            // Remove any generated content
            $('.wooboost-generated-content').remove();
            
            // Clear any previous errors
            this.clearErrors();
        },
        
        /**
         * Use generated content - Step 7: Content insertion into WordPress editors
         */
        useGeneratedContent: function(data) {
            var self = this;
            
            // Show confirmation dialog before inserting content
            this.showInsertionConfirmation(data, function() {
                try {
                    var insertionResults = self.insertContentIntoEditors(data);
                    
                    if (insertionResults.success) {
                        // Show success message
                        self.showInsertionSuccess(insertionResults);
                        self.hideModal();
                    } else {
                        // Show error message
                        self.showInsertionError(insertionResults.errors);
                    }
                } catch (error) {
                    console.error('WooBoost: Error inserting content:', error);
                    self.showInsertionError(['An unexpected error occurred while inserting content. Please try again.']);
                }
            });
        },
        
        /**
         * Show confirmation dialog for content insertion - Step 7
         */
        showInsertionConfirmation: function(data, callback) {
            var hasExistingContent = this.checkForExistingContent();
            
            if (hasExistingContent) {
                var message = 'This will replace the existing content in your product editor. Are you sure you want to continue?';
                
                // Temporarily hide the main modal
                this.elements.modalOverlay.removeClass('active');
                
                var self = this;
                this.showCustomConfirmation(message, 
                    function() {
                        // User confirmed - proceed with callback and hide main modal
                        callback();
                    },
                    function() {
                        // User cancelled - restore the main modal
                        self.elements.modalOverlay.addClass('active');
                    }
                );
            } else {
                // No existing content, proceed directly
                callback();
            }
        },

        /**
         * Show custom confirmation dialog
         */
        showCustomConfirmation: function(message, onConfirm, onCancel) {
            var self = this;
            
            // Remove existing confirmation if any
            $('.wooboost-confirmation-overlay').remove();
            
            var confirmationHTML = '<div class="wooboost-confirmation-overlay">' +
                '<div class="wooboost-confirmation-modal">' +
                    '<div class="wooboost-confirmation-header">' +
                        '<div class="wooboost-confirmation-icon">' +
                            '<svg viewBox="0 0 24 24" fill="currentColor">' +
                                '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>' +
                            '</svg>' +
                        '</div>' +
                        '<h3 class="wooboost-confirmation-title">Confirm Action</h3>' +
                    '</div>' +
                    '<div class="wooboost-confirmation-body">' +
                        '<p class="wooboost-confirmation-message">' + this.escapeHtml(message) + '</p>' +
                    '</div>' +
                    '<div class="wooboost-confirmation-footer">' +
                        '<button type="button" class="wooboost-btn wooboost-btn-secondary wooboost-confirmation-cancel">Cancel</button>' +
                        '<button type="button" class="wooboost-btn wooboost-btn-primary wooboost-confirmation-confirm">Continue</button>' +
                    '</div>' +
                '</div>' +
            '</div>';
            
            // Add to body
            $('body').append(confirmationHTML);
            
            var $overlay = $('.wooboost-confirmation-overlay');
            var $modal = $('.wooboost-confirmation-modal');
            
            // Show with animation
            setTimeout(function() {
                $overlay.addClass('wooboost-confirmation-active');
            }, 50);
            
            // Prevent body scroll
            $('body').addClass('wooboost-modal-open');
            
            // Event handlers
            function cleanup() {
                $overlay.removeClass('wooboost-confirmation-active');
                $('body').removeClass('wooboost-modal-open');
                setTimeout(function() {
                    $overlay.remove();
                }, 300);
            }
            
            // Confirm button
            $overlay.find('.wooboost-confirmation-confirm').on('click', function() {
                cleanup();
                if (onConfirm) onConfirm();
            });
            
            // Cancel button
            $overlay.find('.wooboost-confirmation-cancel').on('click', function() {
                cleanup();
                if (onCancel) onCancel();
            });
            
            // Overlay click to cancel
            $overlay.on('click', function(e) {
                if (e.target === $overlay[0]) {
                    cleanup();
                    if (onCancel) onCancel();
                }
            });
            
            // Escape key to cancel
            $(document).on('keydown.wooboost-confirmation', function(e) {
                if (e.key === 'Escape') {
                    $(document).off('keydown.wooboost-confirmation');
                    cleanup();
                    if (onCancel) onCancel();
                }
            });
            
            // Focus the confirm button
            $overlay.find('.wooboost-confirmation-confirm').focus();
        },
        
        /**
         * Check if there's existing content that would be replaced - Step 7
         */
        checkForExistingContent: function() {
            // Check main content field
            var $content = $('#content');
            if ($content.length && $content.val().trim().length > 0) {
                return true;
            }
            
            // Check TinyMCE if available
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('content')) {
                var editor = tinyMCE.get('content');
                if (editor && editor.getContent().trim().length > 0) {
                    return true;
                }
            }
            
            // Check Block Editor if available
            if (typeof wp !== 'undefined' && wp.data) {
                try {
                    var editor = wp.data.select('core/editor');
                    if (editor) {
                        var blocks = editor.getBlocks();
                        if (blocks && blocks.length > 0) {
                            // Check if any block has meaningful content
                            for (var i = 0; i < blocks.length; i++) {
                                if (blocks[i].attributes && 
                                    Object.keys(blocks[i].attributes).length > 0) {
                                    return true;
                                }
                            }
                        }
                    }
                } catch (e) {
                    // Ignore errors, assume no content
                }
            }
            
            return false;
        },
        
        /**
         * Insert content into appropriate WordPress editors - Step 7
         */
        insertContentIntoEditors: function(data) {
            var results = {
                success: false,
                inserted: [],
                errors: []
            };
            
            // Insert description into main content editor
            if (data.description) {
                var descriptionResult = this.insertIntoMainEditor(data.description);
                if (descriptionResult.success) {
                    results.inserted.push('Product description');
                } else {
                    results.errors.push('Failed to insert description: ' + descriptionResult.error);
                }
            }
            
            // Insert excerpt into excerpt field
            if (data.excerpt) {
                var excerptResult = this.insertIntoExcerptField(data.excerpt);
                if (excerptResult.success) {
                    results.inserted.push('Product excerpt');
                } else {
                    results.errors.push('Failed to insert excerpt: ' + excerptResult.error);
                }
            }
            
            // Consider it successful if at least one insertion worked
            results.success = results.inserted.length > 0;
            
            return results;
        },
        
        /**
         * Insert content into the main content editor - Enhanced Step 7
         */
        insertIntoMainEditor: function(content) {
            // Check if we have detected editor state
            if (!this.editorState.type) {
                this.determineEditorType();
            }
            
            // Use detected editor type for more reliable insertion
            switch (this.editorState.type) {
                case 'block':
                    return this.insertIntoBlockEditor(content);
                case 'tinymce':
                    return this.insertIntoTinyMCE(content);
                case 'textarea':
                    return this.insertIntoTextarea(content);
                default:
                    return this.insertIntoEditorFallback(content);
            }
        },
        
        /**
         * Insert content into Block Editor (Gutenberg) - Step 7
         */
        insertIntoBlockEditor: function(content) {
            try {
                if (typeof wp === 'undefined' || !wp.data || !wp.blocks) {
                    throw new Error('Block Editor API not available');
                }
                
                var editor = wp.data.select('core/editor');
                if (!editor || typeof wp.data.dispatch !== 'function') {
                    throw new Error('Block Editor not ready');
                }
                
                // Convert content to blocks
                var blocks;
                if (content.indexOf('<') !== -1) {
                    // Rich text content - convert HTML to blocks
                    blocks = wp.blocks.parse(content);
                } else {
                    // Plain text - create paragraph block
                    blocks = [wp.blocks.createBlock('core/paragraph', { content: content })];
                }
                
                wp.data.dispatch('core/editor').resetBlocks(blocks);
                return { success: true, method: 'Block Editor' };
                
            } catch (e) {
                console.warn('WooBoost: Block Editor insertion failed:', e);
                return { success: false, error: e.message };
            }
        },
        
        /**
         * Insert content into TinyMCE Editor - Step 7
         */
        insertIntoTinyMCE: function(content) {
            try {
                if (typeof tinyMCE === 'undefined') {
                    throw new Error('TinyMCE not available');
                }
                
                var editor = tinyMCE.get('content');
                if (!editor) {
                    throw new Error('TinyMCE editor not found');
                }
                
                if (editor.isHidden()) {
                    // Editor is in text mode, insert into textarea
                    var $textarea = $('#content');
                    if ($textarea.length) {
                        $textarea.val(content);
                        $textarea.trigger('change');
                        return { success: true, method: 'TinyMCE (Text Mode)' };
                    } else {
                        throw new Error('Content textarea not found');
                    }
                } else {
                    // Editor is in visual mode
                    editor.setContent(content);
                    editor.save(); // Save to underlying textarea
                    return { success: true, method: 'TinyMCE (Visual Mode)' };
                }
                
            } catch (e) {
                console.warn('WooBoost: TinyMCE insertion failed:', e);
                return { success: false, error: e.message };
            }
        },
        
        /**
         * Insert content into basic textarea - Step 7
         */
        insertIntoTextarea: function(content) {
            try {
                var $contentField = this.editorState.contentField || $('#content');
                if (!$contentField || !$contentField.length) {
                    throw new Error('Content textarea not found');
                }
                
                $contentField.val(content);
                $contentField.trigger('change');
                return { success: true, method: 'Textarea' };
                
            } catch (e) {
                console.warn('WooBoost: Textarea insertion failed:', e);
                return { success: false, error: e.message };
            }
        },
        
        /**
         * Fallback content insertion method - Step 7
         */
        insertIntoEditorFallback: function(content) {
            // Try all methods in sequence until one works
            var methods = [
                { name: 'TinyMCE', func: this.insertIntoTinyMCE.bind(this) },
                { name: 'Block Editor', func: this.insertIntoBlockEditor.bind(this) },
                { name: 'Textarea', func: this.insertIntoTextarea.bind(this) }
            ];
            
            for (var i = 0; i < methods.length; i++) {
                var result = methods[i].func(content);
                if (result.success) {
                    return result;
                }
            }
            
            return { 
                success: false, 
                error: 'No compatible editor found. Please ensure you are on a product edit page.' 
            };
        },
        
        /**
         * Insert content into excerpt field - Enhanced Step 7
         */
        insertIntoExcerptField: function(excerpt) {
            // Use cached excerpt field if available
            var $excerptField = this.editorState.excerptField || $('#excerpt');
            
            if (!$excerptField || !$excerptField.length) {
                return { 
                    success: false, 
                    error: 'Excerpt field not found. You may need to enable it in Screen Options.' 
                };
            }
            
            try {
                $excerptField.val(excerpt);
                $excerptField.trigger('change');
                
                // Visual feedback - briefly highlight the field
                this.highlightField($excerptField);
                
                return { success: true, method: 'Textarea' };
            } catch (e) {
                return { 
                    success: false, 
                    error: 'Failed to insert into excerpt field: ' + e.message 
                };
            }
        },
        
        /**
         * Provide visual feedback by highlighting a field - Step 7
         */
        highlightField: function($field) {
            if (!$field || !$field.length) return;
            
            var originalBg = $field.css('background-color');
            $field.css('background-color', '#d1edff');
            
            setTimeout(function() {
                $field.css('background-color', originalBg);
            }, 1000);
        },
        
        /**
         * Show successful insertion feedback - Enhanced Step 7
         */
        showInsertionSuccess: function(results) {
            var message = 'Your content has been inserted successfully!';
            
            // Provide visual feedback on the inserted fields
            this.highlightInsertedContent();
            
            // Show custom success notification
            this.showCustomNotification(message, 'success');
        },

        /**
         * Show custom notification toast - replaces WordPress admin notice
         */
        showCustomNotification: function(message, type) {
            type = type || 'info';
            
            // Remove existing notifications
            $('.wooboost-notification').remove();
            
            var iconSvg = '';
            if (type === 'success') {
                iconSvg = '<svg class="wooboost-notification-icon" viewBox="0 0 20 20" fill="currentColor">' +
                         '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>';
            } else if (type === 'error') {
                iconSvg = '<svg class="wooboost-notification-icon" viewBox="0 0 20 20" fill="currentColor">' +
                         '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>';
            }
            
            var $notification = $('<div class="wooboost-notification wooboost-notification-' + type + '">' +
                '<div class="wooboost-notification-content">' +
                    iconSvg +
                    '<span class="wooboost-notification-message">' + this.escapeHtml(message) + '</span>' +
                    '<button type="button" class="wooboost-notification-close" aria-label="Close notification">&times;</button>' +
                '</div>' +
            '</div>');
            
            // Add to body
            $('body').append($notification);
            
            // Animate in
            setTimeout(function() {
                $notification.addClass('wooboost-notification-visible');
            }, 50);
            
            // Auto-dismiss after 4 seconds for success notifications
            var autoDismissTimer;
            if (type === 'success') {
                autoDismissTimer = setTimeout(function() {
                    dismissNotification();
                }, 4000);
            }
            
            // Close button handler
            function dismissNotification() {
                if (autoDismissTimer) {
                    clearTimeout(autoDismissTimer);
                }
                $notification.removeClass('wooboost-notification-visible');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }
            
            $notification.find('.wooboost-notification-close').on('click', dismissNotification);
            
            // Click notification to dismiss
            $notification.on('click', function(e) {
                if (e.target === $notification[0] || e.target === $notification.find('.wooboost-notification-content')[0]) {
                    dismissNotification();
                }
            });
        },
        
        /**
         * Highlight inserted content fields - Step 7
         */
        highlightInsertedContent: function() {
            // Highlight the main content editor area
            var $contentArea;
            
            switch (this.editorState.type) {
                case 'tinymce':
                    $contentArea = $('#wp-content-wrap');
                    break;
                case 'block':
                    $contentArea = $('.block-editor-writing-flow');
                    break;
                case 'textarea':
                default:
                    $contentArea = $('#content');
                    break;
            }
            
            if ($contentArea && $contentArea.length) {
                this.highlightField($contentArea);
            }
            
            // Highlight excerpt field if it exists
            if (this.editorState.excerptField) {
                this.highlightField(this.editorState.excerptField);
            }
        },
        
        /**
         * Show insertion error feedback - Step 7
         */
        showInsertionError: function(errors) {
            var message = 'Failed to insert content';
            if (errors.length > 0) {
                message += ': ' + errors.join(', ');
            }
            
            // Show custom error notification
            this.showCustomNotification(message, 'error');
        },
        
        /**
         * Show WordPress-style admin notice - Step 7
         */
        showAdminNotice: function(message, type) {
            type = type || 'info';
            
            var noticeClass = 'notice notice-' + type;
            if (type === 'success') {
                noticeClass += ' is-dismissible';
            }
            
            var $notice = $('<div class="' + noticeClass + '" style="margin: 20px 0;"><p><strong>WooBoost:</strong> ' + 
                          message.replace(/\n/g, '<br>') + '</p></div>');
            
            // Insert notice after the first .wrap or at top of content
            var $target = $('.wrap').first();
            if (!$target.length) {
                $target = $('#wpbody-content').first();
            }
            
            if ($target.length) {
                $target.after($notice);
                
                // Auto-remove success notices after 5 seconds
                if (type === 'success') {
                    setTimeout(function() {
                        $notice.fadeOut(300, function() {
                            $notice.remove();
                        });
                    }, 5000);
                }
                
                // Scroll notice into view
                $notice[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                // Fallback to alert if we can't find a good place for the notice
                alert('WooBoost: ' + message.replace(/<br>/g, '\n'));
            }
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
         * Sanitize HTML content to prevent XSS attacks
         * Uses a whitelist approach for allowed tags and attributes
         */
        sanitizeHtml: function(html) {
            if (!html || typeof html !== 'string') {
                return '';
            }

            // Normalize whitespace and clean up the HTML first
            var cleanedHtml = html
                .replace(/\s+/g, ' ')  // Replace multiple whitespace with single space
                .replace(/>\s+</g, '><')  // Remove spaces between tags
                .trim();

            // Create a temporary container
            var container = document.createElement('div');
            container.innerHTML = cleanedHtml;

            // Define allowed tags and their allowed attributes
            var allowedTags = {
                'p': [],
                'br': [],
                'b': [],
                'strong': [],
                'i': [],
                'em': [],
                'u': [],
                'h1': [],
                'h2': [],
                'h3': [],
                'h4': [],
                'h5': [],
                'h6': [],
                'ul': [],
                'ol': [],
                'li': [],
                'a': ['href', 'rel', 'target'],
                'img': ['src', 'alt', 'width', 'height']
            };

            // Helper function to recursively clean nodes
            var cleanNode = function(node) {
                if (node.nodeType === Node.TEXT_NODE) {
                    return node;
                }

                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return null;
                }

                var tagName = node.tagName.toLowerCase();
                
                // Check if tag is allowed
                if (!allowedTags.hasOwnProperty(tagName)) {
                    // For disallowed tags, preserve their text content
                    var textNode = document.createTextNode(node.textContent || '');
                    return textNode;
                }

                // Create new clean element
                var cleanElement = document.createElement(tagName);
                var allowedAttrs = allowedTags[tagName];

                // Copy allowed attributes
                for (var i = 0; i < node.attributes.length; i++) {
                    var attr = node.attributes[i];
                    var attrName = attr.name.toLowerCase();
                    
                    if (allowedAttrs.indexOf(attrName) !== -1) {
                        var attrValue = attr.value;
                        
                        // Additional validation for specific attributes
                        if (attrName === 'href') {
                            // Only allow http, https, and mailto links
                            if (/^(https?:|mailto:|#)/.test(attrValue)) {
                                cleanElement.setAttribute(attrName, attrValue);
                            }
                        } else if (attrName === 'src') {
                            // Only allow http and https for images
                            if (/^https?:/.test(attrValue)) {
                                cleanElement.setAttribute(attrName, attrValue);
                            }
                        } else if (attrName === 'target') {
                            // Only allow _blank
                            if (attrValue === '_blank') {
                                cleanElement.setAttribute(attrName, attrValue);
                                // Always add rel="noopener noreferrer" for security
                                cleanElement.setAttribute('rel', 'noopener noreferrer');
                            }
                        } else {
                            cleanElement.setAttribute(attrName, attrValue);
                        }
                    }
                }

                // Recursively clean child nodes
                for (var j = 0; j < node.childNodes.length; j++) {
                    var cleanChild = cleanNode(node.childNodes[j]);
                    if (cleanChild) {
                        cleanElement.appendChild(cleanChild);
                    }
                }

                return cleanElement;
            };

            // Clean all child nodes
            var cleanContainer = document.createElement('div');
            for (var i = 0; i < container.childNodes.length; i++) {
                var cleanChild = cleanNode(container.childNodes[i]);
                if (cleanChild) {
                    cleanContainer.appendChild(cleanChild);
                }
            }

            return cleanContainer.innerHTML;
        },
        
        /**
         * Get form data - Step 5
         */
        getFormData: function() {
            var $form = $('#wooboost-generation-form');
            var productData = this.extractProductData();
            
            // Validate and sanitize the model selection
            var requestedModel = $form.find('#wooboost-model').val();
            var validatedModel = this.modelUtils.validateModel(requestedModel);
            
            // Log model validation for debugging
            if (requestedModel !== validatedModel) {
                console.warn('WooBoost: Invalid model "' + requestedModel + '" requested, using "' + validatedModel + '"');
            }
            
            return {
                model: validatedModel,
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
            } else if (!this.modelUtils.isModelAllowed(formData.model)) {
                errors.push('Invalid AI model selected');
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