<?php
if (!defined('ABSPATH')) exit;

/**
 * WCEventsFP Performance Optimization
 * Advanced caching, lazy loading, and performance enhancements
 */
class WCEFP_Performance_Optimization {
    
    private static $instance = null;
    private $cache_stats = [];
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize performance optimizations
        add_action('init', [$this, 'init_performance_features']);
        add_action('wp_enqueue_scripts', [$this, 'optimize_asset_loading']);
        add_action('wp_footer', [$this, 'add_performance_monitoring']);
        
        // Database query optimization
        add_action('pre_get_posts', [$this, 'optimize_queries']);
        add_filter('wcefp_get_occurrences', [$this, 'cache_occurrences'], 10, 2);
        
        // Image optimization
        add_filter('wp_get_attachment_image_attributes', [$this, 'add_lazy_loading']);
        add_filter('the_content', [$this, 'optimize_content_images']);
        
        // Advanced caching
        add_action('wcefp_clear_cache', [$this, 'clear_all_caches']);
        add_action('wcefp_warm_cache', [$this, 'warm_critical_caches']);
        
        // AJAX optimization
        add_action('wp_ajax_wcefp_get_performance_metrics', [$this, 'get_performance_metrics']);
        add_action('wp_ajax_wcefp_optimize_database', [$this, 'optimize_database']);
        
        // Background processing
        add_action('wp', [$this, 'schedule_background_tasks']);
        add_action('wcefp_background_optimization', [$this, 'run_background_optimization']);
    }
    
    /**
     * Initialize performance features
     */
    public function init_performance_features() {
        // Object cache check
        if (!wp_using_ext_object_cache()) {
            add_action('admin_notices', [$this, 'object_cache_notice']);
        }
        
        // Setup advanced caching layers
        $this->setup_multi_tier_caching();
        
        // Initialize compression
        if (get_option('wcefp_gzip_compression', true)) {
            $this->enable_compression();
        }
        
        // Database connection pooling (if supported)
        $this->optimize_database_connections();
    }
    
    /**
     * Optimize asset loading
     */
    public function optimize_asset_loading() {
        // Critical CSS inlining
        if (get_option('wcefp_critical_css', true)) {
            add_action('wp_head', [$this, 'inline_critical_css'], 1);
        }
        
        // Asset preloading
        add_action('wp_head', [$this, 'preload_critical_assets'], 2);
        
        // Defer non-critical JavaScript
        add_filter('script_loader_tag', [$this, 'defer_non_critical_scripts'], 10, 2);
        
        // CSS minification and combination
        if (get_option('wcefp_combine_css', true)) {
            add_action('wp_print_styles', [$this, 'combine_css_files']);
        }
        
        // JavaScript optimization
        if (get_option('wcefp_optimize_js', true)) {
            add_action('wp_print_scripts', [$this, 'optimize_javascript']);
        }
    }
    
    /**
     * Setup multi-tier caching system
     */
    private function setup_multi_tier_caching() {
        // Level 1: In-memory cache (fastest)
        $this->memory_cache = [];
        
        // Level 2: Object cache (Redis/Memcached if available)
        $this->object_cache_available = wp_using_ext_object_cache();
        
        // Level 3: Database cache (transients)
        $this->transient_cache = true;
        
        // Level 4: File system cache (for large data)
        $this->file_cache_dir = wp_upload_dir()['basedir'] . '/wcefp-cache/';
        if (!file_exists($this->file_cache_dir)) {
            wp_mkdir_p($this->file_cache_dir);
        }
    }
    
    /**
     * Advanced cache get with multi-tier fallback
     */
    public function cache_get($key, $default = false) {
        $prefixed_key = 'wcefp_' . $key;
        
        // Level 1: Memory cache
        if (isset($this->memory_cache[$key])) {
            $this->cache_stats['memory_hits'] = ($this->cache_stats['memory_hits'] ?? 0) + 1;
            return $this->memory_cache[$key];
        }
        
        // Level 2: Object cache
        if ($this->object_cache_available) {
            $value = wp_cache_get($prefixed_key, 'wcefp');
            if ($value !== false) {
                $this->memory_cache[$key] = $value;
                $this->cache_stats['object_hits'] = ($this->cache_stats['object_hits'] ?? 0) + 1;
                return $value;
            }
        }
        
        // Level 3: Transient cache
        $value = get_transient($prefixed_key);
        if ($value !== false) {
            $this->memory_cache[$key] = $value;
            if ($this->object_cache_available) {
                wp_cache_set($prefixed_key, $value, 'wcefp', 3600);
            }
            $this->cache_stats['transient_hits'] = ($this->cache_stats['transient_hits'] ?? 0) + 1;
            return $value;
        }
        
        // Level 4: File cache
        $file_path = $this->file_cache_dir . md5($key) . '.cache';
        if (file_exists($file_path) && (time() - filemtime($file_path)) < 3600) {
            $value = unserialize(file_get_contents($file_path));
            if ($value !== false) {
                $this->cache_set($key, $value, 3600);
                $this->cache_stats['file_hits'] = ($this->cache_stats['file_hits'] ?? 0) + 1;
                return $value;
            }
        }
        
        $this->cache_stats['misses'] = ($this->cache_stats['misses'] ?? 0) + 1;
        return $default;
    }
    
    /**
     * Advanced cache set with multi-tier storage
     */
    public function cache_set($key, $value, $expiration = 3600) {
        $prefixed_key = 'wcefp_' . $key;
        
        // Level 1: Memory cache
        $this->memory_cache[$key] = $value;
        
        // Level 2: Object cache
        if ($this->object_cache_available) {
            wp_cache_set($prefixed_key, $value, 'wcefp', $expiration);
        }
        
        // Level 3: Transient cache
        set_transient($prefixed_key, $value, $expiration);
        
        // Level 4: File cache (for backup)
        $file_path = $this->file_cache_dir . md5($key) . '.cache';
        file_put_contents($file_path, serialize($value), LOCK_EX);
        
        return true;
    }
    
    /**
     * Cache occurrences data with intelligent invalidation
     */
    public function cache_occurrences($occurrences, $args) {
        $cache_key = 'occurrences_' . md5(serialize($args));
        $cached = $this->cache_get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Cache for 15 minutes with early expiration on updates
        $this->cache_set($cache_key, $occurrences, 900);
        
        return $occurrences;
    }
    
    /**
     * Optimize database queries
     */
    public function optimize_queries($query) {
        if (!is_admin() && $query->is_main_query()) {
            // Add pagination to prevent large result sets
            if (!$query->get('posts_per_page')) {
                $query->set('posts_per_page', 20);
            }
            
            // Optimize meta queries
            $meta_query = $query->get('meta_query');
            if ($meta_query) {
                $query->set('meta_query', $this->optimize_meta_query($meta_query));
            }
        }
    }
    
    /**
     * Optimize meta queries for better performance
     */
    private function optimize_meta_query($meta_query) {
        // Add indexes hints and optimize query structure
        foreach ($meta_query as &$clause) {
            if (is_array($clause) && isset($clause['key'])) {
                // Add specific optimizations based on meta key
                if ($clause['key'] === 'wcefp_occurrence_data') {
                    $clause['type'] = 'NUMERIC';
                }
            }
        }
        
        return $meta_query;
    }
    
    /**
     * Add lazy loading to images
     */
    public function add_lazy_loading($attr) {
        if (!isset($attr['loading'])) {
            $attr['loading'] = 'lazy';
        }
        
        // Add intersection observer fallback
        $attr['class'] = ($attr['class'] ?? '') . ' wcefp-lazy-image';
        
        return $attr;
    }
    
    /**
     * Optimize content images
     */
    public function optimize_content_images($content) {
        // Add WebP support with fallback
        $content = preg_replace_callback(
            '/<img([^>]+)src=["\']([^"\']+)["\']([^>]*)>/i',
            [$this, 'convert_to_webp_with_fallback'],
            $content
        );
        
        return $content;
    }
    
    /**
     * Convert images to WebP with fallback
     */
    private function convert_to_webp_with_fallback($matches) {
        $img_tag = $matches[0];
        $img_src = $matches[2];
        
        // Check if WebP version exists
        $webp_src = $this->get_webp_version($img_src);
        
        if ($webp_src) {
            // Create picture element with WebP and fallback
            return sprintf(
                '<picture>
                    <source srcset="%s" type="image/webp">
                    %s
                </picture>',
                esc_url($webp_src),
                $img_tag
            );
        }
        
        return $img_tag;
    }
    
    /**
     * Get WebP version of image
     */
    private function get_webp_version($img_src) {
        $upload_dir = wp_upload_dir();
        $img_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $img_src);
        $webp_path = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $img_path);
        $webp_url = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $img_src);
        
        if (file_exists($webp_path)) {
            return $webp_url;
        }
        
        // Generate WebP if source exists and WebP is supported
        if (file_exists($img_path) && function_exists('imagewebp')) {
            $this->generate_webp_image($img_path, $webp_path);
            if (file_exists($webp_path)) {
                return $webp_url;
            }
        }
        
        return false;
    }
    
    /**
     * Generate WebP image
     */
    private function generate_webp_image($source, $destination) {
        $info = getimagesize($source);
        
        if (!$info) {
            return false;
        }
        
        switch ($info['mime']) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source);
                break;
            default:
                return false;
        }
        
        if ($image) {
            $result = imagewebp($image, $destination, 80); // 80% quality
            imagedestroy($image);
            return $result;
        }
        
        return false;
    }
    
    /**
     * Inline critical CSS
     */
    public function inline_critical_css() {
        $critical_css = $this->get_critical_css();
        if ($critical_css) {
            echo '<style id="wcefp-critical-css">' . $critical_css . '</style>';
        }
    }
    
    /**
     * Get critical CSS (above-the-fold)
     */
    private function get_critical_css() {
        $cache_key = 'critical_css_' . get_the_ID();
        $cached = $this->cache_get($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Generate or extract critical CSS
        $critical_css = $this->extract_critical_css();
        
        // Cache for 24 hours
        $this->cache_set($cache_key, $critical_css, 86400);
        
        return $critical_css;
    }
    
    /**
     * Extract critical CSS (simplified version)
     */
    private function extract_critical_css() {
        // This is a simplified version - in production, use tools like Critical or Penthouse
        return "
            .wcefp-booking-form { display: block; }
            .wcefp-calendar { width: 100%; }
            .wcefp-card { margin-bottom: 1rem; }
            .wcefp-btn { padding: 0.5rem 1rem; background: #007cba; color: #fff; border: none; }
        ";
    }
    
    /**
     * Preload critical assets
     */
    public function preload_critical_assets() {
        // Preload critical CSS
        echo '<link rel="preload" href="' . WCEFP_PLUGIN_URL . 'assets/css/frontend.css" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
        
        // Preload critical JavaScript
        echo '<link rel="preload" href="' . WCEFP_PLUGIN_URL . 'assets/js/frontend.js" as="script">';
        
        // Preload web fonts
        echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" as="style" crossorigin>';
        
        // DNS prefetch for external resources
        echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">';
        echo '<link rel="dns-prefetch" href="//www.google-analytics.com">';
        echo '<link rel="dns-prefetch" href="//connect.facebook.net">';
    }
    
    /**
     * Defer non-critical scripts
     */
    public function defer_non_critical_scripts($tag, $handle) {
        $defer_scripts = [
            'wcefp-analytics',
            'wcefp-tracking',
            'wcefp-social-share'
        ];
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add performance monitoring
     */
    public function add_performance_monitoring() {
        if (!get_option('wcefp_performance_monitoring', true)) {
            return;
        }
        
        ?>
        <script>
        (function() {
            // Core Web Vitals monitoring
            function measureWebVitals() {
                if ('PerformanceObserver' in window) {
                    // Largest Contentful Paint
                    new PerformanceObserver((entryList) => {
                        const entries = entryList.getEntries();
                        const lastEntry = entries[entries.length - 1];
                        console.log('LCP:', lastEntry.startTime);
                        
                        // Send to analytics
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'web_vitals', {
                                metric_name: 'LCP',
                                metric_value: Math.round(lastEntry.startTime),
                                metric_id: 'wcefp'
                            });
                        }
                    }).observe({entryTypes: ['largest-contentful-paint']});
                    
                    // First Input Delay
                    new PerformanceObserver((entryList) => {
                        const entries = entryList.getEntries();
                        entries.forEach(entry => {
                            console.log('FID:', entry.processingStart - entry.startTime);
                            
                            if (typeof gtag !== 'undefined') {
                                gtag('event', 'web_vitals', {
                                    metric_name: 'FID',
                                    metric_value: Math.round(entry.processingStart - entry.startTime),
                                    metric_id: 'wcefp'
                                });
                            }
                        });
                    }).observe({entryTypes: ['first-input']});
                    
                    // Cumulative Layout Shift
                    let clsValue = 0;
                    new PerformanceObserver((entryList) => {
                        for (const entry of entryList.getEntries()) {
                            if (!entry.hadRecentInput) {
                                clsValue += entry.value;
                            }
                        }
                        console.log('CLS:', clsValue);
                        
                        if (typeof gtag !== 'undefined') {
                            gtag('event', 'web_vitals', {
                                metric_name: 'CLS',
                                metric_value: Math.round(clsValue * 1000),
                                metric_id: 'wcefp'
                            });
                        }
                    }).observe({entryTypes: ['layout-shift']});
                }
            }
            
            // Run measurements when page is loaded
            if (document.readyState === 'complete') {
                measureWebVitals();
            } else {
                window.addEventListener('load', measureWebVitals);
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Get performance metrics
     */
    public function get_performance_metrics() {
        check_ajax_referer('wcefp_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['msg' => __('No permission', 'wceventsfp')]);
        }
        
        $metrics = [
            'cache_stats' => $this->cache_stats,
            'database_queries' => get_num_queries(),
            'memory_usage' => [
                'current' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
                'peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
                'limit' => ini_get('memory_limit')
            ],
            'cache_status' => [
                'object_cache' => $this->object_cache_available,
                'file_cache' => is_writable($this->file_cache_dir),
                'opcache' => function_exists('opcache_get_status') ? opcache_get_status() : false
            ],
            'page_speed' => $this->get_page_speed_metrics(),
            'optimization_suggestions' => $this->get_optimization_suggestions()
        ];
        
        wp_send_json_success($metrics);
    }
    
    /**
     * Get page speed metrics
     */
    private function get_page_speed_metrics() {
        return [
            'server_response_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
            'php_execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
            'total_queries' => get_num_queries(),
            'slow_queries' => $this->get_slow_query_count()
        ];
    }
    
    /**
     * Get optimization suggestions
     */
    private function get_optimization_suggestions() {
        $suggestions = [];
        
        if (!$this->object_cache_available) {
            $suggestions[] = 'Install Redis or Memcached for object caching';
        }
        
        if (get_num_queries() > 50) {
            $suggestions[] = 'Reduce database queries (currently: ' . get_num_queries() . ')';
        }
        
        if (memory_get_peak_usage(true) > 134217728) { // 128MB
            $suggestions[] = 'Optimize memory usage (peak: ' . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB)';
        }
        
        if (!function_exists('opcache_get_status') || !opcache_get_status()['opcache_enabled']) {
            $suggestions[] = 'Enable PHP OPcache for better performance';
        }
        
        return $suggestions;
    }
    
    /**
     * Initialize performance optimization
     */
    public static function init() {
        return self::get_instance();
    }
}

// Initialize performance optimization
WCEFP_Performance_Optimization::init();