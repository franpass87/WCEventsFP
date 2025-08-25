<?php
/**
 * Experience Gating Feature
 *
 * Manages visibility of experiences - hides them from WooCommerce loops (shop, archives, search)
 * while making them available only via shortcodes and blocks.
 *
 * @package WCEFP\Features\Visibility
 * @since 2.2.0
 */

namespace WCEFP\Features\Visibility;

use WCEFP\Utils\DiagnosticLogger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Experience Gating class
 * 
 * Controls the visibility of experience products in various contexts
 */
class ExperienceGating {
    
    /**
     * Initialize the experience gating feature
     */
    public static function init() {
        add_action('pre_get_posts', [__CLASS__, 'exclude_from_loops']);
        add_filter('woocommerce_rest_product_object_query', [__CLASS__, 'exclude_from_rest'], 10, 2);
        add_filter('wp_sitemaps_posts_query_args', [__CLASS__, 'exclude_from_sitemaps'], 10, 2);
        add_filter('woocommerce_product_query_tax_query', [__CLASS__, 'exclude_from_product_query'], 10, 2);
        add_filter('woocommerce_shortcode_products_query', [__CLASS__, 'exclude_from_shortcodes'], 10, 3);
        add_filter('wc_get_products_query', [__CLASS__, 'exclude_from_wc_get_products'], 10, 2);
        
        // Log initialization
        DiagnosticLogger::instance()->debug('Experience Gating feature initialized', [], 'visibility');
    }
    
    /**
     * Check if experience gating is enabled
     * 
     * @return bool True if experiences should be hidden from WooCommerce loops
     */
    public static function hide_flag(): bool {
        $options = get_option('wcefp_options', []);
        $hide_from_woo = isset($options['hide_experiences_from_woo']) ? 
            (bool)$options['hide_experiences_from_woo'] : true; // Default: ON
        
        return apply_filters('wcefp_hide_experiences_from_woo', $hide_from_woo);
    }
    
    /**
     * Exclude experiences from main WordPress queries (shop, archives, search)
     * 
     * @param \WP_Query $query The query instance
     */
    public static function exclude_from_loops(\WP_Query $query) {
        // Skip if gating is disabled
        if (!self::hide_flag()) {
            return;
        }
        
        // Only affect frontend main queries
        if (is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Target specific query types
        if ($query->is_post_type_archive('product') ||
            $query->is_tax(['product_cat', 'product_tag']) ||
            $query->is_search() ||
            $query->is_shop()) {
            
            self::add_experience_exclusion_to_query($query);
            
            DiagnosticLogger::instance()->debug('Experiences excluded from main query', [
                'query_type' => self::get_query_type($query),
                'is_main_query' => $query->is_main_query()
            ], 'visibility');
        }
    }
    
    /**
     * Exclude experiences from REST API product queries
     * 
     * @param array $args Query arguments
     * @param \WP_REST_Request $request REST request object
     * @return array Modified query arguments
     */
    public static function exclude_from_rest($args, $request = null) {
        if (!self::hide_flag()) {
            return $args;
        }
        
        // Add tax query to exclude experiences
        if (!isset($args['tax_query'])) {
            $args['tax_query'] = [];
        }
        
        $args['tax_query'][] = [
            'taxonomy' => 'product_type',
            'field' => 'slug',
            'terms' => ['experience', 'esperienza'],
            'operator' => 'NOT IN'
        ];
        
        DiagnosticLogger::instance()->debug('Experiences excluded from REST API', [
            'endpoint' => $request ? $request->get_route() : 'unknown'
        ], 'visibility');
        
        return $args;
    }
    
    /**
     * Exclude experiences from WordPress XML sitemaps
     * 
     * @param array $args Query arguments for sitemap
     * @param string $post_type Post type being queried
     * @return array Modified query arguments
     */
    public static function exclude_from_sitemaps($args, $post_type) {
        if ($post_type !== 'product' || !self::hide_flag()) {
            return $args;
        }
        
        // Add tax query to exclude experiences
        if (!isset($args['tax_query'])) {
            $args['tax_query'] = [];
        }
        
        $args['tax_query'][] = [
            'taxonomy' => 'product_type',
            'field' => 'slug',
            'terms' => ['experience', 'esperienza'],
            'operator' => 'NOT IN'
        ];
        
        DiagnosticLogger::instance()->debug('Experiences excluded from XML sitemap', [
            'post_type' => $post_type
        ], 'visibility');
        
        return $args;
    }
    
    /**
     * Exclude experiences from WooCommerce product queries
     * 
     * @param array $tax_query Current tax query
     * @param \WC_Query $wc_query WooCommerce query instance
     * @return array Modified tax query
     */
    public static function exclude_from_product_query($tax_query, $wc_query) {
        if (!self::hide_flag()) {
            return $tax_query;
        }
        
        // Only apply on shop/category pages, not single product pages
        if (is_product() || is_admin()) {
            return $tax_query;
        }
        
        $tax_query[] = [
            'taxonomy' => 'product_type',
            'field' => 'slug',
            'terms' => ['experience', 'esperienza'],
            'operator' => 'NOT IN'
        ];
        
        return $tax_query;
    }
    
    /**
     * Exclude experiences from WooCommerce shortcodes (products, recent_products, etc.)
     * 
     * @param array $query_args Query arguments
     * @param array $attributes Shortcode attributes
     * @param string $type Shortcode type
     * @return array Modified query arguments
     */
    public static function exclude_from_shortcodes($query_args, $attributes, $type) {
        if (!self::hide_flag()) {
            return $query_args;
        }
        
        // Skip if explicitly including experiences
        if (isset($attributes['include_experiences']) && $attributes['include_experiences']) {
            return $query_args;
        }
        
        if (!isset($query_args['tax_query'])) {
            $query_args['tax_query'] = [];
        }
        
        $query_args['tax_query'][] = [
            'taxonomy' => 'product_type',
            'field' => 'slug',
            'terms' => ['experience', 'esperienza'],
            'operator' => 'NOT IN'
        ];
        
        DiagnosticLogger::instance()->debug('Experiences excluded from WooCommerce shortcode', [
            'shortcode_type' => $type,
            'attributes' => $attributes
        ], 'visibility');
        
        return $query_args;
    }
    
    /**
     * Exclude experiences from wc_get_products() function calls
     * 
     * @param array $query_args Query arguments
     * @param array $query_vars Query variables
     * @return array Modified query arguments
     */
    public static function exclude_from_wc_get_products($query_args, $query_vars) {
        if (!self::hide_flag()) {
            return $query_args;
        }
        
        // Skip if type is explicitly set to include experiences
        if (isset($query_vars['type']) && in_array('experience', (array)$query_vars['type'])) {
            return $query_args;
        }
        
        // Skip if we're specifically looking for experiences
        if (isset($query_vars['wcefp_include_experiences']) && $query_vars['wcefp_include_experiences']) {
            return $query_args;
        }
        
        if (!isset($query_args['tax_query'])) {
            $query_args['tax_query'] = [];
        }
        
        $query_args['tax_query'][] = [
            'taxonomy' => 'product_type',
            'field' => 'slug',
            'terms' => ['experience', 'esperienza'],
            'operator' => 'NOT IN'
        ];
        
        return $query_args;
    }
    
    /**
     * Add experience exclusion to a WP_Query object
     * 
     * @param \WP_Query $query Query to modify
     */
    private static function add_experience_exclusion_to_query(\WP_Query $query) {
        $tax_query = $query->get('tax_query') ?: [];
        
        $tax_query[] = [
            'taxonomy' => 'product_type',
            'field' => 'slug',
            'terms' => ['experience', 'esperienza'],
            'operator' => 'NOT IN'
        ];
        
        $query->set('tax_query', $tax_query);
    }
    
    /**
     * Get human-readable query type for logging
     * 
     * @param \WP_Query $query Query instance
     * @return string Query type description
     */
    private static function get_query_type(\WP_Query $query) {
        if ($query->is_shop()) {
            return 'shop';
        }
        if ($query->is_post_type_archive('product')) {
            return 'product_archive';
        }
        if ($query->is_tax('product_cat')) {
            return 'product_category';
        }
        if ($query->is_tax('product_tag')) {
            return 'product_tag';
        }
        if ($query->is_search()) {
            return 'search';
        }
        
        return 'other';
    }
    
    /**
     * Get all experience/esperienza product IDs (for debugging)
     * 
     * @return array Array of product IDs
     */
    public static function get_experience_product_ids() {
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => ['experience', 'esperienza'],
                    'operator' => 'IN'
                ]
            ]
        ];
        
        return get_posts($args);
    }
    
    /**
     * Check if a product is an experience
     * 
     * @param int|\WC_Product $product Product ID or object
     * @return bool True if product is an experience
     */
    public static function is_experience($product) {
        if (is_numeric($product)) {
            $product = wc_get_product($product);
        }
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }
        
        return in_array($product->get_type(), ['experience', 'esperienza']);
    }
    
    /**
     * Get visibility status for admin/debugging
     * 
     * @return array Status information
     */
    public static function get_status() {
        $experience_ids = self::get_experience_product_ids();
        
        return [
            'gating_enabled' => self::hide_flag(),
            'experience_products_count' => count($experience_ids),
            'experience_product_ids' => $experience_ids,
            'hooks_active' => [
                'pre_get_posts' => has_action('pre_get_posts', [__CLASS__, 'exclude_from_loops']),
                'woocommerce_rest_product_object_query' => has_filter('woocommerce_rest_product_object_query', [__CLASS__, 'exclude_from_rest']),
                'wp_sitemaps_posts_query_args' => has_filter('wp_sitemaps_posts_query_args', [__CLASS__, 'exclude_from_sitemaps']),
                'woocommerce_product_query_tax_query' => has_filter('woocommerce_product_query_tax_query', [__CLASS__, 'exclude_from_product_query']),
                'woocommerce_shortcode_products_query' => has_filter('woocommerce_shortcode_products_query', [__CLASS__, 'exclude_from_shortcodes']),
                'wc_get_products_query' => has_filter('wc_get_products_query', [__CLASS__, 'exclude_from_wc_get_products'])
            ]
        ];
    }
}