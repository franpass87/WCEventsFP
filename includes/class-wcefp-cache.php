<?php
/**
 * Cache Helper per WCEventsFP
 * Migliora le performance con caching intelligente
 *
 * @package WCEventsFP
 * @since 1.7.2
 */

if (!defined('ABSPATH')) exit;

class WCEFP_Cache {
    
    const GROUP = 'wcefp';
    const DEFAULT_EXPIRATION = HOUR_IN_SECONDS;
    
    /**
     * Get cached data
     */
    public static function get($key, $default = null) {
        $cached = wp_cache_get($key, self::GROUP);
        return ($cached !== false) ? $cached : $default;
    }
    
    /**
     * Set cached data
     */
    public static function set($key, $data, $expiration = self::DEFAULT_EXPIRATION) {
        return wp_cache_set($key, $data, self::GROUP, $expiration);
    }
    
    /**
     * Delete cached data
     */
    public static function delete($key) {
        return wp_cache_delete($key, self::GROUP);
    }
    
    /**
     * Get or compute cached data
     */
    public static function remember($key, callable $callback, $expiration = self::DEFAULT_EXPIRATION) {
        $cached = self::get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $data = $callback();
        self::set($key, $data, $expiration);
        
        return $data;
    }
    
    /**
     * Clear all plugin cache
     */
    public static function flush() {
        // WordPress doesn't have group flush, so we use a simple approach
        wp_cache_flush();
    }
    
    /**
     * Generate cache key for occurrences
     */
    public static function occurrences_key($product_id, $date = null) {
        $suffix = $date ? '_' . sanitize_key($date) : '_all';
        return 'occurrences_' . $product_id . $suffix;
    }
    
    /**
     * Generate cache key for KPI data
     */
    public static function kpi_key($days = 30) {
        return 'kpi_data_' . $days . 'd';
    }
}