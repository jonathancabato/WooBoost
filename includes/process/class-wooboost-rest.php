<?php
/**
 * WooBoost REST API Controller
 * 
 * Handles REST API endpoints for ChatGPT content generation
 *
 * @package WooBoost
 * @subpackage Includes/Process
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooBoost_REST class
 * 
 * REST API controller for content generation
 */
class WooBoost_REST extends WP_REST_Controller {
    
    /**
     * API namespace
     */
    protected $namespace = 'wooboost/v1';
    
    /**
     * OpenAI client instance
     */
    private $openai;
    
    /**
     * Constructor
     */
    public function __construct() {
        WooBoost_Logger::info('WooBoost_REST: Constructor called');
        $this->openai = new WooBoost_OpenAI();
        add_action('rest_api_init', array($this, 'register_routes'));
        
        // Add hook to catch all REST requests
        add_action('rest_request_before_callbacks', array($this, 'log_rest_request'), 10, 3);
        
        WooBoost_Logger::info('WooBoost_REST: Constructor completed, hooks added');
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        WooBoost_Logger::info('WooBoost_REST: Registering routes');
        
        // Models endpoint
        $models_registered = register_rest_route($this->namespace, '/models', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_models'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(),
            ),
        ));
        
        WooBoost_Logger::info('WooBoost_REST: Models route registered', array('success' => $models_registered));
        
        // Generate content endpoint
        $generate_registered = register_rest_route($this->namespace, '/generate', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'generate_content'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => $this->get_generate_args(),
            ),
        ));
        
        WooBoost_Logger::info('WooBoost_REST: Generate route registered', array('success' => $generate_registered));
        
        // Debug logs endpoint
        $debug_registered = register_rest_route($this->namespace, '/debug-logs', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_debug_logs'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(),
            ),
        ));
        
        WooBoost_Logger::info('WooBoost_REST: Debug logs route registered', array('success' => $debug_registered));
        WooBoost_Logger::info('WooBoost_REST: All routes registration completed');
    }
    
    /**
     * Log REST API requests for debugging
     * 
     * @param mixed $response Response object.
     * @param array $handler Route handler.
     * @param WP_REST_Request $request Request object.
     */
    public function log_rest_request($response, $handler, $request) {
        // Only log our endpoints
        if (strpos($request->get_route(), '/wooboost/v1') !== false) {
            WooBoost_Logger::info('WooBoost_REST: Incoming REST request', array(
                'route' => $request->get_route(),
                'method' => $request->get_method(),
                'params' => $request->get_params(),
                'headers' => $request->get_headers(),
                'user_id' => get_current_user_id()
            ));
        }
    }
    
    /**
     * Check user permissions
     * 
     * @param WP_REST_Request $request Current request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function check_permissions($request) {
        WooBoost_Logger::info('WooBoost_REST: check_permissions called');
        
        // Debug logging
        $user_id = get_current_user_id();
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A';
        
        WooBoost_Logger::info('WooBoost_REST: Permission check details', array(
            'user_id' => $user_id,
            'request_uri' => $request_uri,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'N/A',
            'method' => $request->get_method(),
            'headers' => $request->get_headers()
        ));
        
        // Check nonce - use standard WordPress REST nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            // Fallback to custom header
            $nonce = $request->get_header('X-WooBoost-Nonce');
        }
        
        WooBoost_Logger::info('WooBoost_REST: Nonce check', array(
            'received_nonce' => $nonce,
            'nonce_from_x_wp' => $request->get_header('X-WP-Nonce'),
            'nonce_from_x_wooboost' => $request->get_header('X-WooBoost-Nonce')
        ));
        
        if (!$nonce) {
            WooBoost_Logger::error('WooBoost_REST: No nonce provided');
            return new WP_Error(
                'rest_forbidden',
                __('No nonce provided.', 'wooboost'),
                array('status' => 403)
            );
        }
        
        // Try wp_rest nonce first, then custom nonce
        $nonce_valid = wp_verify_nonce($nonce, 'wp_rest') || wp_verify_nonce($nonce, 'wooboost');
        WooBoost_Logger::info('WooBoost_REST: Nonce validation', array(
            'nonce' => $nonce,
            'wp_rest_valid' => wp_verify_nonce($nonce, 'wp_rest'),
            'wooboost_valid' => wp_verify_nonce($nonce, 'wooboost'),
            'overall_valid' => $nonce_valid
        ));
        
        if (!$nonce_valid) {
            WooBoost_Logger::error('WooBoost_REST: Invalid nonce', array(
                'nonce' => $nonce,
                'user_id' => $user_id
            ));
            return new WP_Error(
                'rest_forbidden',
                __('Invalid nonce.', 'wooboost'),
                array('status' => 403)
            );
        }
        
        // Check capability
        $can_edit = current_user_can('edit_products');
        WooBoost_Logger::info('WooBoost_REST: Capability check', array(
            'user_id' => $user_id,
            'can_edit_products' => $can_edit
        ));
        
        if (!$can_edit) {
            WooBoost_Logger::error('WooBoost_REST: User cannot edit products', array(
                'user_id' => $user_id
            ));
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to edit products.', 'wooboost'),
                array('status' => 403)
            );
        }
        
        WooBoost_Logger::info('WooBoost_REST: All permission checks passed');
        return true;
    }
    
    /**
     * Get available ChatGPT models
     * 
     * @param WP_REST_Request $request Current request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function get_models($request) {
        $models = $this->openai->list_models();
        
        if (is_wp_error($models)) {
            return $models;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data'    => $models
        ));
    }
    
    /**
     * Generate content using ChatGPT
     * 
     * @param WP_REST_Request $request Current request.
     * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
     */
    public function generate_content($request) {
        WooBoost_Logger::info('WooBoost_REST: generate_content called');
        
        $params = $request->get_params();
        WooBoost_Logger::info('WooBoost_REST: Request parameters', array(
            'params' => $params,
            'method' => $request->get_method(),
            'content_type' => $request->get_content_type()
        ));
        
        // Sanitize parameters
        $options = array(
            'model'        => sanitize_text_field($params['model'] ?? 'gpt-3.5-turbo'),
            'length'       => sanitize_text_field($params['length'] ?? 'Medium'),
            'creativity'   => sanitize_text_field($params['creativity'] ?? 'Medium'),
            'style'        => sanitize_text_field($params['style'] ?? 'Formal'),
            'format'       => sanitize_text_field($params['format'] ?? 'Plain text'),
            'product_data' => $this->sanitize_product_data($params['product_data'] ?? array())
        );
        
        WooBoost_Logger::info('WooBoost_REST: Sanitized options', array('options' => $options));
        
        $content = $this->openai->generate_content($options);
        
        if (is_wp_error($content)) {
            return $content;
        }
        
        return rest_ensure_response(array(
            'success' => true,
            'data'    => $content
        ));
    }
    
    /**
     * Get arguments for generate endpoint
     * 
     * @return array
     */
    private function get_generate_args() {
        return array(
            'model' => array(
                'description' => __('ChatGPT model to use', 'wooboost'),
                'type'        => 'string',
                'default'     => 'gpt-3.5-turbo',
            ),
            'length' => array(
                'description' => __('Content length', 'wooboost'),
                'type'        => 'string',
                'enum'        => array('Small', 'Medium', 'Large', 'Detailed'),
                'default'     => 'Medium',
            ),
            'creativity' => array(
                'description' => __('Creativity level', 'wooboost'),
                'type'        => 'string',
                'enum'        => array('Low', 'Medium', 'High', 'Max'),
                'default'     => 'Medium',
            ),
            'style' => array(
                'description' => __('Writing style', 'wooboost'),
                'type'        => 'string',
                'enum'        => array('Formal', 'Casual', 'Persuasive', 'Creative'),
                'default'     => 'Formal',
            ),
            'format' => array(
                'description' => __('Output format', 'wooboost'),
                'type'        => 'string',
                'enum'        => array('Plain text', 'Rich typography'),
                'default'     => 'Plain text',
            ),
            'product_data' => array(
                'description' => __('Product data for generation', 'wooboost'),
                'type'        => 'object',
                'default'     => array(),
            ),
        );
    }
    
    /**
     * Sanitize product data
     * 
     * @param array $data Product data to sanitize
     * @return array Sanitized product data
     */
    private function sanitize_product_data($data) {
        if (!is_array($data)) {
            return array();
        }
        
        $sanitized = array();
        
        if (isset($data['title'])) {
            $sanitized['title'] = sanitize_text_field($data['title']);
        }
        
        if (isset($data['description'])) {
            $sanitized['description'] = wp_kses_post($data['description']);
        }
        
        if (isset($data['short_description'])) {
            $sanitized['short_description'] = wp_kses_post($data['short_description']);
        }
        
        if (isset($data['categories'])) {
            $sanitized['categories'] = array_map('sanitize_text_field', (array) $data['categories']);
        }
        
        if (isset($data['tags'])) {
            $sanitized['tags'] = array_map('sanitize_text_field', (array) $data['tags']);
        }
        
        if (isset($data['attributes'])) {
            $sanitized['attributes'] = array_map('sanitize_text_field', (array) $data['attributes']);
        }
        
        if (isset($data['price'])) {
            $sanitized['price'] = sanitize_text_field($data['price']);
        }
        
        return $sanitized;
    }
    
    /**
     * Check admin permissions for debug endpoints
     * 
     * @param WP_REST_Request $request Current request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function check_admin_permissions($request) {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access debug logs.', 'wooboost'),
                array('status' => 403)
            );
        }
        
        return true;
    }
    
    /**
     * Get debug logs
     * 
     * @param WP_REST_Request $request Current request.
     * @return WP_REST_Response Response object.
     */
    public function get_debug_logs($request) {
        $logs = WooBoost_Logger::get_recent_logs(100);
        
        return rest_ensure_response(array(
            'success' => true,
            'data'    => array(
                'log_file' => WooBoost_Logger::get_log_file(),
                'logs'     => $logs
            )
        ));
    }
}