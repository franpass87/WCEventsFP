<?php
/**
 * Code Quality Automation System
 * 
 * Provides automated code quality checks, PHPCS integration, and
 * code analysis tools for maintaining high code standards.
 * 
 * @package WCEFP
 * @subpackage Quality
 * @since 2.1.4
 */

namespace WCEFP\Quality;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Code Quality Manager
 */
class CodeQualityManager {
    
    /**
     * Quality check types
     */
    const CHECK_SYNTAX = 'syntax';
    const CHECK_STANDARDS = 'standards';
    const CHECK_SECURITY = 'security';
    const CHECK_PERFORMANCE = 'performance';
    
    /**
     * Quality issues
     * 
     * @var array
     */
    private static $quality_issues = [];
    
    /**
     * Security patterns to check
     * 
     * @var array
     */
    private static $security_patterns = [
        // Unsafe SQL patterns
        '/\$wpdb->query\s*\(\s*[\'"][^\'\"]*\$/' => 'Potential SQL injection risk - use $wpdb->prepare()',
        '/\$_(?:GET|POST|REQUEST|COOKIE)\[/' => 'Unescaped user input - use sanitize_* functions',
        
        // Unsafe output patterns
        '/echo\s+\$[^;]*(?<!esc_html\(\$[^)]*\));/' => 'Potentially unescaped output - use esc_html()',
        
        // Dangerous functions
        '/eval\s*\(/' => 'Dangerous eval() usage',
        '/exec\s*\(/' => 'Potentially dangerous exec() usage'
    ];
    
    /**
     * Performance anti-patterns
     * 
     * @var array
     */
    private static $performance_patterns = [
        '/WP_Query.*posts_per_page.*-1/' => 'Unbounded queries can cause memory issues'
    ];
    
    /**
     * Initialize code quality system
     */
    public static function init() {
        // Only run in debug mode or for administrators
        if (!(WP_DEBUG || current_user_can('manage_options'))) {
            return;
        }
        
        add_action('admin_init', [__CLASS__, 'run_quality_checks']);
        add_action('wp_ajax_wcefp_run_quality_scan', [__CLASS__, 'ajax_run_quality_scan']);
        
        // Add quality check to system status
        add_filter('wcefp_system_diagnostics', [__CLASS__, 'add_quality_diagnostics']);
    }
    
    /**
     * Run comprehensive quality checks
     */
    public static function run_quality_checks() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $start_time = microtime(true);
        
        self::check_php_syntax();
        self::check_wordpress_standards();
        self::check_security_issues();
        self::check_performance_issues();
        
        $execution_time = microtime(true) - $start_time;
        
        DiagnosticLogger::instance()->info('Code quality checks completed', [
            'execution_time' => $execution_time,
            'issues_found' => count(self::$quality_issues)
        ], DiagnosticLogger::CHANNEL_GENERAL);
    }
    
    /**
     * Check PHP syntax errors
     */
    private static function check_php_syntax() {
        $plugin_files = self::get_plugin_php_files();
        
        foreach ($plugin_files as $file) {
            if (function_exists('php_check_syntax')) {
                if (!php_check_syntax($file)) {
                    self::$quality_issues[] = [
                        'type' => self::CHECK_SYNTAX,
                        'severity' => 'error',
                        'file' => $file,
                        'message' => 'PHP syntax error detected'
                    ];
                }
            } else {
                // Fallback: check for basic syntax issues
                $content = file_get_contents($file);
                if (strpos($content, '<?php') === false && strpos($content, '<?') === false) {
                    self::$quality_issues[] = [
                        'type' => self::CHECK_SYNTAX,
                        'severity' => 'error',
                        'file' => $file,
                        'message' => 'Missing PHP opening tag'
                    ];
                }
            }
        }
    }
    
    /**
     * Check WordPress coding standards
     */
    private static function check_wordpress_standards() {
        $plugin_files = self::get_plugin_php_files();
        
        foreach ($plugin_files as $file) {
            $content = file_get_contents($file);
            
            // Check for deprecated functions
            self::check_deprecated_functions($file, $content);
            
            // Check for proper sanitization
            self::check_sanitization($file, $content);
            
            // Check for nonce verification
            self::check_nonce_usage($file, $content);
            
            // Check for capability checks
            self::check_capability_usage($file, $content);
            
            // Check for translation functions
            self::check_translation_functions($file, $content);
        }
    }
    
    /**
     * Check for security issues
     */
    private static function check_security_issues() {
        $plugin_files = self::get_plugin_php_files();
        
        foreach ($plugin_files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            
            foreach (self::$security_patterns as $pattern => $description) {
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line_number = self::get_line_number_from_offset($content, $match[1]);
                        
                        self::$quality_issues[] = [
                            'type' => self::CHECK_SECURITY,
                            'severity' => 'error',
                            'file' => $file,
                            'line' => $line_number,
                            'message' => $description,
                            'code' => trim($lines[$line_number - 1] ?? '')
                        ];
                    }
                }
            }
        }
    }
    
    /**
     * Check for performance issues
     */
    private static function check_performance_issues() {
        $plugin_files = self::get_plugin_php_files();
        
        foreach ($plugin_files as $file) {
            $content = file_get_contents($file);
            $lines = explode("\n", $content);
            
            foreach (self::$performance_patterns as $pattern => $description) {
                if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                    foreach ($matches[0] as $match) {
                        $line_number = self::get_line_number_from_offset($content, $match[1]);
                        
                        self::$quality_issues[] = [
                            'type' => self::CHECK_PERFORMANCE,
                            'severity' => 'warning',
                            'file' => $file,
                            'line' => $line_number,
                            'message' => $description,
                            'code' => trim($lines[$line_number - 1] ?? '')
                        ];
                    }
                }
            }
        }
    }
    
    /**
     * AJAX handler for quality scan
     */
    public static function ajax_run_quality_scan() {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(-1);
        }
        
        // Clear previous issues
        self::$quality_issues = [];
        
        // Run all quality checks
        self::run_quality_checks();
        
        // Return results
        wp_send_json_success([
            'issues' => self::$quality_issues,
            'summary' => self::get_quality_summary()
        ]);
    }
    
    /**
     * Add quality diagnostics to system status
     */
    public static function add_quality_diagnostics($diagnostics) {
        $diagnostics['code_quality'] = [
            'syntax_errors' => count(array_filter(self::$quality_issues, function($issue) {
                return $issue['type'] === self::CHECK_SYNTAX;
            })),
            'standards_issues' => count(array_filter(self::$quality_issues, function($issue) {
                return $issue['type'] === self::CHECK_STANDARDS;
            })),
            'security_issues' => count(array_filter(self::$quality_issues, function($issue) {
                return $issue['type'] === self::CHECK_SECURITY;
            })),
            'performance_issues' => count(array_filter(self::$quality_issues, function($issue) {
                return $issue['type'] === self::CHECK_PERFORMANCE;
            })),
            'total_issues' => count(self::$quality_issues)
        ];
        
        return $diagnostics;
    }
    
    // Private helper methods
    
    private static function get_plugin_php_files() {
        $files = [];
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(WCEFP_PLUGIN_DIR, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' && 
                strpos($file->getPathname(), '/vendor/') === false &&
                strpos($file->getPathname(), '/node_modules/') === false) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    private static function check_deprecated_functions($file, $content) {
        $deprecated_functions = [
            'get_woocommerce_currency()' => "get_option('woocommerce_currency')",
            'mysql_query' => 'wpdb methods',
            'create_function' => 'anonymous functions'
        ];
        
        foreach ($deprecated_functions as $deprecated => $replacement) {
            if (strpos($content, str_replace('()', '', $deprecated)) !== false) {
                self::$quality_issues[] = [
                    'type' => self::CHECK_STANDARDS,
                    'severity' => 'warning',
                    'file' => $file,
                    'message' => "Deprecated function '$deprecated', use '$replacement' instead"
                ];
            }
        }
    }
    
    private static function check_sanitization($file, $content) {
        // Check for unsanitized user input
        if (preg_match('/\$_(?:GET|POST|REQUEST|COOKIE)\[/', $content)) {
            $lines = explode("\n", $content);
            
            foreach ($lines as $line_num => $line) {
                if (preg_match('/\$_(?:GET|POST|REQUEST|COOKIE)\[/', $line)) {
                    // Check if sanitization function is present on the same line or context
                    if (!preg_match('/(?:sanitize_|wp_kses|absint|intval)/', $line)) {
                        self::$quality_issues[] = [
                            'type' => self::CHECK_STANDARDS,
                            'severity' => 'warning',
                            'file' => $file,
                            'line' => $line_num + 1,
                            'message' => 'User input should be sanitized',
                            'code' => trim($line)
                        ];
                    }
                }
            }
        }
    }
    
    private static function check_nonce_usage($file, $content) {
        // Check if forms have nonce fields
        if (preg_match('/<form[^>]*method=[\'"]post[\'"][^>]*>/', $content)) {
            if (!preg_match('/wp_nonce_field|wp_create_nonce/', $content)) {
                self::$quality_issues[] = [
                    'type' => self::CHECK_STANDARDS,
                    'severity' => 'error',
                    'file' => $file,
                    'message' => 'POST forms should include nonce verification'
                ];
            }
        }
        
        // Check if AJAX handlers verify nonces
        if (preg_match('/wp_ajax_[a-zA-Z_]+/', $content)) {
            if (!preg_match('/check_ajax_referer|wp_verify_nonce/', $content)) {
                self::$quality_issues[] = [
                    'type' => self::CHECK_STANDARDS,
                    'severity' => 'error',
                    'file' => $file,
                    'message' => 'AJAX handlers should verify nonces'
                ];
            }
        }
    }
    
    private static function check_capability_usage($file, $content) {
        // Check if admin functions check user capabilities
        if (preg_match('/add_action\s*\(\s*[\'"]admin_/', $content)) {
            if (!preg_match('/current_user_can/', $content)) {
                self::$quality_issues[] = [
                    'type' => self::CHECK_STANDARDS,
                    'severity' => 'warning',
                    'file' => $file,
                    'message' => 'Admin actions should check user capabilities'
                ];
            }
        }
    }
    
    private static function check_translation_functions($file, $content) {
        // Check if translation functions use correct text domain
        if (preg_match_all('/__\s*\(\s*[\'"][^\'"]*[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/', $content, $matches)) {
            foreach ($matches[1] as $domain) {
                if ($domain !== 'wceventsfp') {
                    self::$quality_issues[] = [
                        'type' => self::CHECK_STANDARDS,
                        'severity' => 'warning',
                        'file' => $file,
                        'message' => "Translation function uses incorrect text domain: '$domain'"
                    ];
                }
            }
        }
    }
    
    // Utility methods
    
    private static function get_line_number_from_offset($content, $offset) {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
    
    private static function get_quality_summary() {
        $summary = [
            'total' => count(self::$quality_issues),
            'by_type' => [],
            'by_severity' => []
        ];
        
        foreach (self::$quality_issues as $issue) {
            $summary['by_type'][$issue['type']] = ($summary['by_type'][$issue['type']] ?? 0) + 1;
            $summary['by_severity'][$issue['severity']] = ($summary['by_severity'][$issue['severity']] ?? 0) + 1;
        }
        
        return $summary;
    }
    
    /**
     * Get all quality issues
     */
    public static function get_quality_issues() {
        return self::$quality_issues;
    }
    
    /**
     * Clear quality issues
     */
    public static function clear_quality_issues() {
        self::$quality_issues = [];
    }
}