<?php
/**
 * Database Query Optimizer
 * 
 * Eliminates N+1 queries and optimizes database operations
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
 * Database Query Optimizer Class
 */
class DatabaseOptimizer {
    
    /**
     * Query cache for current request
     */
    private static $query_cache = [];
    
    /**
     * Product metadata cache
     */
    private static $product_meta_cache = [];
    
    /**
     * Occurrence cache
     */
    private static $occurrence_cache = [];
    
    /**
     * Initialize database optimization
     */
    public static function init() {
        // Pre-load commonly used data
        add_action('wp', [__CLASS__, 'preload_common_data']);
        
        // Query monitoring for development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', [__CLASS__, 'log_query_stats']);
        }
    }
    
    /**
     * Preload commonly used data to prevent N+1 queries
     */
    public static function preload_common_data() {
        if (!self::should_preload()) {
            return;
        }
        
        // Preload product metadata for experience products on catalog pages
        if (is_page() || is_shop() || is_product_category()) {
            self::preload_experience_metadata();
        }
    }
    
    /**
     * Batch load product metadata to eliminate N+1 queries
     * 
     * @param array $product_ids Product IDs to load
     * @param array $meta_keys Specific meta keys to load (optional)
     * @return array Loaded metadata
     */
    public static function batch_load_product_meta($product_ids, $meta_keys = []) {
        if (empty($product_ids)) {
            return [];
        }
        
        global $wpdb;
        
        // Remove already cached products
        $uncached_ids = array_diff($product_ids, array_keys(self::$product_meta_cache));
        
        if (empty($uncached_ids)) {
            return self::filter_cached_meta($product_ids, $meta_keys);
        }
        
        $start_time = microtime(true);
        
        // Build query for uncached products
        $ids_placeholder = implode(',', array_fill(0, count($uncached_ids), '%d'));
        
        $query = "
            SELECT post_id, meta_key, meta_value 
            FROM {$wpdb->postmeta} 
            WHERE post_id IN ($ids_placeholder)
        ";
        
        $query_params = $uncached_ids;
        
        // Add meta key filter if specified
        if (!empty($meta_keys)) {
            $keys_placeholder = implode(',', array_fill(0, count($meta_keys), '%s'));
            $query .= " AND meta_key IN ($keys_placeholder)";
            $query_params = array_merge($query_params, $meta_keys);
        } else {
            // Load only WCEFP-related meta keys
            $wcefp_keys = [
                '_wcefp_capacity',
                '_wcefp_duration',
                '_wcefp_weekdays',
                '_wcefp_time_slots',
                '_wcefp_price_adult',
                '_wcefp_price_child',
                '_wcefp_difficulty',
                '_wcefp_location',
                '_wcefp_category',
                '_wcefp_min_participants',
                '_wcefp_max_participants'
            ];
            
            $keys_placeholder = implode(',', array_fill(0, count($wcefp_keys), '%s'));
            $query .= " AND meta_key IN ($keys_placeholder)";
            $query_params = array_merge($query_params, $wcefp_keys);
        }
        
        $results = $wpdb->get_results($wpdb->prepare($query, $query_params));
        
        // Group results by product ID
        foreach ($results as $row) {
            if (!isset(self::$product_meta_cache[$row->post_id])) {
                self::$product_meta_cache[$row->post_id] = [];
            }
            self::$product_meta_cache[$row->post_id][$row->meta_key] = $row->meta_value;
        }
        
        // Mark products with no meta as cached (empty array)
        foreach ($uncached_ids as $id) {
            if (!isset(self::$product_meta_cache[$id])) {
                self::$product_meta_cache[$id] = [];
            }
        }
        
        $duration = microtime(true) - $start_time;
        
        Logger::log('debug', 'Batch loaded product metadata', [
            'product_count' => count($uncached_ids),
            'meta_keys' => count($meta_keys) ?: count($wcefp_keys ?? []),
            'duration_ms' => round($duration * 1000, 2),
            'total_meta_items' => count($results)
        ]);
        
        return self::filter_cached_meta($product_ids, $meta_keys);
    }
    
    /**
     * Batch load occurrences to eliminate N+1 queries
     * 
     * @param array $product_ids Product IDs
     * @param string $from_date From date
     * @param string $to_date To date
     * @param int $limit Limit per product
     * @return array Occurrences grouped by product ID
     */
    public static function batch_load_occurrences($product_ids, $from_date, $to_date, $limit = 10) {
        if (empty($product_ids)) {
            return [];
        }
        
        global $wpdb;
        
        $cache_key = 'occurrences_' . md5(serialize([$product_ids, $from_date, $to_date, $limit]));
        
        if (isset(self::$occurrence_cache[$cache_key])) {
            return self::$occurrence_cache[$cache_key];
        }
        
        $start_time = microtime(true);
        
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        $ids_placeholder = implode(',', array_fill(0, count($product_ids), '%d'));
        
        // Use window function to limit per product
        $query = "
            SELECT * FROM (
                SELECT o.*,
                       ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY start_local ASC) as rn
                FROM {$occurrences_table} o
                WHERE o.product_id IN ($ids_placeholder)
                AND o.start_local >= %s
                AND o.start_local <= %s
                AND o.status = 'active'
            ) ranked
            WHERE rn <= %d
            ORDER BY product_id, start_local
        ";
        
        $query_params = array_merge($product_ids, [$from_date . ' 00:00:00', $to_date . ' 23:59:59', $limit]);
        
        $results = $wpdb->get_results($wpdb->prepare($query, $query_params), ARRAY_A);
        
        // Group by product ID
        $grouped_occurrences = [];
        foreach ($product_ids as $product_id) {
            $grouped_occurrences[$product_id] = [];
        }
        
        foreach ($results as $occurrence) {
            $product_id = $occurrence['product_id'];
            $grouped_occurrences[$product_id][] = $occurrence;
        }
        
        self::$occurrence_cache[$cache_key] = $grouped_occurrences;
        
        $duration = microtime(true) - $start_time;
        
        Logger::log('debug', 'Batch loaded occurrences', [
            'product_count' => count($product_ids),
            'occurrence_count' => count($results),
            'date_range' => "{$from_date} to {$to_date}",
            'duration_ms' => round($duration * 1000, 2)
        ]);
        
        return $grouped_occurrences;
    }
    
    /**
     * Get product meta from cache or database
     * 
     * @param int $product_id Product ID
     * @param string $meta_key Meta key
     * @param mixed $default Default value
     * @return mixed Meta value
     */
    public static function get_product_meta($product_id, $meta_key, $default = null) {
        // Check cache first
        if (isset(self::$product_meta_cache[$product_id][$meta_key])) {
            return self::$product_meta_cache[$product_id][$meta_key];
        }
        
        // Load from database and cache
        if (!isset(self::$product_meta_cache[$product_id])) {
            self::batch_load_product_meta([$product_id]);
        }
        
        return self::$product_meta_cache[$product_id][$meta_key] ?? $default;
    }
    
    /**
     * Batch load WooCommerce product data
     * 
     * @param array $product_ids Product IDs
     * @return array Product objects
     */
    public static function batch_load_products($product_ids) {
        if (empty($product_ids) || !function_exists('wc_get_products')) {
            return [];
        }
        
        $start_time = microtime(true);
        
        // Use WooCommerce batch loading
        $products = wc_get_products([
            'include' => $product_ids,
            'limit' => -1,
            'status' => 'publish',
            'type' => ['evento', 'esperienza', 'product'] // Include variations
        ]);
        
        $duration = microtime(true) - $start_time;
        
        Logger::log('debug', 'Batch loaded WooCommerce products', [
            'requested_count' => count($product_ids),
            'loaded_count' => count($products),
            'duration_ms' => round($duration * 1000, 2)
        ]);
        
        // Index by ID for easy access
        $indexed_products = [];
        foreach ($products as $product) {
            $indexed_products[$product->get_id()] = $product;
        }
        
        return $indexed_products;
    }
    
    /**
     * Optimized query for experience catalog
     * 
     * @param array $args Query arguments
     * @return array Query results with minimal queries
     */
    public static function get_optimized_experience_catalog($args) {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Build optimized query
        $query = self::build_catalog_query($args);
        $product_ids = $wpdb->get_col($query);
        
        if (empty($product_ids)) {
            return ['products' => [], 'total' => 0];
        }
        
        // Batch load all required data
        $products = self::batch_load_products($product_ids);
        $metadata = self::batch_load_product_meta($product_ids);
        
        // Get next available dates in batch
        $next_dates = self::batch_get_next_available_dates($product_ids);
        
        // Assemble results
        $results = [];
        foreach ($product_ids as $product_id) {
            if (!isset($products[$product_id])) {
                continue;
            }
            
            $product = $products[$product_id];
            $meta = $metadata[$product_id] ?? [];
            
            $results[] = [
                'id' => $product_id,
                'title' => $product->get_name(),
                'permalink' => $product->get_permalink(),
                'price' => $product->get_price(),
                'image_id' => $product->get_image_id(),
                'excerpt' => $product->get_short_description(),
                'meta' => $meta,
                'next_available_date' => $next_dates[$product_id] ?? null,
                'product_object' => $product // For compatibility
            ];
        }
        
        $duration = microtime(true) - $start_time;
        
        Logger::log('info', 'Optimized catalog query completed', [
            'product_count' => count($results),
            'total_duration_ms' => round($duration * 1000, 2),
            'filters' => array_keys(array_filter($args))
        ]);
        
        return [
            'products' => $results,
            'total' => count($results)
        ];
    }
    
    /**
     * Check if data preloading should occur
     * 
     * @return bool Should preload
     */
    private static function should_preload() {
        // Don't preload on admin, AJAX, or REST requests
        if (is_admin() || wp_doing_ajax() || defined('REST_REQUEST')) {
            return false;
        }
        
        // Only preload on relevant pages
        return is_page() || is_shop() || is_product_category() || is_home();
    }
    
    /**
     * Preload experience metadata for catalog pages
     */
    private static function preload_experience_metadata() {
        global $wp_query;
        
        // Get product IDs from current query
        $product_ids = [];
        
        if (isset($wp_query->posts)) {
            foreach ($wp_query->posts as $post) {
                if ($post->post_type === 'product') {
                    $product_ids[] = $post->ID;
                }
            }
        }
        
        if (!empty($product_ids)) {
            self::batch_load_product_meta($product_ids);
        }
    }
    
    /**
     * Filter cached metadata
     * 
     * @param array $product_ids Product IDs
     * @param array $meta_keys Meta keys filter
     * @return array Filtered metadata
     */
    private static function filter_cached_meta($product_ids, $meta_keys) {
        $filtered = [];
        
        foreach ($product_ids as $product_id) {
            if (!isset(self::$product_meta_cache[$product_id])) {
                continue;
            }
            
            if (empty($meta_keys)) {
                $filtered[$product_id] = self::$product_meta_cache[$product_id];
            } else {
                $filtered[$product_id] = [];
                foreach ($meta_keys as $key) {
                    if (isset(self::$product_meta_cache[$product_id][$key])) {
                        $filtered[$product_id][$key] = self::$product_meta_cache[$product_id][$key];
                    }
                }
            }
        }
        
        return $filtered;
    }
    
    /**
     * Build optimized catalog query
     * 
     * @param array $args Query arguments
     * @return string SQL query
     */
    private static function build_catalog_query($args) {
        global $wpdb;
        
        $query = "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'product_type'
            AND t.slug IN ('evento', 'esperienza')
        ";
        
        $where_conditions = [];
        $join_clauses = [];
        
        // Add filtering conditions
        if (!empty($args['category'])) {
            $join_clauses[] = "LEFT JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id";
            $join_clauses[] = "LEFT JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id";
            $where_conditions[] = $wpdb->prepare("(tt_cat.taxonomy = 'product_cat' AND tt_cat.term_id = %d)", $args['category']);
        }
        
        if (!empty($args['search'])) {
            $where_conditions[] = $wpdb->prepare("(p.post_title LIKE %s OR p.post_content LIKE %s)", 
                '%' . $wpdb->esc_like($args['search']) . '%',
                '%' . $wpdb->esc_like($args['search']) . '%'
            );
        }
        
        // Add joins and where conditions
        if (!empty($join_clauses)) {
            $query .= ' ' . implode(' ', array_unique($join_clauses));
        }
        
        if (!empty($where_conditions)) {
            $query .= ' AND (' . implode(' OR ', $where_conditions) . ')';
        }
        
        // Add ordering
        $query .= " ORDER BY p.menu_order ASC, p.post_title ASC";
        
        // Add limit
        if (!empty($args['limit'])) {
            $query .= $wpdb->prepare(" LIMIT %d", $args['limit']);
        }
        
        return $query;
    }
    
    /**
     * Batch get next available dates
     * 
     * @param array $product_ids Product IDs
     * @return array Next available dates indexed by product ID
     */
    private static function batch_get_next_available_dates($product_ids) {
        if (empty($product_ids)) {
            return [];
        }
        
        global $wpdb;
        
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        $ids_placeholder = implode(',', array_fill(0, count($product_ids), '%d'));
        
        $query = "
            SELECT product_id, MIN(start_local) as next_date
            FROM {$occurrences_table}
            WHERE product_id IN ($ids_placeholder)
            AND start_local > NOW()
            AND status = 'active'
            GROUP BY product_id
        ";
        
        $results = $wpdb->get_results($wpdb->prepare($query, $product_ids), ARRAY_A);
        
        $dates = [];
        foreach ($results as $row) {
            $dates[$row['product_id']] = $row['next_date'];
        }
        
        return $dates;
    }
    
    /**
     * Log query statistics for debugging
     */
    public static function log_query_stats() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $query_count = get_num_queries();
        $cache_stats = [
            'product_meta_cached' => count(self::$product_meta_cache),
            'occurrence_cache_keys' => count(self::$occurrence_cache),
            'query_cache_keys' => count(self::$query_cache)
        ];
        
        echo '<script>
        console.group("WCEFP Database Optimization");
        console.log("Total Queries: ' . $query_count . '");
        console.log("Product Meta Cached: ' . $cache_stats['product_meta_cached'] . ' products");
        console.log("Occurrence Cache Keys: ' . $cache_stats['occurrence_cache_keys'] . '");
        console.log("Query Cache Keys: ' . $cache_stats['query_cache_keys'] . '");
        console.groupEnd();
        </script>' . "\n";
    }
    
    /**
     * Clear all optimization caches
     */
    public static function clear_cache() {
        self::$product_meta_cache = [];
        self::$occurrence_cache = [];
        self::$query_cache = [];
        
        Logger::log('info', 'Database optimizer cache cleared');
    }
}