<?php
if (!defined('ABSPATH')) exit;

/**
 * Caching system for WCEventsFP plugin
 * 
 * @since 1.7.2
 */
class WCEFP_Cache {
    
    const CACHE_GROUP = 'wcefp';
    const DEFAULT_EXPIRATION = 3600; // 1 hour
    
    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @param mixed  $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = false) {
        $cache_key = self::build_cache_key($key);
        $data = wp_cache_get($cache_key, self::CACHE_GROUP);
        
        if ($data === false) {
            // Try transient fallback
            $data = get_transient($cache_key);
            if ($data !== false) {
                // Restore to object cache
                wp_cache_set($cache_key, $data, self::CACHE_GROUP);
            }
        }
        
        return $data !== false ? $data : $default;
    }
    
    /**
     * Set cached data
     *
     * @param string $key Cache key
     * @param mixed  $data Data to cache
     * @param int    $expiration Expiration in seconds
     * @return bool
     */
    public static function set($key, $data, $expiration = self::DEFAULT_EXPIRATION) {
        $cache_key = self::build_cache_key($key);
        
        // Set in object cache
        $result = wp_cache_set($cache_key, $data, self::CACHE_GROUP, $expiration);
        
        // Set transient as fallback
        set_transient($cache_key, $data, $expiration);
        
        \WCEFP\Utils\Logger::debug("Cache set: {$key}", ['expiration' => $expiration]);
        
        return $result;
    }
    
    /**
     * Delete cached data
     *
     * @param string $key Cache key
     * @return bool
     */
    public static function delete($key) {
        $cache_key = self::build_cache_key($key);
        
        wp_cache_delete($cache_key, self::CACHE_GROUP);
        delete_transient($cache_key);
        
        \WCEFP\Utils\Logger::debug("Cache deleted: {$key}");
        
        return true;
    }
    
    /**
     * Clear all plugin cache
     */
    public static function clear_all() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_wcefp_%'
        ));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_timeout_wcefp_%'
        ));
        
        // Clear object cache (if supported)
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }
        
        \WCEFP\Utils\Logger::info('All caches cleared');
    }
    
    /**
     * Get KPI data with caching
     *
     * @param int $days Number of days to analyze
     * @return array
     */
    public static function get_kpi_data($days = 30) {
        $cache_key = "kpi_data_{$days}";
        $data = self::get($cache_key);
        
        if ($data === false) {
            global $wpdb;
            
            $from = date('Y-m-d', strtotime("-{$days} days"));
            $orders_table = $wpdb->prefix . 'posts';
            $order_items_table = $wpdb->prefix . 'woocommerce_order_items';
            $order_itemmeta_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
            $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
            
            // Get orders count and revenue
            $orders_query = $wpdb->prepare("
                SELECT COUNT(*) as count, SUM(pm.meta_value) as revenue 
                FROM {$orders_table} p 
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
                WHERE p.post_type = 'shop_order' 
                AND p.post_status = 'wc-completed'
                AND pm.meta_key = '_order_total'
                AND p.post_date >= %s
            ", $from);
            
            $orders_data = $wpdb->get_row($orders_query, ARRAY_A);
            
            // Get occupancy rate
            $occupancy_query = $wpdb->prepare("
                SELECT 
                    AVG(CASE WHEN capacity > 0 THEN (booked * 100.0 / capacity) ELSE 0 END) as avg_occupancy
                FROM {$occurrences_table} 
                WHERE start_datetime >= %s 
                AND status = 'active'
            ", $from);
            
            $occupancy = $wpdb->get_var($occupancy_query);
            
            // Get top experience
            $top_experience_query = $wpdb->prepare("
                SELECT p.ID, p.post_title, COUNT(*) as bookings
                FROM {$orders_table} o
                JOIN {$order_items_table} oi ON o.ID = oi.order_id
                JOIN {$order_itemmeta_table} oim ON oi.order_item_id = oim.order_item_id
                JOIN {$wpdb->posts} p ON oim.meta_value = p.ID
                WHERE o.post_status = 'wc-completed'
                AND o.post_date >= %s
                AND oim.meta_key = '_product_id'
                GROUP BY p.ID, p.post_title
                ORDER BY bookings DESC
                LIMIT 1
            ", $from);
            
            $top_experience = $wpdb->get_row($top_experience_query, ARRAY_A);
            
            $data = [
                'orders_count' => (int)($orders_data['count'] ?? 0),
                'revenue' => (float)($orders_data['revenue'] ?? 0),
                'avg_occupancy' => round((float)($occupancy ?? 0), 1),
                'top_experience' => $top_experience ? [
                    'id' => (int)$top_experience['ID'],
                    'title' => $top_experience['post_title'],
                    'bookings' => (int)$top_experience['bookings']
                ] : null,
                'generated_at' => current_time('mysql')
            ];
            
            // Cache for 30 minutes
            self::set($cache_key, $data, 1800);
            
            \WCEFP\Utils\Logger::debug("KPI data generated and cached", $data);
        }
        
        return $data;
    }
    
    /**
     * Get product occurrences with caching
     *
     * @param int    $product_id
     * @param string $from_date
     * @param string $to_date
     * @return array
     */
    public static function get_product_occurrences($product_id, $from_date, $to_date) {
        $cache_key = "product_occurrences_{$product_id}_{$from_date}_{$to_date}";
        $data = self::get($cache_key);
        
        if ($data === false) {
            global $wpdb;
            
            $table = $wpdb->prefix . 'wcefp_occurrences';
            $query = $wpdb->prepare("
                SELECT id, start_datetime, end_datetime, capacity, booked, status
                FROM {$table}
                WHERE product_id = %d
                AND start_datetime BETWEEN %s AND %s
                ORDER BY start_datetime ASC
            ", $product_id, $from_date, $to_date);
            
            $data = $wpdb->get_results($query, ARRAY_A);
            
            // Cache for 15 minutes
            self::set($cache_key, $data, 900);
        }
        
        return $data;
    }
    
    /**
     * Invalidate product-related cache
     *
     * @param int $product_id
     */
    public static function invalidate_product_cache($product_id) {
        // Get all cache keys that might contain this product
        $patterns = [
            "product_occurrences_{$product_id}_*",
            "kpi_data_*",
            "calendar_events_*"
        ];
        
        foreach ($patterns as $pattern) {
            // This is simplified - in production you might want to track cache keys
            \WCEFP\Utils\Logger::debug("Invalidating cache pattern: {$pattern}");
        }
        
        // Clear KPI cache as it might be affected
        self::delete('kpi_data_30');
        self::delete('kpi_data_7');
    }
    
    /**
     * Build cache key with prefix
     *
     * @param string $key
     * @return string
     */
    private static function build_cache_key($key) {
        return 'wcefp_' . md5($key);
    }
    
    /**
     * Get cache statistics
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        
        $transient_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_wcefp_%'
        ));
        
        return [
            'transient_count' => (int)$transient_count,
            'object_cache_available' => wp_using_ext_object_cache(),
            'cache_group' => self::CACHE_GROUP
        ];
    }
}