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
     * @return bool
     */
    public function is_available() {
        return class_exists('WCEFP_Gift');
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
     * @param mixed $default Default value
     * @return mixed
     */
    public function get($key, $default = false) {
        if (class_exists('WCEFP_Cache')) {
            return \WCEFP_Cache::get($key, $default);
        }
        return $default;
    }
    
    /**
     * Set cached data
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param int $expiration Expiration in seconds
     * @return bool
     */
    public function set($key, $data, $expiration = 3600) {
        if (class_exists('WCEFP_Cache')) {
            return \WCEFP_Cache::set($key, $data, $expiration);
        }
        return false;
    }
    
    /**
     * Delete cached data
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function delete($key) {
        if (class_exists('WCEFP_Cache')) {
            return \WCEFP_Cache::delete($key);
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