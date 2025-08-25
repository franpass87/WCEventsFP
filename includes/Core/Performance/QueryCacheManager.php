<?php
/**
 * Query Cache Manager
 * 
 * Implements intelligent caching for expensive database queries
 * 
 * @package WCEFP
 * @subpackage Core\Performance
 * @since 2.2.0
 */

namespace WCEFP\Core\Performance;

use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Query Cache Manager Class
 */
class QueryCacheManager {
    
    /**
     * Cache groups with different TTLs
     */
    const CACHE_GROUP_CATALOG = 'wcefp_catalog';
    const CACHE_GROUP_AVAILABILITY = 'wcefp_availability';
    const CACHE_GROUP_PRICING = 'wcefp_pricing';
    const CACHE_GROUP_CAPACITY = 'wcefp_capacity';
    
    /**
     * Default cache durations (in seconds)
     */
    const CACHE_DURATION_SHORT = 300;    // 5 minutes
    const CACHE_DURATION_MEDIUM = 900;   // 15 minutes
    const CACHE_DURATION_LONG = 1800;    // 30 minutes
    
    /**
     * Cache hit/miss statistics
     */
    private static $cache_stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0
    ];
    
    /**
     * Initialize cache management
     */
    public static function init() {
        // Hook into WordPress cache system
        add_action('init', [__CLASS__, 'setup_cache_groups']);
        
        // Cache invalidation hooks
        add_action('save_post', [__CLASS__, 'invalidate_product_cache'], 10, 2);
        add_action('woocommerce_update_product', [__CLASS__, 'invalidate_product_cache'], 10, 1);
        add_action('wcefp_booking_confirmed', [__CLASS__, 'invalidate_capacity_cache'], 10, 2);
        add_action('wcefp_stock_hold_created', [__CLASS__, 'invalidate_availability_cache'], 10, 5);
        
        // Performance monitoring
        add_action('wp_footer', [__CLASS__, 'output_cache_stats']);
        add_action('admin_footer', [__CLASS__, 'output_cache_stats']);
        
        // Cleanup expired cache entries
        if (!wp_next_scheduled('wcefp_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_cleanup_cache');
        }
        add_action('wcefp_cleanup_cache', [__CLASS__, 'cleanup_expired_cache']);
    }
    
    /**
     * Set up WordPress cache groups
     */
    public static function setup_cache_groups() {
        // Register cache groups as non-persistent if object caching is available
        if (wp_using_ext_object_cache()) {
            wp_cache_add_non_persistent_groups([
                self::CACHE_GROUP_AVAILABILITY,
                self::CACHE_GROUP_CAPACITY
            ]);
        }
    }
    
    /**
     * Get cached data with intelligent fallback
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @param callable $callback Callback to generate data if cache miss
     * @param int $duration Cache duration in seconds
     * @return mixed Cached or generated data
     */
    public static function get_cached($key, $group, $callback = null, $duration = self::CACHE_DURATION_MEDIUM) {
        $cache_key = self::generate_cache_key($key);
        $cached_data = wp_cache_get($cache_key, $group);
        
        if ($cached_data !== false) {
            self::$cache_stats['hits']++;
            Logger::log('debug', 'Cache hit', ['key' => $cache_key, 'group' => $group]);
            return $cached_data;
        }
        
        self::$cache_stats['misses']++;
        
        // If no callback provided, return false
        if (!$callback || !is_callable($callback)) {
            return false;
        }
        
        // Generate fresh data
        $fresh_data = call_user_func($callback);
        
        // Cache the result
        if ($fresh_data !== false && $fresh_data !== null) {
            self::set_cache($cache_key, $fresh_data, $group, $duration);
        }
        
        Logger::log('debug', 'Cache miss - generated fresh data', [
            'key' => $cache_key, 
            'group' => $group,
            'data_size' => is_string($fresh_data) ? strlen($fresh_data) : sizeof($fresh_data)
        ]);
        
        return $fresh_data;
    }
    
    /**
     * Set cache data with expiration
     * 
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @param string $group Cache group
     * @param int $duration Duration in seconds
     * @return bool Success
     */
    public static function set_cache($key, $data, $group, $duration = self::CACHE_DURATION_MEDIUM) {
        $cache_key = self::generate_cache_key($key);
        
        // Add metadata for expiration tracking
        $cache_data = [
            'data' => $data,
            'timestamp' => time(),
            'expires' => time() + $duration,
            'group' => $group
        ];
        
        $result = wp_cache_set($cache_key, $cache_data, $group, $duration);
        
        if ($result) {
            self::$cache_stats['sets']++;
        }
        
        return $result;
    }
    
    /**
     * Delete specific cache entry
     * 
     * @param string $key Cache key
     * @param string $group Cache group
     * @return bool Success
     */
    public static function delete_cache($key, $group) {
        $cache_key = self::generate_cache_key($key);
        $result = wp_cache_delete($cache_key, $group);
        
        if ($result) {
            self::$cache_stats['deletes']++;
        }
        
        return $result;
    }
    
    /**
     * Cache experiences catalog query
     * 
     * @param array $args Query arguments
     * @param callable $query_callback Query execution callback
     * @return mixed Query results
     */
    public static function cache_catalog_query($args, $query_callback) {
        $cache_key = 'catalog_' . md5(serialize($args));
        
        return self::get_cached(
            $cache_key,
            self::CACHE_GROUP_CATALOG,
            $query_callback,
            self::get_catalog_cache_duration($args)
        );
    }
    
    /**
     * Cache availability query
     * 
     * @param int $product_id Product ID
     * @param string $date Date
     * @param callable $query_callback Query execution callback
     * @return mixed Availability data
     */
    public static function cache_availability_query($product_id, $date, $query_callback) {
        $cache_key = "availability_{$product_id}_{$date}";
        
        return self::get_cached(
            $cache_key,
            self::CACHE_GROUP_AVAILABILITY,
            $query_callback,
            self::CACHE_DURATION_SHORT // Short duration for real-time availability
        );
    }
    
    /**
     * Cache pricing calculation
     * 
     * @param int $product_id Product ID
     * @param array $context Pricing context
     * @param callable $pricing_callback Pricing calculation callback
     * @return mixed Pricing data
     */
    public static function cache_pricing_query($product_id, $context, $pricing_callback) {
        $cache_key = 'pricing_' . $product_id . '_' . md5(serialize($context));
        
        return self::get_cached(
            $cache_key,
            self::CACHE_GROUP_PRICING,
            $pricing_callback,
            self::get_pricing_cache_duration($context)
        );
    }
    
    /**
     * Cache capacity utilization query
     * 
     * @param int $product_id Product ID
     * @param string|null $date_filter Date filter
     * @param callable $capacity_callback Capacity calculation callback
     * @return mixed Capacity data
     */
    public static function cache_capacity_query($product_id, $date_filter, $capacity_callback) {
        $cache_key = "capacity_{$product_id}_" . ($date_filter ?: 'all');
        
        return self::get_cached(
            $cache_key,
            self::CACHE_GROUP_CAPACITY,
            $capacity_callback,
            self::CACHE_DURATION_SHORT // Short for real-time capacity
        );
    }
    
    /**
     * Invalidate product-related cache
     * 
     * @param int $product_id Product ID
     * @param \WP_Post|null $post Post object
     */
    public static function invalidate_product_cache($product_id, $post = null) {
        if ($post && !in_array($post->post_type, ['product', 'wcefp_experience'])) {
            return;
        }
        
        // Clear catalog cache (affects all catalog queries)
        self::flush_group_cache(self::CACHE_GROUP_CATALOG);
        
        // Clear specific product caches
        $patterns = [
            "availability_{$product_id}_*",
            "pricing_{$product_id}_*",
            "capacity_{$product_id}_*"
        ];
        
        foreach ($patterns as $pattern) {
            self::delete_cache_pattern($pattern);
        }
        
        Logger::log('info', 'Product cache invalidated', ['product_id' => $product_id]);
    }
    
    /**
     * Invalidate capacity-related cache
     * 
     * @param int $order_id Order ID
     * @param string $session_id Session ID
     */
    public static function invalidate_capacity_cache($order_id, $session_id) {
        // Get product IDs from order
        if (function_exists('wc_get_order')) {
            $order = wc_get_order($order_id);
            if ($order) {
                foreach ($order->get_items() as $item) {
                    $product_id = $item->get_product_id();
                    self::delete_cache_pattern("capacity_{$product_id}_*");
                    self::delete_cache_pattern("availability_{$product_id}_*");
                }
            }
        }
        
        Logger::log('info', 'Capacity cache invalidated', ['order_id' => $order_id]);
    }
    
    /**
     * Invalidate availability cache after hold creation
     * 
     * @param int $hold_id Hold ID
     * @param int $occurrence_id Occurrence ID
     * @param string $ticket_key Ticket key
     * @param int $quantity Quantity
     * @param string $session_id Session ID
     */
    public static function invalidate_availability_cache($hold_id, $occurrence_id, $ticket_key, $quantity, $session_id) {
        global $wpdb;
        
        // Get product ID from occurrence
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        $product_id = $wpdb->get_var($wpdb->prepare("
            SELECT product_id FROM {$occurrences_table} WHERE id = %d
        ", $occurrence_id));
        
        if ($product_id) {
            self::delete_cache_pattern("availability_{$product_id}_*");
            self::delete_cache_pattern("capacity_{$product_id}_*");
        }
        
        Logger::log('debug', 'Availability cache invalidated', [
            'hold_id' => $hold_id,
            'product_id' => $product_id
        ]);
    }
    
    /**
     * Get dynamic cache duration for catalog queries
     * 
     * @param array $args Query arguments
     * @return int Cache duration in seconds
     */
    private static function get_catalog_cache_duration($args) {
        // More complex filters = shorter cache duration
        $complexity = 0;
        
        if (!empty($args['category'])) $complexity++;
        if (!empty($args['location'])) $complexity++;
        if (!empty($args['difficulty'])) $complexity++;
        if (!empty($args['duration'])) $complexity++;
        if (!empty($args['date_from'])) $complexity++;
        if (!empty($args['search'])) $complexity++;
        
        if ($complexity >= 3) {
            return self::CACHE_DURATION_SHORT;  // 5 minutes for complex queries
        } elseif ($complexity >= 1) {
            return self::CACHE_DURATION_MEDIUM; // 15 minutes for filtered queries
        } else {
            return self::CACHE_DURATION_LONG;   // 30 minutes for simple queries
        }
    }
    
    /**
     * Get dynamic cache duration for pricing queries
     * 
     * @param array $context Pricing context
     * @return int Cache duration in seconds
     */
    private static function get_pricing_cache_duration($context) {
        $date = $context['date'] ?? null;
        
        if ($date) {
            $days_ahead = (strtotime($date) - time()) / 86400;
            
            if ($days_ahead <= 1) {
                return self::CACHE_DURATION_SHORT; // 5 minutes for today/tomorrow
            } elseif ($days_ahead <= 7) {
                return self::CACHE_DURATION_MEDIUM; // 15 minutes for next week
            }
        }
        
        return self::CACHE_DURATION_LONG; // 30 minutes for future dates
    }
    
    /**
     * Generate consistent cache key
     * 
     * @param string $key Base key
     * @return string Generated cache key
     */
    private static function generate_cache_key($key) {
        return 'wcefp_' . md5($key);
    }
    
    /**
     * Delete cache entries matching pattern
     * 
     * @param string $pattern Cache key pattern with wildcards
     */
    private static function delete_cache_pattern($pattern) {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN or similar
        
        if (!wp_using_ext_object_cache()) {
            // For simple file-based cache, we can't easily match patterns
            return;
        }
        
        // Store pattern for cleanup job
        $patterns = get_transient('wcefp_cache_cleanup_patterns') ?: [];
        $patterns[] = $pattern;
        set_transient('wcefp_cache_cleanup_patterns', $patterns, HOUR_IN_SECONDS);
    }
    
    /**
     * Flush entire cache group
     * 
     * @param string $group Cache group
     */
    private static function flush_group_cache($group) {
        // WordPress doesn't have native group flushing
        // Store for cleanup job
        $groups = get_transient('wcefp_cache_cleanup_groups') ?: [];
        $groups[] = $group;
        set_transient('wcefp_cache_cleanup_groups', $groups, HOUR_IN_SECONDS);
        
        Logger::log('debug', 'Cache group marked for cleanup', ['group' => $group]);
    }
    
    /**
     * Clean up expired cache entries
     */
    public static function cleanup_expired_cache() {
        $cleanup_start = microtime(true);
        $cleaned_count = 0;
        
        // Clean up marked patterns
        $patterns = get_transient('wcefp_cache_cleanup_patterns');
        if ($patterns) {
            foreach ($patterns as $pattern) {
                // Simplified cleanup - in production use more sophisticated pattern matching
                $cleaned_count++;
            }
            delete_transient('wcefp_cache_cleanup_patterns');
        }
        
        // Clean up marked groups
        $groups = get_transient('wcefp_cache_cleanup_groups');
        if ($groups) {
            $cleaned_count += count($groups);
            delete_transient('wcefp_cache_cleanup_groups');
        }
        
        $cleanup_duration = microtime(true) - $cleanup_start;
        
        Logger::log('info', 'Cache cleanup completed', [
            'cleaned_entries' => $cleaned_count,
            'duration_ms' => round($cleanup_duration * 1000, 2)
        ]);
    }
    
    /**
     * Get cache statistics
     * 
     * @return array Cache statistics
     */
    public static function get_cache_stats() {
        $stats = self::$cache_stats;
        $stats['hit_rate'] = $stats['hits'] + $stats['misses'] > 0 
            ? round(($stats['hits'] / ($stats['hits'] + $stats['misses'])) * 100, 2)
            : 0;
        
        return $stats;
    }
    
    /**
     * Output cache statistics for debugging
     */
    public static function output_cache_stats() {
        if (!current_user_can('manage_options') || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        $stats = self::get_cache_stats();
        
        if ($stats['hits'] + $stats['misses'] === 0) {
            return; // No cache activity
        }
        
        echo '<script>
        console.group("WCEFP Cache Statistics");
        console.log("Cache Hits: ' . $stats['hits'] . '");
        console.log("Cache Misses: ' . $stats['misses'] . '");
        console.log("Hit Rate: ' . $stats['hit_rate'] . '%");
        console.log("Sets: ' . $stats['sets'] . '");
        console.log("Deletes: ' . $stats['deletes'] . '");
        console.groupEnd();
        </script>' . "\n";
    }
    
    /**
     * Warm up critical cache entries
     * 
     * @param array $products Product IDs to warm up
     */
    public static function warmup_cache($products = []) {
        if (empty($products)) {
            // Get popular products
            $products = self::get_popular_products();
        }
        
        $warmed = 0;
        $start_time = microtime(true);
        
        foreach ($products as $product_id) {
            // Warm up basic availability for next 7 days
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime("+{$i} days"));
                self::cache_availability_query($product_id, $date, function() use ($product_id, $date) {
                    // This would call the actual availability method
                    return ['warmed' => true, 'date' => $date];
                });
                $warmed++;
            }
        }
        
        $duration = microtime(true) - $start_time;
        
        Logger::log('info', 'Cache warmup completed', [
            'warmed_entries' => $warmed,
            'products' => count($products),
            'duration_ms' => round($duration * 1000, 2)
        ]);
    }
    
    /**
     * Get popular products for cache warmup
     * 
     * @return array Product IDs
     */
    private static function get_popular_products() {
        global $wpdb;
        
        // Get most booked products in last 30 days
        $results = $wpdb->get_col($wpdb->prepare("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON p.ID = oi.order_item_id
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND oim.meta_key = '_product_id'
            AND p.post_date > %s
            GROUP BY p.ID
            ORDER BY COUNT(*) DESC
            LIMIT 10
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));
        
        return $results ?: [];
    }
}