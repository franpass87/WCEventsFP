<?php
if (!defined('ABSPATH')) exit;

/**
 * Centralized logging system for WCEventsFP plugin
 * 
 * @since 1.7.2
 */
class WCEFP_Logger {
    
    private static $instance = null;
    private $log_file;
    private $max_log_size = 5242880; // 5MB
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
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
     * Log an error message
     *
     * @param string $message Error message
     * @param array  $context Additional context data
     */
    public static function error($message, $context = []) {
        self::get_instance()->log('ERROR', $message, $context);
    }
    
    /**
     * Log a warning message
     *
     * @param string $message Warning message
     * @param array  $context Additional context data
     */
    public static function warning($message, $context = []) {
        self::get_instance()->log('WARNING', $message, $context);
    }
    
    /**
     * Log an info message
     *
     * @param string $message Info message
     * @param array  $context Additional context data
     */
    public static function info($message, $context = []) {
        self::get_instance()->log('INFO', $message, $context);
    }
    
    /**
     * Log a debug message (only in WP_DEBUG mode)
     *
     * @param string $message Debug message
     * @param array  $context Additional context data
     */
    public static function debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            self::get_instance()->log('DEBUG', $message, $context);
        }
    }
    
    /**
     * Log a message with level
     *
     * @param string $level   Log level
     * @param string $message Log message
     * @param array  $context Additional context data
     */
    private function log($level, $message, $context = []) {
        if (!is_writable(dirname($this->log_file))) {
            return false;
        }
        
        // Rotate log if too large
        if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_log_size) {
            $this->rotate_log();
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $ip = $this->get_client_ip();
        
        $context_str = empty($context) ? '' : ' ' . wp_json_encode($context);
        
        $log_entry = sprintf(
            "[%s] %s [User:%d] [IP:%s] %s%s\n",
            $timestamp,
            $level,
            $user_id,
            $ip,
            $message,
            $context_str
        );
        
        return file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Rotate current log file
     */
    private function rotate_log() {
        $backup_file = $this->log_file . '.' . date('YmdHis') . '.bak';
        rename($this->log_file, $backup_file);
        
        // Keep only last 5 backup files
        $pattern = dirname($this->log_file) . '/wcefp-*.log.*.bak';
        $files = glob($pattern);
        if (count($files) > 5) {
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            // Delete oldest files
            for ($i = 0; $i < count($files) - 5; $i++) {
                unlink($files[$i]);
            }
        }
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }
        
        return $ip;
    }
    
    /**
     * Get recent log entries for admin display
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
     * Clear all log files
     */
    public static function clear_logs() {
        $instance = self::get_instance();
        $log_dir = dirname($instance->log_file);
        
        $files = glob($log_dir . '/wcefp-*.log*');
        foreach ($files as $file) {
            unlink($file);
        }
        
        self::info('Log files cleared by admin');
    }
}