<?php
/**
 * WooBoost OpenAI Client
 * 
 * Handles OpenAI API integration for content generation
 *
 * @package WooBoost
 * @subpackage Includes/Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooBoost_OpenAI class
 * 
 * OpenAI API client wrapper
 */
class WooBoost_OpenAI {
    
    /**
     * API base URL
     */
    private const API_BASE = 'https://api.openai.com/v1';
    
    /**
     * Allowed AI models for WooBoost
     * Only these models are permitted for content generation
     * Based on OpenAI API documentation - confirmed existing and cost-effective models
     */
    private const ALLOWED_MODELS = array(
        'gpt-5-mini' => array(
            'id' => 'gpt-5-mini',
            'name' => 'GPT-5 Mini',
            'description' => 'Fast and efficient model'
        ),
        'gpt-5-nano' => array(
            'id' => 'gpt-5-nano',
            'name' => 'GPT-5 Nano',
            'description' => 'Ultra-fast and cost-effective (Default)'
        ),
        'gpt-4o-mini' => array(
            'id' => 'gpt-4o-mini',
            'name' => 'GPT-4o Mini',
            'description' => 'Optimized GPT-4 model'
        ),
        'gpt-4.1-nano' => array(
            'id' => 'gpt-4.1-nano',
            'name' => 'GPT-4.1 Nano',
            'description' => 'Latest GPT-4.1 nano variant'
        )
    );
    
    /**
     * Default model when none is specified
     */
    private const DEFAULT_MODEL = 'gpt-5-nano';
    
    /**
     * API key
     */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_key = defined('WOOBOOST_OPENAI_KEY') ? WOOBOOST_OPENAI_KEY : '';
    }
    
    /**
     * Get allowed models array
     * 
     * @return array Array of allowed models
     */
    public static function get_allowed_models() {
        return self::ALLOWED_MODELS;
    }
    
    /**
     * Get allowed model IDs
     * 
     * @return array Array of allowed model IDs
     */
    public static function get_allowed_model_ids() {
        return array_keys(self::ALLOWED_MODELS);
    }
    
    /**
     * Get default model
     * 
     * @return string Default model ID
     */
    public static function get_default_model() {
        return self::DEFAULT_MODEL;
    }
    
    /**
     * Validate if a model is allowed
     * 
     * @param string $model_id Model ID to validate
     * @return bool True if model is allowed
     */
    public static function is_model_allowed($model_id) {
        return array_key_exists($model_id, self::ALLOWED_MODELS);
    }
    
    /**
     * Validate and sanitize model selection
     * 
     * @param string $model_id Requested model ID
     * @return string Valid model ID (falls back to default if invalid)
     */
    public static function validate_model($model_id) {
        if (empty($model_id) || !self::is_model_allowed($model_id)) {
            return self::DEFAULT_MODEL;
        }
        return $model_id;
    }
    
    /**
     * Check if model uses new token parameter format
     * 
     * @param string $model_id Model ID to check
     * @return bool True if model uses max_output_tokens (Responses API)
     */
    protected static function uses_completion_tokens($model_id) {
        // GPT-5 models use the Responses API with max_output_tokens parameter
        $new_format_models = array('gpt-5-mini', 'gpt-5-nano');
        return in_array($model_id, $new_format_models);
    }
    
    /**
     * Check if model supports custom temperature values
     * 
     * @param string $model_id Model ID to check
     * @return bool True if model supports custom temperature
     */
    protected static function supports_temperature($model_id) {
        // GPT-5 models only support default temperature (1)
        $no_temperature_models = array('gpt-5-mini', 'gpt-5-nano');
        return !in_array($model_id, $no_temperature_models);
    }
    
    /**
     * Check if model supports advanced parameters (frequency_penalty, presence_penalty, top_p)
     * 
     * @param string $model_id Model ID to check
     * @return bool True if model supports advanced parameters
     */
    protected static function supports_advanced_parameters($model_id) {
        // GPT-5 models may have limited parameter support
        $limited_parameter_models = array('gpt-5-mini', 'gpt-5-nano');
        return !in_array($model_id, $limited_parameter_models);
    }
    
    /**
     * Check if model requires Responses API instead of Chat Completions API
     * 
     * @param string $model_id Model ID to check
     * @return bool True if model should use Responses API
     */
    protected static function uses_responses_api($model_id) {
        // GPT-5 models work best with Responses API
        $responses_api_models = array('gpt-5-mini', 'gpt-5-nano');
        return in_array($model_id, $responses_api_models);
    }
    
    /**
     * List available chat models
     * 
     * @return array|WP_Error Array of model IDs or WP_Error on failure
     */
    public function list_models() {
        // Return only our pre-approved models
        // This prevents hallucination and ensures only allowed models are available
        return self::get_allowed_model_ids();
    }
    
    /**
     * Generate content using ChatGPT
     * 
     * @param array $options Generation options including model, prompt, etc.
     * @return array|WP_Error Generated content or WP_Error on failure
     */
    public function generate_content($options = array()) {
        if (!$this->has_api_key()) {
            return new WP_Error(
                'no_api_key',
                __('OpenAI API key is not configured.', 'wooboost'),
                array('status' => 500)
            );
        }
        
        // Validate and sanitize the model - critical security check
        $requested_model = $options['model'] ?? self::DEFAULT_MODEL;
        $validated_model = self::validate_model($requested_model);
        
        // Log model validation for debugging
        if ($requested_model !== $validated_model) {
            error_log(sprintf(
                'WooBoost: Invalid model "%s" requested, falling back to "%s"',
                $requested_model,
                $validated_model
            ));
        }
        
        // Build the prompt based on options
        $prompt = $this->build_prompt($options);
        
        // Route to appropriate API based on model type
        if (self::uses_responses_api($validated_model)) {
            return $this->generate_content_responses_api($validated_model, $prompt, $options);
        } else {
            return $this->generate_content_chat_api($validated_model, $prompt, $options);
        }
    }
    
    /**
     * Generate content using Chat Completions API
     * 
     * @param string $model Validated model ID
     * @param string $prompt Generated prompt
     * @param array $options Generation options
     * @return array|WP_Error Generated content or WP_Error on failure
     */
    private function generate_content_chat_api($model, $prompt, $options) {
        // Map creativity to temperature
        $temperature = $this->map_creativity_to_temperature($options['creativity'] ?? 'Medium');
        
        // Get max tokens value
        $max_tokens_value = $this->get_max_tokens($options['length'] ?? 'Medium');
        
        // Prepare the API request for Chat Completions API
        $request_data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a professional e-commerce copywriter specializing in product descriptions. Create compelling, accurate, and SEO-friendly content that helps customers understand the product value and encourages purchases.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            )
        );
        
        // Only include temperature for models that support it
        if (self::supports_temperature($model)) {
            $request_data['temperature'] = $temperature;
        }
        
        // Only include advanced parameters for models that support them
        if (self::supports_advanced_parameters($model)) {
            $request_data['top_p'] = 1;
            $request_data['frequency_penalty'] = 0;
            $request_data['presence_penalty'] = 0;
        }
        
        // Use the correct token parameter based on model type
        if (self::uses_completion_tokens($model)) {
            $request_data['max_completion_tokens'] = $max_tokens_value;
        } else {
            $request_data['max_tokens'] = $max_tokens_value;
        }
        
        $response = wp_remote_post(self::API_BASE . '/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_data),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed',
                __('Failed to connect to OpenAI API: ', 'wooboost') . $response->get_error_message(),
                array('status' => 500)
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : __('Unknown API error occurred.', 'wooboost');
                
            return new WP_Error(
                'api_error',
                $error_message,
                array('status' => $status_code)
            );
        }
        
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return new WP_Error(
                'invalid_response',
                __('Invalid response from OpenAI API.', 'wooboost'),
                array('status' => 500)
            );
        }
        
        $generated_content = trim($data['choices'][0]['message']['content']);
        
        // Process the content based on format preference
        $processed_content = $this->process_content_format($generated_content, $options['format'] ?? 'Plain text');
        
        // Extract excerpt
        $excerpt = $this->extract_excerpt($processed_content);
        
        return array(
            'excerpt' => $excerpt,
            'description' => $processed_content,
            'usage' => isset($data['usage']) ? $data['usage'] : null
        );
    }
    
    /**
     * Generate content using Responses API (for GPT-5 models)
     * 
     * @param string $model Validated model ID  
     * @param string $prompt Generated prompt
     * @param array $options Generation options
     * @return array|WP_Error Generated content or WP_Error on failure
     */
    private function generate_content_responses_api($model, $prompt, $options) {
        // Map creativity to verbosity for GPT-5 models
        $verbosity = $this->map_creativity_to_verbosity($options['creativity'] ?? 'Medium');
        
        // Get max tokens value
        $max_tokens_value = $this->get_max_tokens($options['length'] ?? 'Medium');
        
        // Build system and user input for Responses API
        $input_messages = array(
            array(
                'role' => 'system',
                'content' => 'You are a professional e-commerce copywriter specializing in product descriptions. Create compelling, accurate, and SEO-friendly content that helps customers understand the product value and encourages purchases.'
            ),
            array(
                'role' => 'user', 
                'content' => $prompt
            )
        );
        
        // Prepare the API request for Responses API
        $request_data = array(
            'model' => $model,
            'input' => $input_messages,
            'text' => array(
                'verbosity' => $verbosity
            ),
            'reasoning' => array(
                'effort' => 'minimal'  // Minimize reasoning to save tokens for actual content
            )
        );
        
        // Add token limits if supported - Responses API uses max_output_tokens
        if (self::uses_completion_tokens($model)) {
            // Increase token limit to account for reasoning overhead
            $adjusted_tokens = $max_tokens_value + 200; // Add buffer for reasoning
            $request_data['max_output_tokens'] = $adjusted_tokens;
        }
        
        $response = wp_remote_post(self::API_BASE . '/responses', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_data),
            'timeout' => 60,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed',
                __('Failed to connect to OpenAI API: ', 'wooboost') . $response->get_error_message(),
                array('status' => 500)
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : __('Unknown API error occurred.', 'wooboost');
                
            return new WP_Error(
                'api_error',
                $error_message,
                array('status' => $status_code)
            );
        }
        
        $data = json_decode($body, true);
        
        // Extract content from Responses API format
        // Based on OpenAI docs: response should have output_text property directly
        $generated_content = $this->extract_responses_api_content($data);
        
        if (empty($generated_content)) {
            return new WP_Error(
                'invalid_response',
                __('Invalid response from OpenAI Responses API. No content found in response.', 'wooboost'),
                array('status' => 500)
            );
        }
        
        // Process the content based on format preference
        $processed_content = $this->process_content_format($generated_content, $options['format'] ?? 'Plain text');
        
        // Extract excerpt
        $excerpt = $this->extract_excerpt($processed_content);
        
        return array(
            'excerpt' => $excerpt,
            'description' => $processed_content,
            'usage' => isset($data['usage']) ? $data['usage'] : null
        );
    }
    
    /**
     * Extract content from Responses API response format
     * 
     * @param array $data API response data
     * @return string Extracted content
     */
    private function extract_responses_api_content($data) {
        $content = '';
        
        // GPT-5 Responses API format: content is in output array
        if (isset($data['output']) && is_array($data['output'])) {
            foreach ($data['output'] as $item) {
                // Look for message type items with content
                if (isset($item['type']) && $item['type'] === 'message' && isset($item['content'])) {
                    if (is_array($item['content'])) {
                        foreach ($item['content'] as $content_item) {
                            if (isset($content_item['type']) && $content_item['type'] === 'output_text' && isset($content_item['text'])) {
                                $content .= $content_item['text'];
                            }
                        }
                    }
                }
            }
        }
        // Fallback: Primary format (output_text property directly)
        elseif (isset($data['output_text']) && is_string($data['output_text'])) {
            $content = $data['output_text'];
        }
        // Fallback: choices array (similar to Chat Completions)
        elseif (isset($data['choices']) && is_array($data['choices']) && !empty($data['choices'])) {
            if (isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
            } elseif (isset($data['choices'][0]['text'])) {
                $content = $data['choices'][0]['text'];
            }
        }
        // Fallback: direct content property
        elseif (isset($data['content']) && is_string($data['content'])) {
            $content = $data['content'];
        }
        // Fallback: text property
        elseif (isset($data['text']) && is_string($data['text'])) {
            $content = $data['text'];
        }
        
        return trim($content);
    }
    
    /**
     * Map creativity setting to verbosity parameter for GPT-5 models
     * 
     * @param string $creativity Creativity setting
     * @return string Verbosity level
     */
    private function map_creativity_to_verbosity($creativity) {
        switch (strtolower($creativity)) {
            case 'low':
                return 'low';
            case 'medium':
            default:
                return 'medium';
            case 'high':
                return 'high';
        }
    }
    
    /**
     * Check if API key is configured
     * 
     * @return bool True if API key is set
     */
    public function has_api_key() {
        return !empty($this->api_key);
    }
    
    /**
     * Validate API key by making a test request
     * 
     * @return bool|WP_Error True if valid, WP_Error on failure
     */
    public function validate_api_key() {
        if (!$this->has_api_key()) {
            return new WP_Error(
                'no_api_key',
                __('OpenAI API key is not configured.', 'wooboost'),
                array('status' => 500)
            );
        }
        
        // Make a simple request to validate the key
        $response = wp_remote_get(self::API_BASE . '/models?limit=1', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'timeout' => 10,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed',
                __('Failed to connect to OpenAI API.', 'wooboost'),
                array('status' => 500)
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            return true;
        } elseif ($status_code === 401) {
            return new WP_Error(
                'invalid_api_key',
                __('Invalid OpenAI API key.', 'wooboost'),
                array('status' => 401)
            );
        } else {
            return new WP_Error(
                'api_error',
                __('OpenAI API validation failed.', 'wooboost'),
                array('status' => $status_code)
            );
        }
    }
    
    /**
     * Build prompt based on generation options
     * 
     * @param array $options Generation options
     * @return string Formatted prompt
     */
    private function build_prompt($options) {
        $product_data = $options['product_data'] ?? array();
        $length = $options['length'] ?? 'Medium';
        $style = $options['style'] ?? 'Formal';
        $format = $options['format'] ?? 'Plain text';
        
        $prompt = "Create product content for an e-commerce website.\n\n";
        
        // Add product information
        if (!empty($product_data['title'])) {
            $prompt .= "Product Name: " . $product_data['title'] . "\n";
        }
        
        if (!empty($product_data['price'])) {
            $prompt .= "Price: " . $product_data['price'] . "\n";
        }
        
        if (!empty($product_data['categories'])) {
            $categories = is_array($product_data['categories']) 
                ? implode(', ', $product_data['categories']) 
                : $product_data['categories'];
            $prompt .= "Categories: " . $categories . "\n";
        }
        
        if (!empty($product_data['tags'])) {
            $tags = is_array($product_data['tags']) 
                ? implode(', ', $product_data['tags']) 
                : $product_data['tags'];
            $prompt .= "Tags: " . $tags . "\n";
        }
        
        if (!empty($product_data['description'])) {
            $prompt .= "Existing Description: " . strip_tags($product_data['description']) . "\n";
        }
        
        $prompt .= "\nRequirements:\n";
        
        // Length requirements
        switch ($length) {
            case 'Small':
                $prompt .= "- Create a brief, concise description (50-100 words)\n";
                break;
            case 'Medium':
                $prompt .= "- Create a balanced description (100-200 words)\n";
                break;
            case 'Large':
                $prompt .= "- Create a comprehensive description (200-300 words)\n";
                break;
            case 'Detailed':
                $prompt .= "- Create an extensive, detailed description (300+ words)\n";
                break;
        }
        
        // Style requirements
        switch ($style) {
            case 'Formal':
                $prompt .= "- Use professional, formal language\n";
                break;
            case 'Casual':
                $prompt .= "- Use friendly, casual language\n";
                break;
            case 'Persuasive':
                $prompt .= "- Use compelling, sales-focused language that drives conversions\n";
                break;
            case 'Creative':
                $prompt .= "- Use creative, unique language that stands out\n";
                break;
        }
        
        // Format requirements
        if ($format === 'HTML (Simplified)') {
            $prompt .= "- Use simple HTML formatting with <strong>, <em>, <b>, <i>, <u>, and <br> tags where appropriate\n";
            $prompt .= "- Keep formatting minimal and focused on emphasis\n";
        } elseif ($format === 'HTML (Detailed)') {
            $prompt .= "- Use comprehensive HTML formatting with proper structure:\n";
            $prompt .= "- Use <h2> and <h3> for section headings (e.g., 'Product Overview', 'Key Features', 'Specifications')\n";
            $prompt .= "- Use <ul> and <li> tags for feature lists and bullet points\n";
            $prompt .= "- Use <p> tags for paragraphs\n";
            $prompt .= "- Use <strong> for important terms and <em> for subtle emphasis\n";
            $prompt .= "- Create well-structured content with clear sections and hierarchy\n";
            $prompt .= "- Example structure: <h2>Product Overview</h2><p>Description...</p><h3>Key Features</h3><ul><li><strong>Feature:</strong> Description</li></ul>\n";
        } else {
            $prompt .= "- Use plain text without HTML formatting\n";
        }
        
        $prompt .= "- Focus on benefits and value to the customer\n";
        $prompt .= "- Include relevant keywords naturally\n";
        $prompt .= "- Make it engaging and informative\n";
        $prompt .= "- Ensure the content is accurate and helpful for potential buyers\n\n";
        $prompt .= "Generate the product description now:";
        
        return $prompt;
    }
    
    /**
     * Map creativity level to OpenAI temperature
     * 
     * @param string $creativity Creativity level
     * @return float Temperature value
     */
    protected function map_creativity_to_temperature($creativity) {
        switch ($creativity) {
            case 'Low':
                return 0.3;
            case 'Medium':
                return 0.7;
            case 'High':
                return 1.0;
            case 'Max':
                return 1.3;
            default:
                return 0.7;
        }
    }
    
    /**
     * Get max tokens based on content length
     * 
     * @param string $length Content length
     * @return int Max tokens
     */
    protected function get_max_tokens($length) {
        switch ($length) {
            case 'Small':
                return 150;
            case 'Medium':
                return 300;
            case 'Large':
                return 500;
            case 'Detailed':
                return 800;
            default:
                return 300;
        }
    }
    
    /**
     * Process content format
     * 
     * @param string $content Generated content
     * @param string $format Desired format
     * @return string Processed content
     */
    private function process_content_format($content, $format) {
        if ($format === 'HTML (Simplified)' || $format === 'Rich typography') {
            // Convert basic markdown-style formatting to HTML if present
            $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
            $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
            $content = preg_replace('/\n\n/', '<br><br>', $content);
            $content = preg_replace('/\n/', '<br>', $content);
        } elseif ($format === 'HTML (Detailed)') {
            // For detailed HTML, the AI should already provide structured HTML
            // Just clean up any markdown artifacts that might remain
            $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
            $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
            
            // Don't convert newlines to <br> for detailed HTML as it should already have proper structure
        }
        
        return $content;
    }
    
    /**
     * Extract excerpt from content
     * 
     * @param string $content Full content
     * @return string Excerpt
     */
    private function extract_excerpt($content) {
        // For HTML content, try to get a meaningful excerpt that preserves some structure
        if (strpos($content, '<') !== false) {
            // Remove heading tags but keep paragraph content
            $content_for_excerpt = preg_replace('/<h[1-6][^>]*>.*?<\/h[1-6]>/i', '', $content);
            
            // Find the first paragraph or meaningful content
            if (preg_match('/<p[^>]*>(.*?)<\/p>/i', $content_for_excerpt, $matches)) {
                $plain_content = strip_tags($matches[1]);
            } else {
                // Fallback: strip all tags
                $plain_content = strip_tags($content_for_excerpt);
            }
        } else {
            // Plain text content
            $plain_content = $content;
        }
        
        // Clean up whitespace
        $plain_content = trim(preg_replace('/\s+/', ' ', $plain_content));
        
        // Get first sentence or first 150 characters
        $sentences = preg_split('/[.!?]+/', $plain_content);
        $first_sentence = trim($sentences[0] ?? '');
        
        if (strlen($first_sentence) > 150) {
            return substr($first_sentence, 0, 147) . '...';
        }
        
        return empty($first_sentence) ? substr($plain_content, 0, 147) . '...' : $first_sentence . '.';
    }
}