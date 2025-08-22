<?php
if (!defined('ABSPATH')) exit;

/**
 * Legacy logging system wrapper for WCEventsFP plugin
 * 
 * This class now acts as a wrapper around the new WCEFP\Utils\Logger
 * to maintain backward compatibility while eliminating duplication.
 * 
 * @since 1.7.2
 * @deprecated 2.1.1 Use WCEFP\Utils\Logger instead
 */
class WCEFP_Logger {
    
    private static $instance = null;
    private $log_file;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Set up log file path for backward compatibility
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/wcefp-logs';
        $this->log_file = $log_dir . '/wcefp-' . date('Y-m') . '.log';
    }
    
    /**
     * Log an error message
     *
     * @param string $message Error message
     * @param array  $context Additional context data
     */
    public static function error($message, $context = []) {
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::error($message, $context);
        } else {
            error_log("WCEventsFP [ERROR]: {$message}");
        }
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Warning message
     * @param array  $context Additional context data
     */
    public static function warning($message, $context = []) {
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::warning($message, $context);
        } else {
            error_log("WCEventsFP [WARNING]: {$message}");
        }
    }
    
    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param array  $context Additional context data
     */
    public static function info($message, $context = []) {
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::info($message, $context);
        } else {
            error_log("WCEventsFP [INFO]: {$message}");
        }
    }
    
    /**
     * Log a debug message (only in WP_DEBUG mode)
     *
     * @param string $message Debug message
     * @param array  $context Additional context data
     */
    public static function debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (class_exists('WCEFP\\Utils\\Logger')) {
                \WCEFP\Utils\Logger::debug($message, $context);
            } else {
                error_log("WCEventsFP [DEBUG]: {$message}");
            }
        }
    }
    
    /**
     * Get recent log entries for admin display (backward compatibility)
     *
     * @param int $limit Number of entries to retrieve
     * @return array
     */
    public static function get_recent_logs($limit = 100) {
        $instance = self::get_instance();
        
        if (!file_exists($instance->log_file)) {
            return [];
        }
        
        $logs = file($instance->log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$logs) {
            return [];
        }
        
        return array_slice(array_reverse($logs), 0, $limit);
    }
    
    /**
     * Clear all log files (backward compatibility)
     */
    public static function clear_logs() {
        $instance = self::get_instance();
        $log_dir = dirname($instance->log_file);
        
        $files = glob($log_dir . '/wcefp-*.log*');
        foreach ($files as $file) {
            if (is_writable($file)) {
                unlink($file);
            }
        }
        
        self::info('Log files cleared by admin');
    }
}