<?php
/**
 * WooBoost Logger Class
 * 
 * Simple logging utility for debugging WooBoost issues
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
 * WooBoost_Logger class
 * 
 * Handles logging for debugging purposes
 */
class WooBoost_Logger {
    
    /**
     * Log file path
     */
    private static $log_file = null;
    
    /**
     * Initialize logger
     */
    public static function init() {
        if (self::$log_file === null) {
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/wooboost-logs/';
            
            // Create logs directory if it doesn't exist
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            self::$log_file = $log_dir . 'wooboost-debug.txt';
        }
    }
    
    /**
     * Log a message
     * 
     * @param string $message Message to log
     * @param string $level Log level (INFO, ERROR, DEBUG)
     * @param array $context Additional context data
     */
    public static function log($message, $level = 'INFO', $context = array()) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A';
        
        $log_entry = sprintf(
            "[%s] [%s] [User:%d] [URI:%s] %s\n",
            $timestamp,
            $level,
            $user_id,
            $request_uri,
            $message
        );
        
        // Add context if provided
        if (!empty($context)) {
            $log_entry .= "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
        
        $log_entry .= str_repeat('-', 80) . "\n";
        
        // Write to file
        file_put_contents(self::$log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Log an info message
     */
    public static function info($message, $context = array()) {
        self::log($message, 'INFO', $context);
    }
    
    /**
     * Log an error message
     */
    public static function error($message, $context = array()) {
        self::log($message, 'ERROR', $context);
    }
    
    /**
     * Log a debug message
     */
    public static function debug($message, $context = array()) {
        self::log($message, 'DEBUG', $context);
    }
    
    /**
     * Get log file path
     */
    public static function get_log_file() {
        self::init();
        return self::$log_file;
    }
    
    /**
     * Clear log file
     */
    public static function clear_log() {
        self::init();
        if (file_exists(self::$log_file)) {
            file_put_contents(self::$log_file, '');
        }
    }
    
    /**
     * Get recent log entries
     * 
     * @param int $lines Number of lines to get
     * @return string
     */
    public static function get_recent_logs($lines = 50) {
        self::init();
        
        if (!file_exists(self::$log_file)) {
            return 'Log file not found.';
        }
        
        $file = file(self::$log_file);
        $recent = array_slice($file, -$lines);
        
        return implode('', $recent);
    }
}
