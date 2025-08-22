<?php
/**
 * Environment Utility Class
 * 
 * Shared utility for environment checking and system validation
 * 
 * @package WCEFP
 * @subpackage Utils
 * @since 2.1.0
 */

namespace WCEFP\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Environment checking and validation utilities
 */
class Environment {
    
    /**
     * Cache for environment test results
     * @var array
     */
    private static $test_cache = [];
    
    /**
     * Run complete environment test suite
     * 
     * @return array Array of test results
     */
    public static function run_full_tests() {
        if (!empty(self::$test_cache)) {
            return self::$test_cache;
        }
        
        self::$test_cache = [
            'php_version' => self::test_php_version(),
            'wp_version' => self::test_wp_version(), 
            'woocommerce' => self::test_woocommerce(),
            'memory_limit' => self::test_memory_limit(),
            'extensions' => self::test_php_extensions()
        ];
        
        return self::$test_cache;
    }
    
    /**
     * Test PHP version
     * 
     * @return array Test result
     */
    public static function test_php_version() {
        $required_version = '7.4.0';
        $current_version = PHP_VERSION;
        
        return [
            'name' => __('PHP Version', 'wceventsfp'),
            'status' => version_compare($current_version, $required_version, '>=') ? 'success' : 'error',
            'message' => sprintf(__('PHP %s (Required: %s+)', 'wceventsfp'), $current_version, $required_version),
            'critical' => true,
            'value' => $current_version,
            'required' => $required_version
        ];
    }
    
    /**
     * Test WordPress version
     * 
     * @return array Test result
     */
    public static function test_wp_version() {
        $required_version = '5.0';
        $current_version = get_bloginfo('version');
        
        return [
            'name' => __('WordPress Version', 'wceventsfp'),
            'status' => version_compare($current_version, $required_version, '>=') ? 'success' : 'warning',
            'message' => sprintf(__('WordPress %s (Recommended: %s+)', 'wceventsfp'), $current_version, $required_version),
            'critical' => false,
            'value' => $current_version,
            'required' => $required_version
        ];
    }
    
    /**
     * Test WooCommerce availability
     * 
     * @return array Test result
     */
    public static function test_woocommerce() {
        $is_active = class_exists('WooCommerce');
        
        return [
            'name' => __('WooCommerce', 'wceventsfp'),
            'status' => $is_active ? 'success' : 'error',
            'message' => $is_active ? 
                sprintf(__('WooCommerce %s Active', 'wceventsfp'), WC()->version ?? 'Unknown') : 
                __('WooCommerce not found - Required for full functionality', 'wceventsfp'),
            'critical' => true,
            'value' => $is_active ? (WC()->version ?? 'Unknown') : false,
            'required' => '5.0+'
        ];
    }
    
    /**
     * Test memory limit
     * 
     * @return array Test result
     */
    public static function test_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        $status = self::check_memory_status($memory_limit);
        
        return [
            'name' => __('Memory Limit', 'wceventsfp'),
            'status' => $status,
            'message' => self::get_memory_message($memory_limit, $status),
            'critical' => false,
            'value' => $memory_limit,
            'required' => '256M'
        ];
    }
    
    /**
     * Test PHP extensions
     * 
     * @return array Test result
     */
    public static function test_php_extensions() {
        $required_extensions = ['json', 'curl', 'mbstring', 'openssl'];
        $missing_extensions = [];
        
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $missing_extensions[] = $extension;
            }
        }
        
        $status = empty($missing_extensions) ? 'success' : 'error';
        
        return [
            'name' => __('PHP Extensions', 'wceventsfp'),
            'status' => $status,
            'message' => $status === 'success' ? 
                __('All required extensions available', 'wceventsfp') : 
                sprintf(__('Missing extensions: %s', 'wceventsfp'), implode(', ', $missing_extensions)),
            'critical' => true,
            'value' => $required_extensions,
            'missing' => $missing_extensions
        ];
    }
    
    /**
     * Check memory limit status
     * 
     * @param string $memory_limit
     * @return string Status (success|warning|error)
     */
    private static function check_memory_status($memory_limit) {
        if ($memory_limit === '-1') {
            return 'success'; // Unlimited
        }
        
        $memory_bytes = self::convert_memory_to_bytes($memory_limit);
        
        if ($memory_bytes >= 268435456) { // 256MB
            return 'success';
        } elseif ($memory_bytes >= 134217728) { // 128MB
            return 'warning';
        } else {
            return 'error';
        }
    }
    
    /**
     * Get memory limit message
     * 
     * @param string $memory_limit
     * @param string $status
     * @return string
     */
    private static function get_memory_message($memory_limit, $status) {
        switch ($status) {
            case 'success':
                return sprintf(__('Memory limit: %s (Excellent)', 'wceventsfp'), $memory_limit);
            case 'warning':
                return sprintf(__('Memory limit: %s (Adequate, 256MB+ recommended)', 'wceventsfp'), $memory_limit);
            case 'error':
                return sprintf(__('Memory limit: %s (Too low, may cause issues)', 'wceventsfp'), $memory_limit);
            default:
                return sprintf(__('Memory limit: %s', 'wceventsfp'), $memory_limit);
        }
    }
    
    /**
     * Convert memory limit string to bytes
     * 
     * @param string $memory_limit
     * @return int
     */
    public static function convert_memory_to_bytes($memory_limit) {
        if ($memory_limit === '-1') {
            return PHP_INT_MAX; // Unlimited
        }
        
        $unit = strtolower(substr($memory_limit, -1));
        $value = (int) $memory_limit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }
    
    /**
     * Get recommended installation mode based on environment
     * 
     * @return string Recommended mode
     */
    public static function get_recommended_mode() {
        $tests = self::run_full_tests();
        
        // Check for critical failures
        $critical_failures = array_filter($tests, function($test) {
            return $test['critical'] && $test['status'] === 'error';
        });
        
        if (!empty($critical_failures)) {
            return 'minimal'; // Safety mode
        }
        
        // Check memory limit
        $memory_status = $tests['memory_limit']['status'];
        
        if ($memory_status === 'error') {
            return 'minimal';
        } elseif ($memory_status === 'warning') {
            return 'progressive';
        } else {
            return 'standard';
        }
    }
    
    /**
     * Check if environment is suitable for installation
     * 
     * @return bool
     */
    public static function is_installation_possible() {
        $tests = self::run_full_tests();
        
        // Check for critical failures
        foreach ($tests as $test) {
            if ($test['critical'] && $test['status'] === 'error') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get performance score based on environment
     * 
     * @return int Score from 0-100
     */
    public static function get_performance_score() {
        $tests = self::run_full_tests();
        $score = 0;
        $total_tests = count($tests);
        
        foreach ($tests as $test) {
            switch ($test['status']) {
                case 'success':
                    $score += 100;
                    break;
                case 'warning':
                    $score += 60;
                    break;
                case 'error':
                    $score += 0;
                    break;
            }
        }
        
        return $total_tests > 0 ? round($score / $total_tests) : 0;
    }
    
    /**
     * Clear test cache
     */
    public static function clear_cache() {
        self::$test_cache = [];
    }
}