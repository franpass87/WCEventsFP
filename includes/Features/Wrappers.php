<?php
/**
 * Feature wrappers for legacy classes
 * 
 * @package WCEFP
 * @subpackage Features  
 * @since 2.1.1
 */

namespace WCEFP\Features;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Voucher Manager - wraps existing WCEFP_Gift class
 * Provides modern interface for legacy gift/voucher functionality
 */
class VoucherManager {
    
    /**
     * Initialize voucher system
     */
    public function __construct() {
        // Ensure legacy class is loaded and initialized
        if (class_exists('WCEFP_Gift')) {
            \WCEFP_Gift::init();
        }
    }
    
    /**
     * Check if voucher functionality is available
     * 
     * @return bool True if voucher system is available
     * @since 2.1.1
     */
    public function is_available() {
        $available = class_exists('WCEFP_Gift');
        
        if (!$available && class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::info('Voucher system not available', [
                'class' => 'WCEFP_Gift',
                'status' => 'class not found'
            ]);
        }
        
        return $available;
    }
}

/**
 * Cache Manager - wraps existing WCEFP_Cache class
 * Provides modern interface for legacy caching functionality
 */
class CacheManager {
    
    /**
     * Initialize cache system
     */
    public function __construct() {
        // Legacy cache class is auto-loaded, no initialization needed
    }
    
    /**
     * Get cached data
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if not found or on error
     * @return mixed Cached value or default
     * @since 2.1.1
     */
    public function get($key, $default = false) {
        if (!is_string($key) || empty($key)) {
            if (class_exists('WCEFP\\Utils\\Logger')) {
                \WCEFP\Utils\Logger::error('Invalid cache key provided', [
                    'key' => $key,
                    'type' => gettype($key),
                    'caller' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] ?? 'unknown'
                ]);
            }
            return $default;
        }
        
        if (class_exists('WCEFP_Cache')) {
            try {
                return \WCEFP_Cache::get($key, $default);
            } catch (\Throwable $e) {
                if (class_exists('WCEFP\\Utils\\Logger')) {
                    \WCEFP\Utils\Logger::error('Cache retrieval failed', [
                        'key' => $key,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                return $default;
            }
        }
        
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::warning('Legacy cache class not available', [
                'class' => 'WCEFP_Cache',
                'fallback' => 'returning default value'
            ]);
        }
        
        return $default;
    }
    
    /**
     * Set cached data with expiration
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration time in seconds
     * @return bool True on success, false on failure
     * @since 2.1.1
     */
    public function set($key, $data, $expiration = 3600) {
        if (!is_string($key) || empty($key)) {
            if (class_exists('WCEFP\\Utils\\Logger')) {
                \WCEFP\Utils\Logger::error('Invalid cache key for set operation', [
                    'key' => $key,
                    'type' => gettype($key)
                ]);
            }
            return false;
        }
        
        if (class_exists('WCEFP_Cache')) {
            try {
                return \WCEFP_Cache::set($key, $data, $expiration);
            } catch (\Throwable $e) {
                if (class_exists('WCEFP\\Utils\\Logger')) {
                    \WCEFP\Utils\Logger::error('Cache set operation failed', [
                        'key' => $key,
                        'error' => $e->getMessage()
                    ]);
                }
                return false;
            }
        }
        
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::warning('Legacy cache class not available for set operation', [
                'class' => 'WCEFP_Cache'
            ]);
        }
        
        return false;
    }
    
    /**
     * Delete cached data by key
     * 
     * @param string $key Cache key to delete
     * @return bool True on success, false on failure
     * @since 2.1.1
     */
    public function delete($key) {
        if (!is_string($key) || empty($key)) {
            if (class_exists('WCEFP\\Utils\\Logger')) {
                \WCEFP\Utils\Logger::error('Invalid cache key for delete operation', [
                    'key' => $key,
                    'type' => gettype($key)
                ]);
            }
            return false;
        }
        
        if (class_exists('WCEFP_Cache')) {
            try {
                return \WCEFP_Cache::delete($key);
            } catch (\Throwable $e) {
                if (class_exists('WCEFP\\Utils\\Logger')) {
                    \WCEFP\Utils\Logger::error('Cache delete operation failed', [
                        'key' => $key,
                        'error' => $e->getMessage()
                    ]);
                }
                return false;
            }
        }
        
        if (class_exists('WCEFP\\Utils\\Logger')) {
            \WCEFP\Utils\Logger::warning('Legacy cache class not available for delete operation', [
                'class' => 'WCEFP_Cache'
            ]);
        }
        
        return false;
    }
    
    /**
     * Check if cache functionality is available
     * 
     * @return bool
     */
    public function is_available() {
        return class_exists('WCEFP_Cache');
    }
}