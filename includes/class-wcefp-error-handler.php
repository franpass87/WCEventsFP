<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Enhanced Error Handler
 * Provides comprehensive error handling, user-friendly messages, and developer debugging tools
 */
class WCEFP_Error_Handler {
    
    private static $instance = null;
    private $error_log = [];
    private $debug_mode = false;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->debug_mode = defined('WP_DEBUG') && WP_DEBUG;
        
        // Hook into WordPress error handling
        add_action('wp_ajax_wcefp_get_error_log', [$this, 'ajax_get_error_log']);
        add_action('wp_ajax_wcefp_clear_error_log', [$this, 'ajax_clear_error_log']);
        
        // Register custom error handler for WCEFP operations
        set_error_handler([$this, 'handle_php_error'], E_USER_ERROR | E_USER_WARNING | E_USER_NOTICE);
    }
    
    /**
     * Handle PHP errors in WCEFP context
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Only handle errors from WCEFP files
        if (strpos($errfile, 'wcefp') === false) {
            return false; // Let WordPress handle it
        }
        
        $error_data = [
            'type' => $this->get_error_type_name($errno),
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'context' => $this->get_error_context()
        ];
        
        $this->log_error($error_data);
        
        // In debug mode, show detailed error
        if ($this->debug_mode) {
            $this->display_debug_error($error_data);
        } else {
            $this->display_user_friendly_error($errno);
        }
        
        return true; // Error handled
    }
    
    /**
     * Log WCEFP specific errors
     */
    public function log_error($error_data) {
        $this->error_log[] = $error_data;
        
        // Also log to WordPress error log if available
        if (function_exists('error_log')) {
            error_log(sprintf(
                '[WCEFP] %s: %s in %s on line %d',
                $error_data['type'],
                $error_data['message'],
                $error_data['file'],
                $error_data['line']
            ));
        }
        
        // Store in database for admin review
        $this->store_error_in_db($error_data);
    }
    
    /**
     * Handle booking-specific errors with user-friendly messages
     */
    public function handle_booking_error($error_code, $context = []) {
        $user_messages = [
            'BOOKING_FULL' => __('Sorry, this experience is fully booked. Please choose a different date.', 'wceventsfp'),
            'INVALID_DATE' => __('Please select a valid date for your booking.', 'wceventsfp'),
            'PAYMENT_FAILED' => __('Payment could not be processed. Please try again or contact support.', 'wceventsfp'),
            'INVENTORY_ERROR' => __('There was an issue checking availability. Please refresh and try again.', 'wceventsfp'),
            'RESOURCE_CONFLICT' => __('The selected time conflicts with another booking. Please choose a different slot.', 'wceventsfp'),
            'INVALID_CAPACITY' => __('Please select a valid number of participants.', 'wceventsfp'),
            'SYSTEM_ERROR' => __('A system error occurred. Our team has been notified.', 'wceventsfp')
        ];
        
        $user_message = $user_messages[$error_code] ?? $user_messages['SYSTEM_ERROR'];
        
        $error_data = [
            'type' => 'BOOKING_ERROR',
            'code' => $error_code,
            'message' => $user_message,
            'context' => $context,
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $this->log_error($error_data);
        
        return [
            'success' => false,
            'message' => $user_message,
            'error_code' => $error_code,
            'debug_info' => $this->debug_mode ? $context : null
        ];
    }
    
    /**
     * Get error context information
     */
    private function get_error_context() {
        return [
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'method' => $_SERVER['REQUEST_METHOD'] ?? '',
            'post_data' => $_POST ?? [],
            'get_data' => $_GET ?? [],
            'user_id' => get_current_user_id(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $this->get_client_ip(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
    
    /**
     * Store error in database for admin review
     */
    private function store_error_in_db($error_data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_error_log';
        
        // Create table if not exists
        $this->create_error_table();
        
        $wpdb->insert(
            $table_name,
            [
                'error_type' => $error_data['type'],
                'error_message' => $error_data['message'],
                'error_context' => json_encode($error_data['context'] ?? []),
                'user_id' => $error_data['user_id'],
                'created_at' => $error_data['timestamp']
            ],
            ['%s', '%s', '%s', '%d', '%s']
        );
    }
    
    /**
     * Create error log table
     */
    private function create_error_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_error_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            error_type VARCHAR(50) NOT NULL,
            error_message TEXT NOT NULL,
            error_context LONGTEXT,
            user_id BIGINT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL,
            resolved BOOLEAN DEFAULT FALSE,
            INDEX (error_type),
            INDEX (created_at),
            INDEX (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Display user-friendly error
     */
    private function display_user_friendly_error($errno) {
        $message = __('An error occurred. Please try again or contact support if the problem persists.', 'wceventsfp');
        
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => $message,
                'error_code' => 'SYSTEM_ERROR'
            ]);
        } else {
            wp_die($message, __('System Error', 'wceventsfp'), ['response' => 500]);
        }
    }
    
    /**
     * Display detailed error for debug mode
     */
    private function display_debug_error($error_data) {
        if (wp_doing_ajax()) {
            wp_send_json_error([
                'message' => $error_data['message'],
                'debug_info' => $error_data
            ]);
        } else {
            $debug_html = sprintf(
                '<h3>WCEFP Debug Error</h3><p><strong>%s:</strong> %s</p><p><strong>File:</strong> %s:%d</p><pre>%s</pre>',
                $error_data['type'],
                $error_data['message'],
                $error_data['file'],
                $error_data['line'],
                print_r($error_data['context'], true)
            );
            wp_die($debug_html, 'WCEFP Debug Error');
        }
    }
    
    /**
     * Get error type name from errno
     */
    private function get_error_type_name($errno) {
        $error_types = [
            E_USER_ERROR => 'Fatal Error',
            E_USER_WARNING => 'Warning',
            E_USER_NOTICE => 'Notice'
        ];
        
        return $error_types[$errno] ?? 'Unknown Error';
    }
    
    /**
     * AJAX handler to get error log (admin only)
     */
    public function ajax_get_error_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        check_ajax_referer('wcefp_admin_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_error_log';
        
        $page = intval($_POST['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $errors = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        wp_send_json_success([
            'errors' => $errors,
            'total' => $total,
            'page' => $page,
            'total_pages' => ceil($total / $limit)
        ]);
    }
    
    /**
     * AJAX handler to clear error log (admin only)
     */
    public function ajax_clear_error_log() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        check_ajax_referer('wcefp_admin_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_error_log';
        
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        wp_send_json_success(['message' => __('Error log cleared successfully', 'wceventsfp')]);
    }
    
    /**
     * Get recent errors for dashboard widget
     */
    public function get_recent_errors($limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_error_log';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE resolved = FALSE ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Initialize error handler
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize error handler
WCEFP_Error_Handler::init();