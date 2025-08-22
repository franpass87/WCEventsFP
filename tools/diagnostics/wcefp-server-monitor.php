<?php
/**
 * WCEventsFP Server Resources Monitor
 * 
 * Detects server capabilities and recommends appropriate loading modes
 * to prevent WSOD on resource-constrained servers.
 * 
 * @package WCEventsFP
 * @version 2.1.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load shared utilities for consistent memory conversion
if (file_exists(__DIR__ . '/wcefp-shared-utilities.php')) {
    require_once __DIR__ . '/wcefp-shared-utilities.php';
}

/**
 * Server Resources Monitor Class
 */
class WCEFP_Server_Monitor {
    
    /**
     * Resource thresholds
     */
    const MEMORY_MINIMAL_MB = 64;    // 64MB - bare minimum
    const MEMORY_LOW_MB = 128;       // 128MB - low resources
    const MEMORY_STANDARD_MB = 256;  // 256MB - standard
    const MEMORY_HIGH_MB = 512;      // 512MB - high performance
    
    const EXECUTION_MINIMAL = 15;    // 15 seconds
    const EXECUTION_LOW = 30;        // 30 seconds  
    const EXECUTION_STANDARD = 60;   // 60 seconds
    const EXECUTION_HIGH = 120;      // 120 seconds
    
    /**
     * Loading modes
     */
    const MODE_ULTRA_MINIMAL = 'ultra_minimal';  // Absolute bare bones - emergency mode
    const MODE_MINIMAL = 'minimal';              // Basic functionality only
    const MODE_PROGRESSIVE = 'progressive';      // Load features gradually
    const MODE_STANDARD = 'standard';            // Normal loading
    const MODE_FULL = 'full';                   // All features enabled
    
    /**
     * Server resource data cache
     * @var array|null
     */
    private static $resource_data = null;
    
    /**
     * Get server resource information
     * 
     * @return array
     */
    public static function get_server_resources() {
        if (self::$resource_data !== null) {
            return self::$resource_data;
        }
        
        self::$resource_data = [
            'memory_limit' => self::get_memory_limit_bytes(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_available' => self::get_available_memory(),
            'execution_time' => self::get_max_execution_time(),
            'php_version' => PHP_VERSION,
            'loaded_extensions' => get_loaded_extensions(),
            'server_load' => self::get_server_load(),
            'wp_debug' => defined('WP_DEBUG') && WP_DEBUG,
            'timestamp' => time()
        ];
        
        return self::$resource_data;
    }
    
    /**
     * Get memory limit in bytes
     * 
     * @return int
     */
    public static function get_memory_limit_bytes() {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return -1; // Unlimited
        }
        
        return self::convert_memory_to_bytes($limit);
    }
    
    /**
     * Get available memory (limit minus current usage)
     * 
     * @return int
     */
    public static function get_available_memory() {
        $limit = self::get_memory_limit_bytes();
        $usage = memory_get_usage(true);
        
        if ($limit === -1) {
            return -1; // Unlimited
        }
        
        return max(0, $limit - $usage);
    }
    
    /**
     * Get max execution time
     * 
     * @return int
     */
    public static function get_max_execution_time() {
        return (int) ini_get('max_execution_time');
    }
    
    /**
     * Get server load if available
     * 
     * @return float|null
     */
    public static function get_server_load() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return isset($load[0]) ? (float) $load[0] : null;
        }
        
        return null;
    }
    
    /**
     * Convert memory value to bytes
     * 
     * @param string|int $val
     * @return int
     */
    private static function convert_memory_to_bytes($val) {
        // Use shared utilities if available
        if (function_exists('wcefp_convert_memory_to_bytes')) {
            return wcefp_convert_memory_to_bytes($val);
        }
        
        // Fallback implementation
        if (is_numeric($val)) {
            return (int) $val;
        }
        
        $val = trim($val);
        if (empty($val)) {
            return 0;
        }
        
        $unit = strtolower(substr($val, -1));
        $val = (int) $val;
        
        switch ($unit) {
            case 'g': $val *= 1024; // fall through
            case 'm': $val *= 1024; // fall through  
            case 'k': $val *= 1024; break;
        }
        
        return $val;
    }
    
    /**
     * Determine recommended loading mode based on server resources
     * 
     * @return string
     */
    public static function get_recommended_loading_mode() {
        $resources = self::get_server_resources();
        $memory_mb = $resources['memory_limit'] / 1048576; // Convert to MB
        $available_mb = $resources['memory_available'] / 1048576;
        $execution_time = $resources['execution_time'];
        
        // Ultra minimal mode for severely constrained servers
        if ($memory_mb > 0 && $memory_mb < self::MEMORY_MINIMAL_MB) {
            return self::MODE_ULTRA_MINIMAL;
        }
        
        if ($available_mb > 0 && $available_mb < 32) { // Less than 32MB available
            return self::MODE_ULTRA_MINIMAL;
        }
        
        if ($execution_time > 0 && $execution_time < self::EXECUTION_MINIMAL) {
            return self::MODE_ULTRA_MINIMAL;
        }
        
        // Minimal mode for low-resource servers  
        if ($memory_mb > 0 && $memory_mb < self::MEMORY_LOW_MB) {
            return self::MODE_MINIMAL;
        }
        
        if ($available_mb > 0 && $available_mb < 64) { // Less than 64MB available
            return self::MODE_MINIMAL;
        }
        
        if ($execution_time > 0 && $execution_time < self::EXECUTION_LOW) {
            return self::MODE_MINIMAL;
        }
        
        // Progressive mode for moderate servers
        if ($memory_mb > 0 && $memory_mb < self::MEMORY_STANDARD_MB) {
            return self::MODE_PROGRESSIVE;
        }
        
        if ($execution_time > 0 && $execution_time < self::EXECUTION_STANDARD) {
            return self::MODE_PROGRESSIVE;
        }
        
        // Standard mode for good servers
        if ($memory_mb > 0 && $memory_mb < self::MEMORY_HIGH_MB) {
            return self::MODE_STANDARD;
        }
        
        // Full mode for high-performance servers
        return self::MODE_FULL;
    }
    
    /**
     * Get resource score (0-100)
     * 
     * @return int
     */
    public static function get_resource_score() {
        $resources = self::get_server_resources();
        
        // Memory score (0-50)
        $memory_score = 0;
        $memory_mb = $resources['memory_limit'] / 1048576;
        
        if ($memory_mb === -1) {
            $memory_score = 50; // Unlimited
        } else {
            $memory_score = min(50, ($memory_mb / self::MEMORY_HIGH_MB) * 50);
        }
        
        // Execution time score (0-30)
        $execution_score = 0;
        $execution_time = $resources['execution_time'];
        
        if ($execution_time === 0) {
            $execution_score = 30; // Unlimited
        } else {
            $execution_score = min(30, ($execution_time / self::EXECUTION_HIGH) * 30);
        }
        
        // Available memory bonus (0-20)
        $available_score = 0;
        if ($resources['memory_available'] > 0) {
            $available_mb = $resources['memory_available'] / 1048576;
            $available_score = min(20, ($available_mb / 128) * 20);
        }
        
        return (int) ($memory_score + $execution_score + $available_score);
    }
    
    /**
     * Check if server can handle standard loading
     * 
     * @return bool
     */
    public static function can_handle_standard_loading() {
        $mode = self::get_recommended_loading_mode();
        return in_array($mode, [self::MODE_STANDARD, self::MODE_FULL]);
    }
    
    /**
     * Get feature limits based on resources
     * 
     * @return array
     */
    public static function get_feature_limits() {
        $mode = self::get_recommended_loading_mode();
        
        switch ($mode) {
            case self::MODE_ULTRA_MINIMAL:
                return [
                    'max_features' => 1,
                    'features_per_load' => 1,
                    'load_delay_ms' => 2000,
                    'enable_caching' => false,
                    'enable_logging' => false,
                    'allowed_features' => ['core']
                ];
                
            case self::MODE_MINIMAL:
                return [
                    'max_features' => 3,
                    'features_per_load' => 1,
                    'load_delay_ms' => 1000,
                    'enable_caching' => false,
                    'enable_logging' => true,
                    'allowed_features' => ['core', 'admin_basic', 'woocommerce_basic']
                ];
                
            case self::MODE_PROGRESSIVE:
                return [
                    'max_features' => 6,
                    'features_per_load' => 2,
                    'load_delay_ms' => 500,
                    'enable_caching' => true,
                    'enable_logging' => true,
                    'allowed_features' => ['core', 'admin_basic', 'admin_enhanced', 'woocommerce_basic', 'resources', 'channels']
                ];
                
            case self::MODE_STANDARD:
                return [
                    'max_features' => 10,
                    'features_per_load' => 3,
                    'load_delay_ms' => 200,
                    'enable_caching' => true,
                    'enable_logging' => true,
                    'allowed_features' => ['core', 'admin_basic', 'admin_enhanced', 'woocommerce_basic', 'resources', 'channels', 'commissions', 'reviews', 'tracking', 'automation']
                ];
                
            case self::MODE_FULL:
            default:
                return [
                    'max_features' => -1, // Unlimited
                    'features_per_load' => 5,
                    'load_delay_ms' => 0,
                    'enable_caching' => true,
                    'enable_logging' => true,
                    'allowed_features' => [] // All features allowed
                ];
        }
    }
    
    /**
     * Generate server report
     * 
     * @return array
     */
    public static function generate_report() {
        $resources = self::get_server_resources();
        $mode = self::get_recommended_loading_mode();
        $score = self::get_resource_score();
        $limits = self::get_feature_limits();
        
        return [
            'timestamp' => $resources['timestamp'],
            'memory_limit_mb' => round($resources['memory_limit'] / 1048576, 1),
            'memory_usage_mb' => round($resources['memory_usage'] / 1048576, 1),
            'memory_available_mb' => round($resources['memory_available'] / 1048576, 1),
            'execution_time' => $resources['execution_time'],
            'php_version' => $resources['php_version'],
            'server_load' => $resources['server_load'],
            'recommended_mode' => $mode,
            'resource_score' => $score,
            'feature_limits' => $limits,
            'can_handle_standard' => self::can_handle_standard_loading(),
            'status' => self::get_status_message($mode, $score)
        ];
    }
    
    /**
     * Get status message based on mode and score
     * 
     * @param string $mode
     * @param int $score
     * @return string
     */
    private static function get_status_message($mode, $score) {
        switch ($mode) {
            case self::MODE_ULTRA_MINIMAL:
                return 'Server resources are very limited. Plugin will run in emergency mode with minimal features.';
                
            case self::MODE_MINIMAL:
                return 'Server resources are limited. Plugin will run with basic features only.';
                
            case self::MODE_PROGRESSIVE:
                return 'Server resources are moderate. Plugin will load features progressively.';
                
            case self::MODE_STANDARD:
                return 'Server resources are good. Plugin will run in standard mode.';
                
            case self::MODE_FULL:
                return 'Server resources are excellent. All features available.';
                
            default:
                return 'Resource status unknown.';
        }
    }
    
    /**
     * Log resource information for debugging
     */
    public static function log_resources() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $report = self::generate_report();
        error_log('WCEFP Server Monitor: ' . json_encode($report));
    }
}

/**
 * Get server monitor instance
 * 
 * @return WCEFP_Server_Monitor
 */
function wcefp_server_monitor() {
    return new WCEFP_Server_Monitor();
}

/**
 * Quick check if server can handle plugin activation
 * 
 * @return bool
 */
function wcefp_can_activate_safely() {
    $mode = WCEFP_Server_Monitor::get_recommended_loading_mode();
    return $mode !== WCEFP_Server_Monitor::MODE_ULTRA_MINIMAL;
}