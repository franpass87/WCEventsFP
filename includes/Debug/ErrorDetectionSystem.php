<?php
/**
 * Error Detection and Debug System
 * 
 * Provides comprehensive error detection, debugging tools, and automated
 * issue reporting for the WCEventsFP plugin.
 * 
 * @package WCEFP
 * @subpackage Debug
 * @since 2.1.4
 */

namespace WCEFP\Debug;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Error Detection and Debug Manager
 */
class ErrorDetectionSystem {
    
    /**
     * Error categories
     */
    const ERROR_FATAL = 'fatal';
    const ERROR_WARNING = 'warning';
    const ERROR_NOTICE = 'notice';
    const ERROR_DEPRECATED = 'deprecated';
    const ERROR_COMPATIBILITY = 'compatibility';
    
    /**
     * Detected issues
     * 
     * @var array
     */
    private static $detected_issues = [];
    
    /**
     * Debug mode
     * 
     * @var bool
     */
    private static $debug_mode = false;
    
    /**
     * Initialize error detection system
     */
    public static function init() {
        self::$debug_mode = (defined('WP_DEBUG') && WP_DEBUG) || 
                           (defined('WCEFP_DEBUG') && WCEFP_DEBUG);
        
        if (self::$debug_mode) {
            self::register_error_handlers();
        }
        
        // Always check for critical issues
        add_action('wp_loaded', [__CLASS__, 'run_critical_checks'], 999);
        add_action('admin_init', [__CLASS__, 'run_admin_checks']);
        add_action('wp_footer', [__CLASS__, 'run_frontend_checks']);
        
        // Add admin notices for issues
        add_action('admin_notices', [__CLASS__, 'show_admin_notices']);
        
        // AJAX handlers for JavaScript errors
        add_action('wp_ajax_wcefp_log_js_error', [__CLASS__, 'handle_js_error_ajax']);
        add_action('wp_ajax_wcefp_log_console_error', [__CLASS__, 'handle_console_error_ajax']);
    }
    
    /**
     * Register error handlers
     */
    private static function register_error_handlers() {
        // PHP error handler
        set_error_handler([__CLASS__, 'handle_php_error']);
        
        // Exception handler
        set_exception_handler([__CLASS__, 'handle_exception']);
        
        // Script error detection
        add_action('wp_footer', [__CLASS__, 'add_js_error_handler']);
        add_action('admin_footer', [__CLASS__, 'add_js_error_handler']);
    }
    
    /**
     * Handle PHP errors
     */
    public static function handle_php_error($severity, $message, $file = '', $line = 0, $context = []) {
        // Don't interfere with error reporting settings
        if (!(error_reporting() & $severity)) {
            return false;
        }
        
        $error_type = self::get_error_type($severity);
        $issue = [
            'type' => 'php_error',
            'severity' => $error_type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'context' => self::sanitize_context($context),
            'timestamp' => current_time('mysql'),
            'backtrace' => self::get_clean_backtrace()
        ];
        
        self::log_issue($issue);
        
        // For plugin files, add to detected issues
        if (strpos($file, WCEFP_PLUGIN_DIR) !== false) {
            self::$detected_issues[] = $issue;
        }
        
        return false; // Don't interfere with default error handling
    }
    
    /**
     * Handle uncaught exceptions
     */
    public static function handle_exception($exception) {
        $issue = [
            'type' => 'exception',
            'severity' => self::ERROR_FATAL,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'timestamp' => current_time('mysql')
        ];
        
        self::log_issue($issue);
        
        if (strpos($exception->getFile(), WCEFP_PLUGIN_DIR) !== false) {
            self::$detected_issues[] = $issue;
        }
        
        // Graceful degradation for plugin errors
        if (self::is_plugin_error($exception)) {
            self::handle_plugin_error($exception);
        }
    }
    
    /**
     * Run critical system checks
     */
    public static function run_critical_checks() {
        $checks = [
            'check_php_version',
            'check_wordpress_version', 
            'check_woocommerce_compatibility',
            'check_required_functions',
            'check_file_permissions',
            'check_database_connection',
            'check_memory_limit'
        ];
        
        foreach ($checks as $check) {
            if (method_exists(__CLASS__, $check)) {
                $result = call_user_func([__CLASS__, $check]);
                
                if (!$result['passed']) {
                    self::$detected_issues[] = [
                        'type' => 'system_check',
                        'severity' => $result['severity'],
                        'check' => $check,
                        'message' => $result['message'],
                        'recommendation' => $result['recommendation'] ?? '',
                        'timestamp' => current_time('mysql')
                    ];
                }
            }
        }
    }
    
    /**
     * Run admin-specific checks
     */
    public static function run_admin_checks() {
        if (!is_admin()) {
            return;
        }
        
        self::check_admin_permissions();
        self::check_settings_integrity();
    }
    
    /**
     * Run frontend-specific checks
     */
    public static function run_frontend_checks() {
        if (is_admin()) {
            return;
        }
        
        self::check_asset_loading();
    }
    
    /**
     * Show admin notices for detected issues
     */
    public static function show_admin_notices() {
        if (!current_user_can('manage_options') || empty(self::$detected_issues)) {
            return;
        }
        
        $critical_issues = array_filter(self::$detected_issues, function($issue) {
            return $issue['severity'] === self::ERROR_FATAL;
        });
        
        $warning_issues = array_filter(self::$detected_issues, function($issue) {
            return $issue['severity'] === self::ERROR_WARNING;
        });
        
        if (!empty($critical_issues)) {
            self::show_critical_notice($critical_issues);
        }
        
        if (!empty($warning_issues) && count($warning_issues) <= 3) {
            self::show_warning_notice($warning_issues);
        }
    }
    
    /**
     * Add JavaScript error handler
     */
    public static function add_js_error_handler() {
        if (!self::$debug_mode) {
            return;
        }
        
        ?>
        <script>
        (function() {
            // Global error handler
            window.addEventListener('error', function(e) {
                if (typeof jQuery !== 'undefined' && typeof wcefp_admin !== 'undefined') {
                    jQuery.post(wcefp_admin.ajax_url, {
                        action: 'wcefp_log_js_error',
                        nonce: wcefp_admin.nonce,
                        message: e.message,
                        filename: e.filename,
                        lineno: e.lineno,
                        colno: e.colno,
                        stack: e.error ? e.error.stack : ''
                    });
                }
            });
            
            // Console error detection
            if (window.console && window.console.error) {
                var originalError = window.console.error;
                window.console.error = function() {
                    originalError.apply(console, arguments);
                    
                    var message = Array.prototype.slice.call(arguments).join(' ');
                    if (message.toLowerCase().includes('wcefp') || message.toLowerCase().includes('wceventsfp')) {
                        if (typeof jQuery !== 'undefined' && typeof wcefp_admin !== 'undefined') {
                            jQuery.post(wcefp_admin.ajax_url, {
                                action: 'wcefp_log_console_error',
                                nonce: wcefp_admin.nonce,
                                message: message
                            });
                        }
                    }
                };
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Handle JavaScript error AJAX
     */
    public static function handle_js_error_ajax() {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }
        
        $error_data = [
            'message' => sanitize_text_field($_POST['message'] ?? ''),
            'filename' => sanitize_text_field($_POST['filename'] ?? ''),
            'lineno' => absint($_POST['lineno'] ?? 0),
            'colno' => absint($_POST['colno'] ?? 0),
            'stack' => sanitize_textarea_field($_POST['stack'] ?? '')
        ];
        
        DiagnosticLogger::instance()->error('JavaScript Error', $error_data, DiagnosticLogger::CHANNEL_GENERAL);
        
        wp_die();
    }
    
    /**
     * Handle console error AJAX
     */
    public static function handle_console_error_ajax() {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }
        
        $message = sanitize_text_field($_POST['message'] ?? '');
        
        DiagnosticLogger::instance()->warning('Console Error', [
            'message' => $message
        ], DiagnosticLogger::CHANNEL_GENERAL);
        
        wp_die();
    }
    
    // System check methods
    
    private static function check_php_version() {
        $required = '7.4.0';
        $current = PHP_VERSION;
        
        return [
            'passed' => version_compare($current, $required, '>='),
            'severity' => self::ERROR_FATAL,
            'message' => "PHP version $current (minimum: $required)",
            'recommendation' => 'Upgrade PHP to version 7.4 or higher'
        ];
    }
    
    private static function check_wordpress_version() {
        $required = '5.0';
        $current = get_bloginfo('version');
        
        return [
            'passed' => version_compare($current, $required, '>='),
            'severity' => self::ERROR_FATAL,
            'message' => "WordPress version $current (minimum: $required)",
            'recommendation' => 'Upgrade WordPress to version 5.0 or higher'
        ];
    }
    
    private static function check_woocommerce_compatibility() {
        if (!class_exists('WooCommerce')) {
            return [
                'passed' => false,
                'severity' => self::ERROR_FATAL,
                'message' => 'WooCommerce not installed or activated',
                'recommendation' => 'Install and activate WooCommerce plugin'
            ];
        }
        
        $required = '5.0';
        $current = defined('WC_VERSION') ? WC_VERSION : '0.0';
        
        return [
            'passed' => version_compare($current, $required, '>='),
            'severity' => self::ERROR_WARNING,
            'message' => "WooCommerce version $current (recommended: $required+)",
            'recommendation' => 'Update WooCommerce to latest version'
        ];
    }
    
    private static function check_required_functions() {
        $required_functions = [
            'wp_remote_get',
            'wp_remote_post', 
            'wp_schedule_event',
            'wp_mkdir_p',
            'wp_upload_dir'
        ];
        
        $missing = [];
        foreach ($required_functions as $function) {
            if (!function_exists($function)) {
                $missing[] = $function;
            }
        }
        
        return [
            'passed' => empty($missing),
            'severity' => self::ERROR_FATAL,
            'message' => empty($missing) ? 'All required functions available' : 'Missing functions: ' . implode(', ', $missing),
            'recommendation' => 'Contact hosting provider - WordPress core functions are missing'
        ];
    }
    
    private static function check_file_permissions() {
        $paths_to_check = [
            WP_CONTENT_DIR . '/wcefp-logs/' => 'Log directory',
            wp_upload_dir()['basedir'] => 'Upload directory'
        ];
        
        $permission_issues = [];
        
        foreach ($paths_to_check as $path => $description) {
            if (!is_dir($path)) {
                wp_mkdir_p($path);
            }
            
            if (!is_writable($path)) {
                $permission_issues[] = $description;
            }
        }
        
        return [
            'passed' => empty($permission_issues),
            'severity' => self::ERROR_WARNING,
            'message' => empty($permission_issues) ? 'File permissions OK' : 'Permission issues: ' . implode(', ', $permission_issues),
            'recommendation' => 'Fix file permissions (755 for directories, 644 for files)'
        ];
    }
    
    private static function check_database_connection() {
        global $wpdb;
        
        $result = $wpdb->get_var("SELECT 1");
        
        return [
            'passed' => $result === '1',
            'severity' => self::ERROR_FATAL,
            'message' => $result === '1' ? 'Database connection OK' : 'Database connection failed',
            'recommendation' => 'Check database configuration and connectivity'
        ];
    }
    
    private static function check_memory_limit() {
        $memory_limit = self::get_memory_limit();
        $recommended = 256 * 1024 * 1024; // 256MB
        
        return [
            'passed' => $memory_limit >= $recommended,
            'severity' => self::ERROR_WARNING,
            'message' => sprintf('Memory limit: %s (recommended: %s)', size_format($memory_limit), size_format($recommended)),
            'recommendation' => 'Increase PHP memory limit to at least 256MB'
        ];
    }
    
    // Helper methods
    
    private static function get_error_type($severity) {
        switch ($severity) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::ERROR_FATAL;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return self::ERROR_WARNING;
            case E_NOTICE:
            case E_USER_NOTICE:
                return self::ERROR_NOTICE;
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return self::ERROR_DEPRECATED;
            default:
                return self::ERROR_NOTICE;
        }
    }
    
    private static function sanitize_context($context) {
        if (is_array($context)) {
            unset($context['password'], $context['pass'], $context['pwd'], $context['key'], $context['token']);
        }
        return $context;
    }
    
    private static function get_clean_backtrace() {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        
        return array_map(function($frame) {
            return [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null
            ];
        }, $backtrace);
    }
    
    private static function is_plugin_error($exception) {
        return strpos($exception->getFile(), WCEFP_PLUGIN_DIR) !== false;
    }
    
    private static function handle_plugin_error($exception) {
        if (!is_admin()) {
            add_filter('wcefp_disable_frontend', '__return_true');
        }
    }
    
    private static function log_issue($issue) {
        $level = $issue['severity'] === self::ERROR_FATAL ? 'error' : 
                ($issue['severity'] === self::ERROR_WARNING ? 'warning' : 'info');
                
        DiagnosticLogger::instance()->log($level, $issue['message'], $issue, DiagnosticLogger::CHANNEL_GENERAL);
    }
    
    private static function get_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        
        if ($memory_limit == -1) {
            return PHP_INT_MAX;
        }
        
        return wp_convert_hr_to_bytes($memory_limit);
    }
    
    private static function check_admin_permissions() {
        $screen = get_current_screen();
        
        if ($screen && strpos($screen->id, 'wcefp') !== false) {
            if (!current_user_can('manage_woocommerce')) {
                self::$detected_issues[] = [
                    'type' => 'permission_error',
                    'severity' => self::ERROR_WARNING,
                    'message' => 'User lacks required permissions for plugin admin pages',
                    'screen' => $screen->id,
                    'timestamp' => current_time('mysql')
                ];
            }
        }
    }
    
    private static function check_settings_integrity() {
        $critical_settings = [
            'wcefp_version' => WCEFP_VERSION,
            'wcefp_db_version' => WCEFP_VERSION
        ];
        
        foreach ($critical_settings as $setting => $expected) {
            $actual = get_option($setting);
            
            if ($actual === false) {
                self::$detected_issues[] = [
                    'type' => 'missing_setting',
                    'severity' => self::ERROR_WARNING,
                    'setting' => $setting,
                    'message' => "Critical setting '$setting' is missing",
                    'timestamp' => current_time('mysql')
                ];
            }
        }
    }
    
    private static function check_asset_loading() {
        // Check if required assets are available
        if (!wp_script_is('jquery', 'enqueued') && !wp_script_is('jquery', 'done')) {
            self::$detected_issues[] = [
                'type' => 'missing_asset',
                'severity' => self::ERROR_WARNING,
                'asset' => 'jquery',
                'message' => 'Required jQuery library not loaded',
                'timestamp' => current_time('mysql')
            ];
        }
    }
    
    private static function show_critical_notice($issues) {
        echo '<div class="notice notice-error"><p>';
        echo '<strong>' . __('WCEventsFP Critical Issues Detected:', 'wceventsfp') . '</strong><br>';
        
        foreach ($issues as $issue) {
            echo '• ' . esc_html($issue['message']) . '<br>';
        }
        
        echo '</p></div>';
    }
    
    private static function show_warning_notice($issues) {
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo '<strong>' . __('WCEventsFP Warnings:', 'wceventsfp') . '</strong><br>';
        
        foreach ($issues as $issue) {
            echo '• ' . esc_html($issue['message']) . '<br>';
        }
        
        echo '</p></div>';
    }
    
    /**
     * Get all detected issues
     */
    public static function get_detected_issues() {
        return self::$detected_issues;
    }
    
    /**
     * Clear detected issues
     */
    public static function clear_detected_issues() {
        self::$detected_issues = [];
    }
}