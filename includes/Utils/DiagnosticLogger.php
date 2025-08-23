<?php
/**
 * Enhanced Logging and Diagnostics System
 * 
 * Provides comprehensive logging, error tracking, and system diagnostics.
 * 
 * @package WCEFP
 * @subpackage Utils
 * @since 2.1.4
 */

namespace WCEFP\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced logger with multiple channels and diagnostic capabilities
 */
class DiagnosticLogger {
    
    /**
     * Log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    /**
     * Log channels
     */
    const CHANNEL_GENERAL = 'general';
    const CHANNEL_BOOKINGS = 'bookings';
    const CHANNEL_PAYMENTS = 'payments';
    const CHANNEL_INTEGRATIONS = 'integrations';
    const CHANNEL_PERFORMANCE = 'performance';
    const CHANNEL_SECURITY = 'security';
    
    /**
     * Instance
     * 
     * @var DiagnosticLogger
     */
    private static $instance;
    
    /**
     * Log directory
     * 
     * @var string
     */
    private $log_dir;
    
    /**
     * Enable logging
     * 
     * @var bool
     */
    private $enabled;
    
    /**
     * Log level threshold
     * 
     * @var string
     */
    private $log_level;
    
    /**
     * Max log file size (in bytes)
     * 
     * @var int
     */
    private $max_file_size;
    
    /**
     * Max log files to keep
     * 
     * @var int
     */
    private $max_files;
    
    /**
     * Get singleton instance
     * 
     * @return DiagnosticLogger
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->log_dir = WP_CONTENT_DIR . '/wcefp-logs/';
        $this->enabled = defined('WCEFP_DEBUG_LOG') ? WCEFP_DEBUG_LOG : WP_DEBUG;
        $this->log_level = defined('WCEFP_LOG_LEVEL') ? WCEFP_LOG_LEVEL : self::INFO;
        $this->max_file_size = defined('WCEFP_MAX_LOG_SIZE') ? WCEFP_MAX_LOG_SIZE : 10485760; // 10MB
        $this->max_files = defined('WCEFP_MAX_LOG_FILES') ? WCEFP_MAX_LOG_FILES : 5;
        
        $this->ensure_log_directory();
        
        // Add error handler for PHP errors
        if ($this->enabled) {
            add_action('wp_loaded', [$this, 'set_error_handler']);
        }
    }
    
    /**
     * Log a message
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context data
     * @param string $channel Log channel
     */
    public function log($level, $message, $context = [], $channel = self::CHANNEL_GENERAL) {
        if (!$this->enabled || !$this->should_log($level)) {
            return;
        }
        
        $log_entry = $this->format_log_entry($level, $message, $context, $channel);
        $this->write_to_file($log_entry, $channel);
        
        // Also log to WordPress debug.log if WP_DEBUG is enabled
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log("WCEventsFP [{$level}] {$message}");
        }
        
        // Trigger action for external handlers
        do_action('wcefp_log', $level, $message, $context, $channel);
    }
    
    /**
     * Log emergency message
     */
    public function emergency($message, $context = [], $channel = self::CHANNEL_GENERAL) {
        $this->log(self::EMERGENCY, $message, $context, $channel);
    }
    
    /**
     * Log alert message
     */
    public function alert($message, $context = [], $channel = self::CHANNEL_GENERAL) {
        $this->log(self::ALERT, $message, $context, $channel);
    }
    
    /**
     * Log critical message
     */
    public function critical($message, $context = [], $channel = self::CHANNEL_GENERAL) {
        $this->log(self::CRITICAL, $message, $context, $channel);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = [], $channel = self::CHANNEL_GENERAL) {
        $this->log(self::ERROR, $message, $context, $channel);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = [], $channel = self::CHANNEL_GENERAL) {
        $this->log(self::WARNING, $message, $context, $channel);
    }
    
    /**
     * Log notice message
     */
    public function notice($message, $context = [], $channel = self::CHANNEL_GENERAL) {
        $this->log(self::NOTICE, $message, $context, $channel);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = [], $channel = self::CHANNEL_GENERAL) {
        $this->log(self::INFO, $message, $context, $channel);
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = [], $channel = self::CHANNEL_GENERAL) {
        $this->log(self::DEBUG, $message, $context, $channel);
    }
    
    /**
     * Log booking-related events
     */
    public function log_booking($level, $message, $booking_id = null, $context = []) {
        if ($booking_id) {
            $context['booking_id'] = $booking_id;
        }
        $this->log($level, $message, $context, self::CHANNEL_BOOKINGS);
    }
    
    /**
     * Log payment-related events
     */
    public function log_payment($level, $message, $order_id = null, $context = []) {
        if ($order_id) {
            $context['order_id'] = $order_id;
        }
        $this->log($level, $message, $context, self::CHANNEL_PAYMENTS);
    }
    
    /**
     * Log integration-related events
     */
    public function log_integration($level, $message, $service = null, $context = []) {
        if ($service) {
            $context['service'] = $service;
        }
        $this->log($level, $message, $context, self::CHANNEL_INTEGRATIONS);
    }
    
    /**
     * Log performance metrics
     */
    public function log_performance($message, $start_time = null, $context = []) {
        if ($start_time) {
            $context['execution_time'] = microtime(true) - $start_time;
            $context['memory_usage'] = memory_get_usage(true);
            $context['peak_memory'] = memory_get_peak_usage(true);
        }
        $this->log(self::INFO, $message, $context, self::CHANNEL_PERFORMANCE);
    }
    
    /**
     * Log security events
     */
    public function log_security($level, $message, $user_id = null, $context = []) {
        if ($user_id) {
            $context['user_id'] = $user_id;
        }
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $context['ip_address'] = $this->get_client_ip();
        
        $this->log($level, $message, $context, self::CHANNEL_SECURITY);
    }
    
    /**
     * Get recent log entries
     * 
     * @param string $channel Log channel
     * @param int $limit Number of entries to retrieve
     * @param string $level Minimum log level
     * @return array
     */
    public function get_recent_logs($channel = self::CHANNEL_GENERAL, $limit = 100, $level = null) {
        $log_file = $this->get_log_file($channel);
        
        if (!file_exists($log_file)) {
            return [];
        }
        
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];
        
        foreach (array_reverse(array_slice($lines, -$limit)) as $line) {
            $parsed = $this->parse_log_entry($line);
            
            if ($parsed && (!$level || $this->compare_log_levels($parsed['level'], $level) >= 0)) {
                $logs[] = $parsed;
            }
        }
        
        return array_reverse($logs);
    }
    
    /**
     * Get system diagnostics information
     * 
     * @return array
     */
    public function get_system_diagnostics() {
        global $wpdb;
        
        $diagnostics = [
            'timestamp' => current_time('mysql'),
            'plugin_version' => WCEFP_VERSION,
            'wordpress' => [
                'version' => get_bloginfo('version'),
                'multisite' => is_multisite(),
                'debug_mode' => WP_DEBUG,
                'memory_limit' => WP_MEMORY_LIMIT,
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_size' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'mysql_version' => $wpdb->db_version(),
                'max_input_vars' => ini_get('max_input_vars'),
                'memory_usage' => $this->format_bytes(memory_get_usage(true)),
                'peak_memory' => $this->format_bytes(memory_get_peak_usage(true))
            ],
            'woocommerce' => [
                'active' => class_exists('WooCommerce'),
                'version' => defined('WC_VERSION') ? WC_VERSION : 'Unknown',
                'base_currency' => get_option('woocommerce_currency'),
                'store_country' => get_option('woocommerce_default_country', 'Unknown')
            ],
            'plugin_settings' => [
                'logging_enabled' => $this->enabled,
                'log_level' => $this->log_level,
                'log_directory' => $this->log_dir,
                'log_directory_writable' => is_writable($this->log_dir),
                'onboarding_completed' => get_option('wcefp_onboarding_completed', false),
                'capabilities_version' => get_option('wcefp_capabilities_version', 'Not set')
            ],
            'integrations' => [
                'brevo_configured' => !empty(get_option('wcefp_brevo_api_key')),
                'ga4_configured' => !empty(get_option('wcefp_ga4_id')) || !empty(get_option('wcefp_gtm_id')),
                'google_reviews_configured' => !empty(get_option('wcefp_google_places_api_key')),
                'meta_pixel_configured' => !empty(get_option('wcefp_meta_pixel_id'))
            ],
            'database' => [
                'tables_exist' => $this->check_database_tables(),
                'option_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE 'wcefp_%'"),
                'transients_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcefp_%'")
            ],
            'recent_errors' => $this->get_recent_logs(self::CHANNEL_GENERAL, 10, self::ERROR)
        ];
        
        return apply_filters('wcefp_system_diagnostics', $diagnostics);
    }
    
    /**
     * Create diagnostic report
     * 
     * @return string
     */
    public function create_diagnostic_report() {
        $diagnostics = $this->get_system_diagnostics();
        
        $report = "WCEventsFP Diagnostic Report\n";
        $report .= "Generated: " . $diagnostics['timestamp'] . "\n";
        $report .= "Plugin Version: " . $diagnostics['plugin_version'] . "\n\n";
        
        $report .= "=== WordPress Information ===\n";
        foreach ($diagnostics['wordpress'] as $key => $value) {
            $report .= ucfirst(str_replace('_', ' ', $key)) . ": " . $this->format_diagnostic_value($value) . "\n";
        }
        
        $report .= "\n=== Server Information ===\n";
        foreach ($diagnostics['server'] as $key => $value) {
            $report .= ucfirst(str_replace('_', ' ', $key)) . ": " . $this->format_diagnostic_value($value) . "\n";
        }
        
        $report .= "\n=== WooCommerce Information ===\n";
        foreach ($diagnostics['woocommerce'] as $key => $value) {
            $report .= ucfirst(str_replace('_', ' ', $key)) . ": " . $this->format_diagnostic_value($value) . "\n";
        }
        
        $report .= "\n=== Plugin Settings ===\n";
        foreach ($diagnostics['plugin_settings'] as $key => $value) {
            $report .= ucfirst(str_replace('_', ' ', $key)) . ": " . $this->format_diagnostic_value($value) . "\n";
        }
        
        $report .= "\n=== Integrations Status ===\n";
        foreach ($diagnostics['integrations'] as $key => $value) {
            $report .= ucfirst(str_replace('_', ' ', $key)) . ": " . ($value ? 'Yes' : 'No') . "\n";
        }
        
        $report .= "\n=== Database Information ===\n";
        foreach ($diagnostics['database'] as $key => $value) {
            $report .= ucfirst(str_replace('_', ' ', $key)) . ": " . $this->format_diagnostic_value($value) . "\n";
        }
        
        if (!empty($diagnostics['recent_errors'])) {
            $report .= "\n=== Recent Errors ===\n";
            foreach ($diagnostics['recent_errors'] as $error) {
                $report .= "[{$error['timestamp']}] {$error['level']}: {$error['message']}\n";
            }
        }
        
        return $report;
    }
    
    /**
     * Clear log files
     * 
     * @param string $channel Optional channel to clear
     * @return bool
     */
    public function clear_logs($channel = null) {
        if ($channel) {
            $log_file = $this->get_log_file($channel);
            return file_exists($log_file) ? unlink($log_file) : true;
        }
        
        // Clear all logs
        $pattern = $this->log_dir . '*.log';
        foreach (glob($pattern) as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    /**
     * Rotate log files
     * 
     * @param string $channel Log channel
     */
    public function rotate_logs($channel = self::CHANNEL_GENERAL) {
        $log_file = $this->get_log_file($channel);
        
        if (!file_exists($log_file) || filesize($log_file) < $this->max_file_size) {
            return;
        }
        
        // Rotate existing files
        for ($i = $this->max_files - 1; $i > 0; $i--) {
            $old_file = $log_file . '.' . $i;
            $new_file = $log_file . '.' . ($i + 1);
            
            if (file_exists($old_file)) {
                if ($i === $this->max_files - 1) {
                    unlink($old_file); // Delete oldest file
                } else {
                    rename($old_file, $new_file);
                }
            }
        }
        
        // Move current log to .1
        rename($log_file, $log_file . '.1');
    }
    
    /**
     * Set custom error handler
     */
    public function set_error_handler() {
        set_error_handler([$this, 'handle_php_error']);
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_php_error($severity, $message, $file = '', $line = 0) {
        // Don't log if error reporting is disabled
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $level = $this->map_php_error_to_log_level($severity);
        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity
        ];
        
        $this->log($level, $message, $context, self::CHANNEL_GENERAL);
        
        // Don't interfere with normal error handling
        return false;
    }
    
    // Private helper methods...
    
    private function ensure_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
        
        // Create .htaccess to protect log files
        $htaccess_file = $this->log_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            file_put_contents($htaccess_file, "Deny from all\n");
        }
    }
    
    private function should_log($level) {
        $levels = [
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7
        ];
        
        return isset($levels[$level]) && isset($levels[$this->log_level]) 
               && $levels[$level] <= $levels[$this->log_level];
    }
    
    private function format_log_entry($level, $message, $context, $channel) {
        $timestamp = current_time('c');
        $user_id = get_current_user_id();
        $request_id = $this->get_request_id();
        
        $entry = [
            'timestamp' => $timestamp,
            'level' => strtoupper($level),
            'channel' => $channel,
            'message' => $message,
            'user_id' => $user_id,
            'request_id' => $request_id
        ];
        
        if (!empty($context)) {
            $entry['context'] = $context;
        }
        
        return json_encode($entry) . "\n";
    }
    
    private function write_to_file($log_entry, $channel) {
        $log_file = $this->get_log_file($channel);
        
        // Rotate logs if needed
        $this->rotate_logs($channel);
        
        // Write to file
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    private function get_log_file($channel) {
        $filename = 'wcefp-' . $channel . '.log';
        return $this->log_dir . $filename;
    }
    
    private function get_request_id() {
        static $request_id;
        
        if (!$request_id) {
            $request_id = substr(uniqid(), -8);
        }
        
        return $request_id;
    }
    
    private function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_CLIENT_IP',            // Proxy
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxy
            'HTTP_FORWARDED',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    private function parse_log_entry($line) {
        $decoded = json_decode($line, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        return null;
    }
    
    private function compare_log_levels($level1, $level2) {
        $levels = [
            self::EMERGENCY => 0,
            self::ALERT => 1,
            self::CRITICAL => 2,
            self::ERROR => 3,
            self::WARNING => 4,
            self::NOTICE => 5,
            self::INFO => 6,
            self::DEBUG => 7
        ];
        
        $level1_value = $levels[strtolower($level1)] ?? 7;
        $level2_value = $levels[strtolower($level2)] ?? 7;
        
        return $level1_value - $level2_value;
    }
    
    private function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes >= 1024 && $i < 4; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    private function check_database_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'wcefp_bookings',
            $wpdb->prefix . 'wcefp_resources',
            $wpdb->prefix . 'wcefp_channels',
            $wpdb->prefix . 'wcefp_commissions',
            $wpdb->prefix . 'wcefp_analytics'
        ];
        
        $existing = 0;
        foreach ($tables as $table) {
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table) {
                $existing++;
            }
        }
        
        return $existing . '/' . count($tables);
    }
    
    private function format_diagnostic_value($value) {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        if (is_array($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }
    
    private function map_php_error_to_log_level($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::ERROR;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return self::WARNING;
            case E_NOTICE:
            case E_USER_NOTICE:
                return self::NOTICE;
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return self::INFO;
            default:
                return self::DEBUG;
        }
    }
}

// Global convenience functions
if (!function_exists('wcefp_log')) {
    function wcefp_log($level, $message, $context = [], $channel = DiagnosticLogger::CHANNEL_GENERAL) {
        DiagnosticLogger::instance()->log($level, $message, $context, $channel);
    }
}

if (!function_exists('wcefp_log_error')) {
    function wcefp_log_error($message, $context = [], $channel = DiagnosticLogger::CHANNEL_GENERAL) {
        DiagnosticLogger::instance()->error($message, $context, $channel);
    }
}

if (!function_exists('wcefp_log_info')) {
    function wcefp_log_info($message, $context = [], $channel = DiagnosticLogger::CHANNEL_GENERAL) {
        DiagnosticLogger::instance()->info($message, $context, $channel);
    }
}

if (!function_exists('wcefp_log_debug')) {
    function wcefp_log_debug($message, $context = [], $channel = DiagnosticLogger::CHANNEL_GENERAL) {
        DiagnosticLogger::instance()->debug($message, $context, $channel);
    }
}