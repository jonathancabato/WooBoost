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
     * List available chat models
     * 
     * @return array|WP_Error Array of model IDs or WP_Error on failure
     */
    public function list_models() {
        if (!$this->has_api_key()) {
            return new WP_Error(
                'no_api_key',
                __('OpenAI API key is not configured.', 'wooboost'),
                array('status' => 500)
            );
        }
        
        $response = wp_remote_get(self::API_BASE . '/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return new WP_Error(
                'api_request_failed',
                __('Failed to connect to OpenAI API.', 'wooboost'),
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
        
        if (!isset($data['data']) || !is_array($data['data'])) {
            return new WP_Error(
                'invalid_response',
                __('Invalid response from OpenAI API.', 'wooboost'),
                array('status' => 500)
            );
        }
        
        // Filter to only chat completion models
        $chat_models = array();
        foreach ($data['data'] as $model) {
            if (isset($model['id']) && $this->is_chat_model($model['id'])) {
                $chat_models[] = $model['id'];
            }
        }
        
        // If no chat models found, return our defaults
        if (empty($chat_models)) {
            return array(
                'gpt-4',
                'gpt-4-turbo-preview', 
                'gpt-3.5-turbo'
            );
        }
        
        return $chat_models;
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
        
        // Build the prompt based on options
        $prompt = $this->build_prompt($options);
        
        // Map creativity to temperature
        $temperature = $this->map_creativity_to_temperature($options['creativity'] ?? 'Medium');
        
        // Prepare the API request
        $request_data = array(
            'model' => $options['model'] ?? 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a professional e-commerce copywriter specializing in product descriptions. Create compelling, accurate, and SEO-friendly content that helps customers understand the product value and encourages purchases.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => $temperature,
            'max_tokens' => $this->get_max_tokens($options['length'] ?? 'Medium'),
            'top_p' => 1,
            'frequency_penalty' => 0,
            'presence_penalty' => 0
        );
        
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
     * Check if a model ID is a chat completion model
     * 
     * @param string $model_id Model ID to check
     * @return bool True if it's a chat model
     */
    private function is_chat_model($model_id) {
        $chat_model_patterns = array(
            'gpt-3.5',
            'gpt-4',
            'gpt-3.5-turbo',
            'gpt-4-turbo'
        );
        
        foreach ($chat_model_patterns as $pattern) {
            if (strpos($model_id, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
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
    private function map_creativity_to_temperature($creativity) {
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
    private function get_max_tokens($length) {
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