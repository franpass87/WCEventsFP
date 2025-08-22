<?php
/**
 * WCEventsFP Shared Diagnostic Utilities
 * 
 * Centralized utility functions to eliminate code duplication across diagnostic tools
 * and provide consistent, robust implementations of common functionality.
 * 
 * @package WCEventsFP
 * @version 2.1.1
 */

if (!defined('ABSPATH') && !defined('WCEFP_DIAGNOSTIC_MODE')) {
    // Allow running in diagnostic mode outside WordPress
    define('WCEFP_DIAGNOSTIC_MODE', true);
}

/**
 * Convert memory limit value to bytes
 * Consolidated from multiple implementations across the codebase
 * 
 * @param string|int|null $val Memory value from ini_get() or config
 * @return int Memory in bytes, 0 if invalid, -1 if unlimited
 */
if (!function_exists('wcefp_convert_memory_to_bytes')) {
    function wcefp_convert_memory_to_bytes($val) {
        // Handle null, false, empty values
        if ($val === null || $val === false || $val === '') {
            return 0;
        }
        
        // Handle unlimited memory
        if ($val === '-1') {
            return -1;
        }
        
        // Handle numeric values (already in bytes)
        if (is_numeric($val)) {
            $bytes = (int) $val;
            return $bytes < 0 ? 0 : $bytes;
        }
        
        // Handle string values
        if (!is_string($val)) {
            return 0;
        }
        
        $val = trim($val);
        if ($val === '' || $val === '0') {
            return 0;
        }
        
        // Extract numeric part and unit - robust pattern matching
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([kmgtKMGT]?)$/i', $val, $matches)) {
            return 0; // Invalid format
        }
        
        $number = (float) $matches[1];
        $unit = strtolower($matches[2]);
        
        // Apply unit multipliers
        switch ($unit) {
            case 't':
                $number *= 1024; // fall through
            case 'g':
                $number *= 1024; // fall through
            case 'm':
                $number *= 1024; // fall through
            case 'k':
                $number *= 1024;
                break;
            default:
                // No unit means bytes (already handled above)
                break;
        }
        
        return (int) $number;
    }
}

/**
 * Check if PHP version meets minimum requirements
 * 
 * @param string $min_version Minimum required PHP version (default: 7.4.0)
 * @return array ['status' => bool, 'current' => string, 'required' => string, 'message' => string]
 */
if (!function_exists('wcefp_check_php_version')) {
    function wcefp_check_php_version($min_version = '7.4.0') {
        $current_version = PHP_VERSION;
        $meets_requirement = version_compare($current_version, $min_version, '>=');
        
        return [
            'status' => $meets_requirement,
            'current' => $current_version,
            'required' => $min_version,
            'message' => $meets_requirement 
                ? "PHP version OK: {$current_version}" 
                : "PHP {$min_version}+ required. Current: {$current_version}"
        ];
    }
}

/**
 * Check if required PHP extensions are loaded
 * 
 * @param array $extensions Array of required extension names
 * @return array ['status' => bool, 'missing' => array, 'loaded' => array, 'message' => string]
 */
if (!function_exists('wcefp_check_php_extensions')) {
    function wcefp_check_php_extensions($extensions = ['mysqli', 'json', 'mbstring']) {
        $missing = [];
        $loaded = [];
        
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                $loaded[] = $ext;
            } else {
                $missing[] = $ext;
            }
        }
        
        $status = empty($missing);
        $message = $status 
            ? "All required extensions loaded: " . implode(', ', $loaded)
            : "Missing extensions: " . implode(', ', $missing);
        
        return [
            'status' => $status,
            'missing' => $missing,
            'loaded' => $loaded,
            'message' => $message
        ];
    }
}

/**
 * Check if WooCommerce is active and available
 * 
 * @return array ['status' => bool, 'version' => string|null, 'message' => string]
 */
if (!function_exists('wcefp_check_woocommerce')) {
    function wcefp_check_woocommerce() {
        $is_active = class_exists('WooCommerce');
        $version = null;
        
        if ($is_active && defined('WC_VERSION')) {
            $version = WC_VERSION;
        }
        
        $message = $is_active 
            ? "WooCommerce is active" . ($version ? " (v{$version})" : "")
            : "WooCommerce not found - Plugin requires WooCommerce!";
        
        return [
            'status' => $is_active,
            'version' => $version,
            'message' => $message
        ];
    }
}

/**
 * Check if WordPress environment is available
 * 
 * @return array ['status' => bool, 'version' => string|null, 'message' => string]
 */
if (!function_exists('wcefp_check_wordpress')) {
    function wcefp_check_wordpress() {
        $is_wp = defined('ABSPATH') && function_exists('get_option');
        $version = null;
        
        if ($is_wp) {
            $version = get_option('wp_db_version');
        }
        
        $message = $is_wp 
            ? "WordPress context detected" . ($version ? " (DB v{$version})" : "")
            : "Not running in WordPress context (standalone diagnostic)";
        
        return [
            'status' => $is_wp,
            'version' => $version,
            'message' => $message
        ];
    }
}

/**
 * Format memory size for human-readable display
 * 
 * @param int $bytes Memory in bytes
 * @return string Formatted memory size (e.g., "256M", "1.5G")
 */
if (!function_exists('wcefp_format_memory_size')) {
    function wcefp_format_memory_size($bytes) {
        if ($bytes === -1) {
            return 'Unlimited';
        }
        
        if ($bytes === 0) {
            return '0B';
        }
        
        $units = ['B', 'K', 'M', 'G', 'T'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 1) . $units[$pow];
    }
}

/**
 * Perform comprehensive environment checks
 * Combines all individual checks into a single comprehensive report
 * 
 * @return array Complete environment check results
 */
if (!function_exists('wcefp_comprehensive_environment_check')) {
    function wcefp_comprehensive_environment_check() {
        $results = [
            'overall_status' => true,
            'critical_errors' => [],
            'warnings' => [],
            'checks' => []
        ];
        
        // PHP Version Check
        $php_check = wcefp_check_php_version();
        $results['checks']['php_version'] = $php_check;
        if (!$php_check['status']) {
            $results['critical_errors'][] = $php_check['message'];
            $results['overall_status'] = false;
        }
        
        // PHP Extensions Check
        $ext_check = wcefp_check_php_extensions();
        $results['checks']['php_extensions'] = $ext_check;
        if (!$ext_check['status']) {
            $results['critical_errors'][] = $ext_check['message'];
            $results['overall_status'] = false;
        }
        
        // Memory Check
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = wcefp_convert_memory_to_bytes($memory_limit);
        $memory_status = true;
        $memory_message = "Memory limit: " . wcefp_format_memory_size($memory_bytes);
        
        if ($memory_bytes !== -1 && $memory_bytes > 0 && $memory_bytes < 134217728) { // 128MB
            $memory_status = false;
            $memory_message .= " (Low memory warning - recommend 256M+)";
            $results['warnings'][] = $memory_message;
        }
        
        $results['checks']['memory'] = [
            'status' => $memory_status,
            'limit' => $memory_limit,
            'bytes' => $memory_bytes,
            'formatted' => wcefp_format_memory_size($memory_bytes),
            'message' => $memory_message
        ];
        
        // WordPress Check
        $wp_check = wcefp_check_wordpress();
        $results['checks']['wordpress'] = $wp_check;
        
        // WooCommerce Check
        $wc_check = wcefp_check_woocommerce();
        $results['checks']['woocommerce'] = $wc_check;
        if (!$wc_check['status']) {
            $results['critical_errors'][] = $wc_check['message'];
            $results['overall_status'] = false;
        }
        
        return $results;
    }
}

/**
 * Display formatted test result
 * Standardized output format for diagnostic tests
 * 
 * @param string $test_name Name of the test
 * @param bool $status Test result (true = pass, false = fail)
 * @param string $message Description message
 * @param array|string|null $details Additional details to display
 */
if (!function_exists('wcefp_display_test_result')) {
    function wcefp_display_test_result($test_name, $status, $message, $details = null) {
        $icon = $status ? '✅' : '❌';
        echo "   {$icon} {$test_name}: {$message}\n";
        
        if ($details) {
            if (is_array($details)) {
                foreach ($details as $key => $value) {
                    echo "      → {$key}: {$value}\n";
                }
            } else {
                echo "      → {$details}\n";
            }
        }
    }
}

/**
 * Display section header for diagnostic output
 * 
 * @param string $section_name Name of the diagnostic section
 * @param int $step_number Optional step number
 */
if (!function_exists('wcefp_display_section_header')) {
    function wcefp_display_section_header($section_name, $step_number = null) {
        $prefix = $step_number ? "{$step_number}. " : "";
        echo "\n{$prefix}{$section_name}...\n";
    }
}

/**
 * Emergency error display for critical failures
 * Safe error display that works even when WordPress/admin notices fail
 * 
 * @param string $title Error title
 * @param array|string $messages Error messages
 */
if (!function_exists('wcefp_emergency_error_display')) {
    function wcefp_emergency_error_display($title, $messages) {
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        
        // Try WordPress admin notice first
        if (function_exists('add_action') && !defined('WCEFP_DIAGNOSTIC_MODE')) {
            add_action('admin_notices', function() use ($title, $messages) {
                echo '<div class="notice notice-error is-dismissible">';
                echo '<p><strong>WCEventsFP Error - ' . esc_html($title) . '</strong></p>';
                echo '<ul>';
                foreach ($messages as $message) {
                    echo '<li>' . esc_html($message) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }, 1);
        } else {
            // Fallback: Direct HTML output for CLI/standalone mode
            $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
            echo "<!DOCTYPE html><html><head><title>WCEventsFP - {$safe_title}</title>";
            echo '<style>body{font-family:Arial,sans-serif;margin:40px;} .error{background:#f8d7da;color:#721c24;padding:20px;border:1px solid #f5c6cb;border-radius:5px;}</style>';
            echo '</head><body><div class="error">';
            echo "<h3>WCEventsFP Error - {$safe_title}</h3>";
            echo '<ul>';
            foreach ($messages as $message) {
                $safe_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
                echo "<li>{$safe_message}</li>";
            }
            echo '</ul>';
            echo '</div></body></html>';
        }
    }
}