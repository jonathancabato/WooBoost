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
        $this->openai = new WooBoost_OpenAI();
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Models endpoint
        register_rest_route($this->namespace, '/models', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_models'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(),
            ),
        ));
        
        // Generate content endpoint
        register_rest_route($this->namespace, '/generate', array(
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'generate_content'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => $this->get_generate_args(),
            ),
        ));
        
        // Debug logs endpoint
        register_rest_route($this->namespace, '/debug-logs', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_debug_logs'),
                'permission_callback' => array($this, 'check_admin_permissions'),
                'args'                => array(),
            ),
        ));
    }
    
    /**
     * Check user permissions
     * 
     * @param WP_REST_Request $request Current request.
     * @return bool|WP_Error True if the request has permission, WP_Error otherwise.
     */
    public function check_permissions($request) {
        // Check nonce - use standard WordPress REST nonce
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            // Fallback to custom header
            $nonce = $request->get_header('X-WooBoost-Nonce');
        }
        
        if (!$nonce) {
            return new WP_Error(
                'rest_forbidden',
                __('No nonce provided.', 'wooboost'),
                array('status' => 403)
            );
        }
        
        // Try wp_rest nonce first, then custom nonce
        $nonce_valid = wp_verify_nonce($nonce, 'wp_rest') || wp_verify_nonce($nonce, 'wooboost');
        
        if (!$nonce_valid) {
            return new WP_Error(
                'rest_forbidden',
                __('Invalid nonce.', 'wooboost'),
                array('status' => 403)
            );
        }
        
        // Check capability
        if (!current_user_can('edit_products')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to edit products.', 'wooboost'),
                array('status' => 403)
            );
        }
        
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
        $params = $request->get_params();
        
        // Sanitize parameters with strict model validation
        $requested_model = sanitize_text_field($params['model'] ?? WooBoost_OpenAI::get_default_model());
        $validated_model = WooBoost_OpenAI::validate_model($requested_model);
        
        $options = array(
            'model'        => $validated_model,
            'length'       => sanitize_text_field($params['length'] ?? 'Medium'),
            'creativity'   => sanitize_text_field($params['creativity'] ?? 'Medium'),
            'style'        => sanitize_text_field($params['style'] ?? 'Formal'),
            'format'       => sanitize_text_field($params['format'] ?? 'Plain text'),
            'product_data' => $this->sanitize_product_data($params['product_data'] ?? array())
        );
        
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
                'description' => __('AI Model to use for content generation', 'wooboost'),
                'type'        => 'string',
                'enum'        => WooBoost_OpenAI::get_allowed_model_ids(),
                'default'     => WooBoost_OpenAI::get_default_model(),
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
                'enum'        => array('Plain text', 'HTML (Simplified)', 'HTML (Detailed)'),
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