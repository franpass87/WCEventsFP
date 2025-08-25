<?php
/**
 * Performance Manager
 * 
 * Handles performance optimizations including caching, query optimization,
 * and asset management.
 * 
 * @package WCEFP\Core
 * @since 2.1.4
 */

namespace WCEFP\Core;

use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Performance Manager class
 */
class PerformanceManager {
    
    /**
     * Cache group for WCEFP
     * 
     * @var string
     */
    const CACHE_GROUP = 'wcefp';
    
    /**
     * Default cache expiration (1 hour)
     * 
     * @var int
     */
    const DEFAULT_CACHE_EXPIRATION = 3600;
    
    /**
     * Initialize performance manager
     * 
     * @return void
     */
    public function init(): void {
        // Optimize database queries
        add_action('init', [$this, 'optimize_database'], 5);
        
        // Optimize asset loading
        add_action('wp_enqueue_scripts', [$this, 'optimize_frontend_assets'], 999);
        add_action('admin_enqueue_scripts', [$this, 'optimize_admin_assets'], 999);
        
        // Add caching for expensive operations
        add_action('wp_loaded', [$this, 'setup_caching']);
        
        // Remove unnecessary autoload options
        add_action('wp_loaded', [$this, 'optimize_autoload_options']);
        
        // Database query optimization
        add_filter('posts_clauses', [$this, 'optimize_event_queries'], 10, 2);
        
        Logger::info('Performance Manager initialized');
    }
    
    /**
     * Optimize database operations
     * 
     * @return void
     */
    public function optimize_database(): void {
        global $wpdb;
        
        // Check and create necessary indexes
        $this->ensure_database_indexes();
        
        // Clean up expired transients
        $this->cleanup_expired_transients();
        
        // Optimize WCEFP tables if they exist
        $this->optimize_wcefp_tables();
    }
    
    /**
     * Ensure database indexes exist for performance
     * 
     * @return void
     */
    private function ensure_database_indexes(): void {
        global $wpdb;
        
        $indexes_to_check = [
            // Main occurrences table
            'wcefp_occorrenze' => [
                'idx_product_date' => ['product_id', 'data_evento'],
                'idx_stato' => ['stato'],
                'idx_email' => ['email'],
                'idx_created' => ['created_at']
            ],
            
            // Closures table
            'wcefp_closures' => [
                'idx_product_dates' => ['product_id', 'start_date', 'end_date'],
                'idx_active' => ['active']
            ],
            
            // WordPress posts for events
            'posts' => [
                'idx_wcefp_events' => ['post_type', 'post_status']
            ]
        ];
        
        foreach ($indexes_to_check as $table => $indexes) {
            $full_table_name = $wpdb->prefix . $table;
            
            // Check if table exists
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)) !== $full_table_name) {
                continue;
            }
            
            foreach ($indexes as $index_name => $columns) {
                $this->create_index_if_not_exists($full_table_name, $index_name, $columns);
            }
        }
    }
    
    /**
     * Create database index if it doesn't exist
     * 
     * @param string $table
     * @param string $index_name
     * @param array $columns
     * @return void
     */
    private function create_index_if_not_exists(string $table, string $index_name, array $columns): void {
        global $wpdb;
        
        // Check if index exists
        $existing_indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`");
        $index_exists = false;
        
        foreach ($existing_indexes as $index) {
            if ($index->Key_name === $index_name) {
                $index_exists = true;
                break;
            }
        }
        
        if (!$index_exists) {
            $columns_str = '`' . implode('`, `', $columns) . '`';
            $sql = "CREATE INDEX `{$index_name}` ON `{$table}` ({$columns_str})";
            
            $result = $wpdb->query($sql);
            
            if ($result === false) {
                Logger::warning('Failed to create database index', [
                    'table' => $table,
                    'index' => $index_name,
                    'columns' => $columns,
                    'error' => $wpdb->last_error
                ]);
            } else {
                Logger::info('Database index created', [
                    'table' => $table,
                    'index' => $index_name,
                    'columns' => $columns
                ]);
            }
        }
    }
    
    /**
     * Clean up expired transients
     * 
     * @return void
     */
    private function cleanup_expired_transients(): void {
        global $wpdb;
        
        // Only run cleanup once per day
        $last_cleanup = get_option('wcefp_last_transient_cleanup', 0);
        if (time() - $last_cleanup < DAY_IN_SECONDS) {
            return;
        }
        
        // Delete expired transients
        $deleted = $wpdb->query("
            DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
            WHERE a.option_name LIKE '_transient_timeout_%'
            AND a.option_name = CONCAT('_transient_timeout_', SUBSTRING(b.option_name, 12))
            AND a.option_value < UNIX_TIMESTAMP()
            AND b.option_name LIKE '_transient_%'
        ");
        
        if ($deleted !== false) {
            Logger::info('Expired transients cleaned up', ['deleted' => $deleted]);
            update_option('wcefp_last_transient_cleanup', time(), false);
        }
    }
    
    /**
     * Optimize WCEFP tables
     * 
     * @return void
     */
    private function optimize_wcefp_tables(): void {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'wcefp_occorrenze',
            $wpdb->prefix . 'wcefp_closures',
            $wpdb->prefix . 'wcefp_product_availability',
            $wpdb->prefix . 'wcefp_product_occurrences'
        ];
        
        foreach ($tables as $table) {
            // Check if table exists and has data
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
            if ($table_exists === $table) {
                $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
                
                // Only optimize tables with significant data
                if ($row_count > 1000) {
                    $wpdb->query("OPTIMIZE TABLE `{$table}`");
                    Logger::info('Table optimized', ['table' => $table, 'rows' => $row_count]);
                }
            }
        }
    }
    
    /**
     * Optimize frontend asset loading
     * 
     * @return void
     */
    public function optimize_frontend_assets(): void {
        // Remove unused WCEFP scripts if not needed on current page
        if (!$this->page_needs_wcefp_assets()) {
            $this->dequeue_unnecessary_scripts();
        }
        
        // Combine and minify CSS/JS if possible
        $this->optimize_wcefp_assets();
    }
    
    /**
     * Optimize admin asset loading
     * 
     * @param string $hook
     * @return void
     */
    public function optimize_admin_assets(string $hook): void {
        // Only load admin assets on WCEFP pages
        if (strpos($hook, 'wcefp') === false && 
            !in_array($hook, ['post.php', 'post-new.php', 'edit.php'])) {
            $this->dequeue_unnecessary_admin_scripts();
        }
    }
    
    /**
     * Setup caching for expensive operations
     * 
     * @return void
     */
    public function setup_caching(): void {
        // Cache event availability queries
        add_filter('wcefp_get_event_availability', [$this, 'cache_event_availability'], 10, 3);
        
        // Cache booking statistics
        add_filter('wcefp_get_booking_statistics', [$this, 'cache_booking_statistics'], 10, 2);
        
        // Cache event categories
        add_filter('wcefp_get_event_categories', [$this, 'cache_event_categories'], 10, 1);
    }
    
    /**
     * Optimize autoload options
     * 
     * @return void
     */
    public function optimize_autoload_options(): void {
        global $wpdb;
        
        // Get WCEFP options that shouldn't be autoloaded
        $non_autoload_options = [
            'wcefp_booking_statistics_cache',
            'wcefp_event_availability_cache',
            'wcefp_export_logs',
            'wcefp_performance_logs',
            'wcefp_diagnostic_logs'
        ];
        
        foreach ($non_autoload_options as $option) {
            // Update autoload setting without changing the value
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET autoload = 'no' WHERE option_name = %s AND autoload = 'yes'",
                $option
            ));
        }
    }
    
    /**
     * Optimize event queries
     * 
     * @param array $clauses
     * @param \WP_Query $query
     * @return array
     */
    public function optimize_event_queries(array $clauses, $query): array {
        // Only optimize WCEFP event queries
        if (!isset($query->query_vars['meta_query'])) {
            return $clauses;
        }
        
        $is_event_query = false;
        foreach ($query->query_vars['meta_query'] as $meta_query) {
            if (isset($meta_query['key']) && $meta_query['key'] === '_wcefp_is_event') {
                $is_event_query = true;
                break;
            }
        }
        
        if (!$is_event_query) {
            return $clauses;
        }
        
        // Add specific optimizations for event queries
        global $wpdb;
        
        // Force use of proper indexes
        if (strpos($clauses['join'], 'FORCE INDEX') === false) {
            $clauses['join'] = str_replace(
                "INNER JOIN {$wpdb->postmeta}",
                "INNER JOIN {$wpdb->postmeta} FORCE INDEX (meta_key)",
                $clauses['join']
            );
        }
        
        return $clauses;
    }
    
    /**
     * Check if current page needs WCEFP assets
     * 
     * @return bool
     */
    private function page_needs_wcefp_assets(): bool {
        global $post;
        
        // Check for new v2 shortcodes
        if ($post && has_shortcode($post->post_content, 'wcefp_booking')) {
            return true;
        }
        
        // Check for WCEFP shortcodes
        if ($post && has_shortcode($post->post_content, 'wcefp_events')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'wcefp_booking_form')) {
            return true;
        }
        
        // Check for WCEFP blocks (including new v2 blocks)
        if ($post && has_block('wcefp/booking-form', $post)) {
            return true;
        }
        
        if ($post && has_block('wcefp/booking-widget-v2', $post)) {
            return true;
        }
        
        if ($post && has_block('wcefp/event-list', $post)) {
            return true;
        }
        
        // Check if it's a WooCommerce product page with events
        if (is_product()) {
            $product_id = get_queried_object_id();
            $product = wc_get_product($product_id);
            if ($product && in_array($product->get_type(), ['evento', 'esperienza'])) {
                return true;
            }
        }
        
        // Check for cart/checkout pages with event products
        if (is_cart() || is_checkout()) {
            if ($this->cart_has_event_products()) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if cart contains event products
     * 
     * @return bool
     */
    private function cart_has_event_products(): bool {
        if (!function_exists('WC') || !WC()->cart) {
            return false;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product = $cart_item['data'];
            if ($product && in_array($product->get_type(), ['evento', 'esperienza'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Remove unnecessary scripts
     * 
     * @return void
     */
    private function dequeue_unnecessary_scripts(): void {
        $scripts_to_remove = [
            'wcefp-admin',
            'wcefp-analytics',
            'wcefp-export'
        ];
        
        foreach ($scripts_to_remove as $script) {
            wp_dequeue_script($script);
            wp_dequeue_style($script);
        }
    }
    
    /**
     * Remove unnecessary admin scripts
     * 
     * @return void
     */
    private function dequeue_unnecessary_admin_scripts(): void {
        $scripts_to_remove = [
            'wcefp-frontend',
            'wcefp-booking',
            'wcefp-calendar-integration'
        ];
        
        foreach ($scripts_to_remove as $script) {
            wp_dequeue_script($script);
            wp_dequeue_style($script);
        }
    }
    
    /**
     * Optimize WCEFP assets
     * 
     * @return void
     */
    private function optimize_wcefp_assets(): void {
        // Check if we have combined assets available
        $combined_css = WCEFP_PLUGIN_DIR . 'assets/dist/frontend-combined.min.css';
        $combined_js = WCEFP_PLUGIN_DIR . 'assets/dist/frontend-combined.min.js';
        
        if (file_exists($combined_css) && file_exists($combined_js)) {
            // Dequeue individual assets
            wp_dequeue_style('wcefp-frontend');
            wp_dequeue_style('wcefp-widgets');
            wp_dequeue_script('wcefp-frontend');
            wp_dequeue_script('wcefp-widgets');
            
            // Enqueue combined assets
            wp_enqueue_style('wcefp-combined', WCEFP_PLUGIN_URL . 'assets/dist/frontend-combined.min.css', [], WCEFP_VERSION);
            wp_enqueue_script('wcefp-combined', WCEFP_PLUGIN_URL . 'assets/dist/frontend-combined.min.js', ['jquery'], WCEFP_VERSION, true);
        }
    }
    
    /**
     * Cache event availability
     * 
     * @param mixed $availability
     * @param int $event_id
     * @param string $date
     * @return mixed
     */
    public function cache_event_availability($availability, int $event_id, string $date) {
        $cache_key = "event_availability_{$event_id}_{$date}";
        
        if ($availability === null) {
            // Try to get from cache
            $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
            
            // Calculate availability (this would be the expensive operation)
            // Return null to let the original function handle it, then cache the result
            return null;
        }
        
        // Cache the result
        wp_cache_set($cache_key, $availability, self::CACHE_GROUP, self::DEFAULT_CACHE_EXPIRATION);
        
        return $availability;
    }
    
    /**
     * Cache booking statistics
     * 
     * @param mixed $statistics
     * @param array $filters
     * @return mixed
     */
    public function cache_booking_statistics($statistics, array $filters) {
        $cache_key = 'booking_statistics_' . md5(serialize($filters));
        
        if ($statistics === null) {
            $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
            return null;
        }
        
        wp_cache_set($cache_key, $statistics, self::CACHE_GROUP, self::DEFAULT_CACHE_EXPIRATION);
        return $statistics;
    }
    
    /**
     * Cache event categories
     * 
     * @param mixed $categories
     * @return mixed
     */
    public function cache_event_categories($categories) {
        $cache_key = 'event_categories';
        
        if ($categories === null) {
            $cached = wp_cache_get($cache_key, self::CACHE_GROUP);
            if ($cached !== false) {
                return $cached;
            }
            return null;
        }
        
        wp_cache_set($cache_key, $categories, self::CACHE_GROUP, self::DEFAULT_CACHE_EXPIRATION * 6); // Cache longer
        return $categories;
    }
    
    /**
     * Get performance metrics
     * 
     * @return array
     */
    public static function get_performance_metrics(): array {
        global $wpdb;
        
        $start_time = defined('WCEFP_START_TIME') ? WCEFP_START_TIME : microtime(true);
        $execution_time = microtime(true) - $start_time;
        
        return [
            'execution_time' => round($execution_time * 1000, 2), // in milliseconds
            'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2), // in MB
            'memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2), // in MB
            'db_queries' => $wpdb->num_queries,
            'cache_hits' => wp_cache_get_last_changed(self::CACHE_GROUP) ? 'enabled' : 'disabled',
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version')
        ];
    }
    
    /**
     * Log performance metrics
     * 
     * @param string $context
     * @return void
     */
    public static function log_performance_metrics(string $context = ''): void {
        $metrics = self::get_performance_metrics();
        
        Logger::info('Performance Metrics' . ($context ? " - {$context}" : ''), $metrics);
        
        // Alert if performance thresholds are exceeded
        if ($metrics['execution_time'] > 5000) { // 5 seconds
            Logger::warning('Slow execution time detected', $metrics);
        }
        
        if ($metrics['memory_usage'] > 128) { // 128 MB
            Logger::warning('High memory usage detected', $metrics);
        }
        
        if ($metrics['db_queries'] > 100) {
            Logger::warning('High number of database queries detected', $metrics);
        }
    }
}