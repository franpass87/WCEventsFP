<?php
/**
 * Enhanced Logger Class
 * 
 * @package WCEFP
 * @subpackage Utils
 * @since 2.0.1
 */

namespace WCEFP\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced logging system with PSR-3 compliant interface
 */
class Logger {
    
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';
    
    /**
     * Logger instance
     * 
     * @var Logger
     */
    private static $instance;
    
    /**
     * Log file path
     * 
     * @var string
     */
    private $log_file;
    
    /**
     * Maximum log file size (5MB)
     * 
     * @var int
     */
    private $max_log_size = 5242880;
    
    /**
     * Get logger instance
     * 
     * @return Logger
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->setup_log_file();
    }
    
    /**
     * Setup log file
     * 
     * @return void
     */
    private function setup_log_file() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wcefp-logs';
        
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Add .htaccess to prevent direct access
            file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
        }
        
        $this->log_file = $log_dir . '/wcefp-' . date('Y-m') . '.log';
    }
    
    /**
     * Log a message
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return void
     */
    public function log($level, $message, array $context = []) {
        if (!in_array($level, [
            self::EMERGENCY, self::ALERT, self::CRITICAL, self::ERROR,
            self::WARNING, self::NOTICE, self::INFO, self::DEBUG
        ])) {
            $level = self::INFO;
        }
        
        $this->write_log($level, $message, $context);
    }
    
    /**
     * Write log entry
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return void
     */
    private function write_log($level, $message, array $context = []) {
        try {
            // Rotate log if too large
            $this->rotate_log_if_needed();
            
            $timestamp = date('Y-m-d H:i:s');
            $formatted_message = $this->format_message($level, $message, $context);
            $log_entry = "[{$timestamp}] [{$level}] {$formatted_message}" . PHP_EOL;
            
            file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
            
            // Also log to WordPress debug log in development
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("WCEventsFP [{$level}]: {$message}");
            }
            
        } catch (\Exception $e) {
            // Fallback to error_log if file writing fails
            error_log("WCEventsFP Logger Error: " . $e->getMessage());
            error_log("WCEventsFP [{$level}]: {$message}");
        }
    }
    
    /**
     * Format log message with context
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Context data
     * @return string
     */
    private function format_message($level, $message, array $context = []) {
        $formatted = $message;
        
        // Replace placeholders in message
        foreach ($context as $key => $value) {
            $placeholder = '{' . $key . '}';
            if (strpos($formatted, $placeholder) !== false) {
                $formatted = str_replace($placeholder, $this->stringify_value($value), $formatted);
            }
        }
        
        // Add context data if any remaining
        $remaining_context = array_filter($context, function($key) use ($message) {
            return strpos($message, '{' . $key . '}') === false;
        }, ARRAY_FILTER_USE_KEY);
        
        if (!empty($remaining_context)) {
            $formatted .= ' | Context: ' . json_encode($remaining_context);
        }
        
        return $formatted;
    }
    
    /**
     * Convert value to string for logging
     * 
     * @param mixed $value Value to stringify
     * @return string
     */
    private function stringify_value($value) {
        if (is_null($value)) {
            return 'null';
        }
        
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }
    
    /**
     * Rotate log file if it exceeds maximum size
     * 
     * @return void
     */
    private function rotate_log_if_needed() {
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $backup_file = $this->log_file . '.bak';
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }
            rename($this->log_file, $backup_file);
        }
    }
    
    // Static convenience methods
    
    public static function emergency($message, array $context = []) {
        self::get_instance()->log(self::EMERGENCY, $message, $context);
    }
    
    public static function alert($message, array $context = []) {
        self::get_instance()->log(self::ALERT, $message, $context);
    }
    
    public static function critical($message, array $context = []) {
        self::get_instance()->log(self::CRITICAL, $message, $context);
    }
    
    public static function error($message, array $context = []) {
        self::get_instance()->log(self::ERROR, $message, $context);
    }
    
    public static function warning($message, array $context = []) {
        self::get_instance()->log(self::WARNING, $message, $context);
    }
    
    public static function notice($message, array $context = []) {
        self::get_instance()->log(self::NOTICE, $message, $context);
    }
    
    public static function info($message, array $context = []) {
        self::get_instance()->log(self::INFO, $message, $context);
    }
    
    public static function debug($message, array $context = []) {
        self::get_instance()->log(self::DEBUG, $message, $context);
    }
}