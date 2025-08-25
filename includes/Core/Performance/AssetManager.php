<?php
/**
 * Performance & Assets Manager
 * 
 * Optimizes asset loading and implements performance enhancements
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
 * Performance & Assets Manager Class
 */
class AssetManager {
    
    /**
     * Registered shortcodes that require assets
     */
    private static $shortcodes_with_assets = [
        'wcefp_experiences',
        'wcefp_experience',
        'wcefp_experience_card',
        'wcefp_booking_widget'
    ];
    
    /**
     * Blocks that require assets
     */
    private static $blocks_with_assets = [
        'wcefp/experiences-catalog',
        'wcefp/experience-single',
        'wcefp/booking-widget'
    ];
    
    /**
     * Assets loading flags
     */
    private static $assets_enqueued = false;
    private static $page_has_shortcodes = null;
    private static $page_has_blocks = null;
    
    /**
     * Initialize asset management
     */
    public static function init() {
        // Hook into WordPress asset system
        add_action('wp_enqueue_scripts', [__CLASS__, 'conditional_asset_enqueue'], 20);
        add_action('wp_head', [__CLASS__, 'preload_critical_assets'], 5);
        add_action('wp_footer', [__CLASS__, 'lazy_load_assets'], 5);
        
        // Content scanning hooks
        add_filter('the_content', [__CLASS__, 'scan_content_for_shortcodes'], 8);
        add_action('wp', [__CLASS__, 'scan_page_for_blocks'], 10);
        
        // Performance monitoring
        add_action('wp_footer', [__CLASS__, 'add_performance_metrics']);
        
        // Asset optimization filters
        add_filter('script_loader_tag', [__CLASS__, 'add_defer_async_attributes'], 10, 2);
        add_filter('style_loader_tag', [__CLASS__, 'add_preload_attributes'], 10, 2);
    }
    
    /**
     * Conditionally enqueue assets based on content
     */
    public static function conditional_asset_enqueue() {
        if (!self::should_load_assets()) {
            return;
        }
        
        self::enqueue_frontend_assets();
        self::$assets_enqueued = true;
        
        Logger::log('debug', 'WCEFP assets conditionally loaded', [
            'has_shortcodes' => self::page_has_shortcodes(),
            'has_blocks' => self::page_has_blocks(),
            'post_id' => get_the_ID()
        ]);
    }
    
    /**
     * Check if assets should be loaded on current page
     * 
     * @return bool Should load assets
     */
    private static function should_load_assets() {
        // Always load on admin pages (for block editor)
        if (is_admin()) {
            return true;
        }
        
        // Check if current page has shortcodes or blocks
        return self::page_has_shortcodes() || self::page_has_blocks();
    }
    
    /**
     * Check if current page has WCEFP shortcodes
     * 
     * @return bool Has shortcodes
     */
    private static function page_has_shortcodes() {
        if (self::$page_has_shortcodes !== null) {
            return self::$page_has_shortcodes;
        }
        
        global $post;
        
        if (!$post || !$post->post_content) {
            self::$page_has_shortcodes = false;
            return false;
        }
        
        // Check for any of our registered shortcodes
        foreach (self::$shortcodes_with_assets as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                self::$page_has_shortcodes = true;
                return true;
            }
        }
        
        // Check widgets and customizer content
        if (is_active_sidebar('sidebar-1') || is_customize_preview()) {
            $widget_content = self::get_widget_content();
            foreach (self::$shortcodes_with_assets as $shortcode) {
                if (strpos($widget_content, "[{$shortcode}") !== false) {
                    self::$page_has_shortcodes = true;
                    return true;
                }
            }
        }
        
        self::$page_has_shortcodes = false;
        return false;
    }
    
    /**
     * Check if current page has WCEFP blocks
     * 
     * @return bool Has blocks
     */
    private static function page_has_blocks() {
        if (self::$page_has_blocks !== null) {
            return self::$page_has_blocks;
        }
        
        global $post;
        
        if (!$post || !$post->post_content || !function_exists('parse_blocks')) {
            self::$page_has_blocks = false;
            return false;
        }
        
        $blocks = parse_blocks($post->post_content);
        self::$page_has_blocks = self::has_wcefp_blocks($blocks);
        
        return self::$page_has_blocks;
    }
    
    /**
     * Recursively check for WCEFP blocks
     * 
     * @param array $blocks Parsed blocks
     * @return bool Has WCEFP blocks
     */
    private static function has_wcefp_blocks($blocks) {
        foreach ($blocks as $block) {
            if (in_array($block['blockName'], self::$blocks_with_assets)) {
                return true;
            }
            
            // Check inner blocks
            if (!empty($block['innerBlocks']) && self::has_wcefp_blocks($block['innerBlocks'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enqueue frontend assets with optimization
     */
    private static function enqueue_frontend_assets() {
        $version = defined('WCEFP_VERSION') ? WCEFP_VERSION : '2.2.0';
        $min_suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        $plugin_url = defined('WCEFP_PLUGIN_URL') ? WCEFP_PLUGIN_URL : plugin_dir_url(dirname(dirname(__DIR__)));
        
        // CSS Assets
        wp_enqueue_style(
            'wcefp-frontend',
            $plugin_url . "assets/css/frontend{$min_suffix}.css",
            [],
            $version,
            'all'
        );
        
        // Critical inline CSS for above-the-fold content
        $critical_css = self::get_critical_css();
        if ($critical_css) {
            wp_add_inline_style('wcefp-frontend', $critical_css);
        }
        
        // JavaScript Assets with dependencies
        wp_enqueue_script(
            'wcefp-frontend',
            $plugin_url . "assets/js/frontend{$min_suffix}.js",
            ['jquery', 'wp-api-fetch'],
            $version,
            true // Load in footer
        );
        
        // Localize script with optimized data
        $localized_data = self::get_localized_script_data();
        wp_localize_script('wcefp-frontend', 'wcefp_frontend', $localized_data);
        
        // Conditional components
        if (self::needs_booking_assets()) {
            wp_enqueue_script(
                'wcefp-booking',
                $plugin_url . "assets/js/booking{$min_suffix}.js",
                ['wcefp-frontend'],
                $version,
                true
            );
        }
        
        if (self::needs_calendar_assets()) {
            wp_enqueue_style(
                'wcefp-calendar',
                $plugin_url . "assets/css/calendar{$min_suffix}.css",
                ['wcefp-frontend'],
                $version
            );
        }
    }
    
    /**
     * Preload critical assets
     */
    public static function preload_critical_assets() {
        if (!self::should_load_assets()) {
            return;
        }
        
        $version = defined('WCEFP_VERSION') ? WCEFP_VERSION : '2.2.0';
        $min_suffix = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';
        $plugin_url = defined('WCEFP_PLUGIN_URL') ? WCEFP_PLUGIN_URL : plugin_dir_url(dirname(dirname(__DIR__)));
        
        // Preload critical CSS
        echo '<link rel="preload" href="' . $plugin_url . "assets/css/frontend{$min_suffix}.css?ver={$version}" . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'" />' . "\n";
        
        // Preload critical fonts
        $font_urls = self::get_critical_fonts();
        foreach ($font_urls as $font_url) {
            echo '<link rel="preload" href="' . esc_url($font_url) . '" as="font" type="font/woff2" crossorigin />' . "\n";
        }
    }
    
    /**
     * Lazy load non-critical assets
     */
    public static function lazy_load_assets() {
        if (!self::$assets_enqueued) {
            return;
        }
        
        // Lazy load non-critical images
        echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            if ("IntersectionObserver" in window) {
                var lazyImages = document.querySelectorAll("img[data-src]");
                var imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove("lazy");
                            img.classList.add("lazy-loaded");
                            imageObserver.unobserve(img);
                        }
                    });
                });
                lazyImages.forEach(function(img) {
                    imageObserver.observe(img);
                });
            } else {
                // Fallback for browsers without IntersectionObserver
                var lazyImages = document.querySelectorAll("img[data-src]");
                lazyImages.forEach(function(img) {
                    img.src = img.dataset.src;
                    img.classList.remove("lazy");
                    img.classList.add("lazy-loaded");
                });
            }
        });
        </script>' . "\n";
    }
    
    /**
     * Add defer/async attributes to scripts
     * 
     * @param string $tag Script tag
     * @param string $handle Script handle
     * @return string Modified script tag
     */
    public static function add_defer_async_attributes($tag, $handle) {
        $defer_scripts = [
            'wcefp-frontend',
            'wcefp-booking'
        ];
        
        $async_scripts = [
            'wcefp-analytics'
        ];
        
        if (in_array($handle, $defer_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }
        
        if (in_array($handle, $async_scripts)) {
            return str_replace(' src', ' async src', $tag);
        }
        
        return $tag;
    }
    
    /**
     * Add preload attributes to stylesheets
     * 
     * @param string $tag Style tag
     * @param string $handle Style handle
     * @return string Modified style tag
     */
    public static function add_preload_attributes($tag, $handle) {
        $preload_styles = [
            'wcefp-frontend'
        ];
        
        if (in_array($handle, $preload_styles)) {
            $tag = str_replace('rel=\'stylesheet\'', 'rel=\'preload\' as=\'style\' onload=\'this.onload=null;this.rel="stylesheet"\'', $tag);
            $tag .= '<noscript><link rel="stylesheet" href="' . wp_style_is($handle, 'queue') . '"></noscript>';
        }
        
        return $tag;
    }
    
    /**
     * Scan content for shortcodes
     * 
     * @param string $content Post content
     * @return string Content (unchanged)
     */
    public static function scan_content_for_shortcodes($content) {
        // This runs early to detect shortcodes before asset enqueueing
        foreach (self::$shortcodes_with_assets as $shortcode) {
            if (has_shortcode($content, $shortcode)) {
                self::$page_has_shortcodes = true;
                break;
            }
        }
        
        return $content;
    }
    
    /**
     * Scan page for blocks
     */
    public static function scan_page_for_blocks() {
        // Force check for blocks
        self::page_has_blocks();
    }
    
    /**
     * Add performance metrics to footer
     */
    public static function add_performance_metrics() {
        if (!self::$assets_enqueued || !current_user_can('manage_options')) {
            return;
        }
        
        echo '<script>
        if (window.performance && window.performance.timing) {
            var perfData = window.performance.timing;
            var pageLoadTime = perfData.loadEventEnd - perfData.navigationStart;
            var domReadyTime = perfData.domContentLoadedEventEnd - perfData.navigationStart;
            
            console.group("WCEFP Performance Metrics");
            console.log("Page Load Time: " + pageLoadTime + "ms");
            console.log("DOM Ready Time: " + domReadyTime + "ms");
            console.log("Assets Loaded: true");
            console.groupEnd();
        }
        </script>' . "\n";
    }
    
    /**
     * Get critical CSS for above-the-fold content
     * 
     * @return string Critical CSS
     */
    private static function get_critical_css() {
        return '
        .wcefp-loading { opacity: 0; transition: opacity 0.3s ease; }
        .wcefp-loaded { opacity: 1; }
        .wcefp-skeleton { background: linear-gradient(90deg, #f0f0f0 25%, transparent 37%, transparent 63%, #f0f0f0 75%); animation: wcefp-skeleton 1.5s infinite linear; }
        @keyframes wcefp-skeleton { 0% { background-position: -200px 0; } 100% { background-position: 200px 0; } }
        ';
    }
    
    /**
     * Get localized script data optimized for performance
     * 
     * @return array Localized data
     */
    private static function get_localized_script_data() {
        return [
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => rest_url('wcefp/v2/'),
            'nonce' => wp_create_nonce('wcefp_nonce'),
            'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR',
            'currency_symbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '€',
            'date_format' => get_option('date_format'),
            'time_format' => get_option('time_format'),
            'i18n' => [
                'loading' => __('Loading...', 'wceventsfp'),
                'error' => __('Error loading content', 'wceventsfp'),
                'no_results' => __('No experiences found', 'wceventsfp'),
                'book_now' => __('Book Now', 'wceventsfp'),
                'from_price' => __('From', 'wceventsfp')
            ],
            'features' => [
                'lazy_loading' => true,
                'ajax_filtering' => true,
                'calendar_widget' => self::needs_calendar_assets(),
                'booking_widget' => self::needs_booking_assets()
            ]
        ];
    }
    
    /**
     * Check if booking assets are needed
     * 
     * @return bool Needs booking assets
     */
    private static function needs_booking_assets() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check for booking-related shortcodes
        $booking_shortcodes = ['wcefp_booking_widget', 'wcefp_experience'];
        foreach ($booking_shortcodes as $shortcode) {
            if (has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        // Check for single experience pages
        if (is_singular('product')) {
            $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;
            if ($product && in_array($product->get_type(), ['evento', 'esperienza'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if calendar assets are needed
     * 
     * @return bool Needs calendar assets
     */
    private static function needs_calendar_assets() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check for calendar-related shortcodes
        return has_shortcode($post->post_content, 'wcefp_experiences') || 
               has_shortcode($post->post_content, 'wcefp_experience') ||
               self::page_has_blocks();
    }
    
    /**
     * Get critical font URLs
     * 
     * @return array Font URLs
     */
    private static function get_critical_fonts() {
        return [
            // Add critical font URLs here
            // 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap'
        ];
    }
    
    /**
     * Get widget content for shortcode scanning
     * 
     * @return string Widget content
     */
    private static function get_widget_content() {
        ob_start();
        
        if (function_exists('dynamic_sidebar') && is_active_sidebar('sidebar-1')) {
            dynamic_sidebar('sidebar-1');
        }
        
        return ob_get_clean();
    }
    
    /**
     * Get asset loading statistics
     * 
     * @return array Loading statistics
     */
    public static function get_loading_stats() {
        return [
            'assets_enqueued' => self::$assets_enqueued,
            'page_has_shortcodes' => self::$page_has_shortcodes,
            'page_has_blocks' => self::$page_has_blocks,
            'should_load_assets' => self::should_load_assets(),
            'post_id' => get_the_ID()
        ];
    }
}