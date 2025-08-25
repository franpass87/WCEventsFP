<?php
/**
 * WooCommerce Archive Filter
 * 
 * Handles hiding event/experience products from WooCommerce archives and search
 * while providing optional redirects to dedicated landing pages
 * 
 * @package WCEFP
 * @subpackage Frontend  
 * @since 2.2.0
 */

namespace WCEFP\Frontend;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Archive Filter Class
 * 
 * Manages visibility of event/experience products in WooCommerce contexts
 */
class WooCommerceArchiveFilter {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize filters
     * 
     * @return void
     */
    private function init() {
        // Only run on frontend
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        // Hide from shop and category archives
        add_action('pre_get_posts', [$this, 'filter_shop_archives'], 20);
        
        // Hide from search results
        add_action('pre_get_posts', [$this, 'filter_search_results'], 20);
        
        // Optional redirect for single product pages
        add_action('template_redirect', [$this, 'maybe_redirect_single_product']);
        
        // Filter related products
        add_filter('woocommerce_output_related_products_args', [$this, 'filter_related_products_args']);
        
        // Filter cross-sells and up-sells
        add_filter('woocommerce_cart_crosssell_ids', [$this, 'filter_crosssell_ids']);
        
        // Hide from product widgets
        add_filter('woocommerce_products_widget_query_args', [$this, 'filter_widget_query_args']);
        
        // Add admin settings
        add_action('admin_init', [$this, 'register_admin_settings']);
    }
    
    /**
     * Filter shop and category archive queries
     * 
     * @param \WP_Query $query Query object
     * @return void
     */
    public function filter_shop_archives($query) {
        // Skip if not main query or admin
        if (!$query->is_main_query() || is_admin()) {
            return;
        }
        
        // Skip if setting is disabled
        if (!$this->is_archive_filtering_enabled()) {
            return;
        }
        
        // Handle shop page
        if (wc_get_page_id('shop') && $query->is_page(wc_get_page_id('shop'))) {
            $this->exclude_event_products($query);
        }
        
        // Handle product category/tag archives
        if ($query->is_tax(['product_cat', 'product_tag']) || $query->is_post_type_archive('product')) {
            $this->exclude_event_products($query);
        }
    }
    
    /**
     * Filter search results
     * 
     * @param \WP_Query $query Query object
     * @return void
     */
    public function filter_search_results($query) {
        // Skip if not main query, admin, or search filtering disabled
        if (!$query->is_main_query() || is_admin() || !$this->is_search_filtering_enabled()) {
            return;
        }
        
        // Only filter search queries that include products
        if ($query->is_search()) {
            $post_types = $query->get('post_type');
            
            // If no post type specified, WordPress searches all public post types
            if (empty($post_types) || (is_array($post_types) && in_array('product', $post_types))) {
                $this->exclude_event_products($query);
            }
        }
    }
    
    /**
     * Exclude event/experience products from query
     * 
     * @param \WP_Query $query Query object
     * @return void
     */
    private function exclude_event_products($query) {
        $meta_query = $query->get('meta_query', []);
        
        // Add meta query to exclude event/experience product types
        $meta_query[] = [
            'key' => '_wcefp_product_type',
            'value' => ['evento', 'esperienza'],
            'compare' => 'NOT IN'
        ];
        
        // Alternative approach using tax_query if product types are stored as taxonomy
        $tax_query = $query->get('tax_query', []);
        
        $tax_query[] = [
            'taxonomy' => 'product_type',
            'field' => 'slug',
            'terms' => ['evento', 'esperienza'],
            'operator' => 'NOT IN'
        ];
        
        $query->set('meta_query', $meta_query);
        $query->set('tax_query', $tax_query);
    }
    
    /**
     * Maybe redirect single product pages to landing pages
     * 
     * @return void
     */
    public function maybe_redirect_single_product() {
        if (!is_product() || !$this->is_single_redirect_enabled()) {
            return;
        }
        
        global $post;
        $product = wc_get_product($post->ID);
        
        if (!$product) {
            return;
        }
        
        // Check if this is an event/experience product
        if (in_array($product->get_type(), ['evento', 'esperienza'])) {
            $landing_page_id = $this->get_product_landing_page($product->get_id());
            
            if ($landing_page_id && $landing_page_id != $post->ID) {
                wp_safe_redirect(get_permalink($landing_page_id), 301);
                exit;
            }
        }
    }
    
    /**
     * Filter related products to exclude events/experiences
     * 
     * @param array $args Related products args
     * @return array
     */
    public function filter_related_products_args($args) {
        if (!$this->is_archive_filtering_enabled()) {
            return $args;
        }
        
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }
        
        $args['meta_query'][] = [
            'key' => '_wcefp_product_type',
            'value' => ['evento', 'esperienza'],
            'compare' => 'NOT IN'
        ];
        
        return $args;
    }
    
    /**
     * Filter cross-sell product IDs
     * 
     * @param array $crosssell_ids Product IDs
     * @return array
     */
    public function filter_crosssell_ids($crosssell_ids) {
        if (!$this->is_archive_filtering_enabled() || empty($crosssell_ids)) {
            return $crosssell_ids;
        }
        
        return array_filter($crosssell_ids, function($product_id) {
            $product = wc_get_product($product_id);
            return $product && !in_array($product->get_type(), ['evento', 'esperienza']);
        });
    }
    
    /**
     * Filter product widget query args
     * 
     * @param array $args Widget query args
     * @return array
     */
    public function filter_widget_query_args($args) {
        if (!$this->is_archive_filtering_enabled()) {
            return $args;
        }
        
        if (!isset($args['meta_query'])) {
            $args['meta_query'] = [];
        }
        
        $args['meta_query'][] = [
            'key' => '_wcefp_product_type',
            'value' => ['evento', 'esperienza'],
            'compare' => 'NOT IN'
        ];
        
        return $args;
    }
    
    /**
     * Check if archive filtering is enabled
     * 
     * @return bool
     */
    private function is_archive_filtering_enabled() {
        return get_option('wcefp_hide_from_archives', true);
    }
    
    /**
     * Check if search filtering is enabled
     * 
     * @return bool
     */
    private function is_search_filtering_enabled() {
        return get_option('wcefp_hide_from_search', true);
    }
    
    /**
     * Check if single product redirect is enabled
     * 
     * @return bool
     */
    private function is_single_redirect_enabled() {
        return get_option('wcefp_redirect_single_products', false);
    }
    
    /**
     * Get landing page ID for a product
     * 
     * @param int $product_id Product ID
     * @return int|false
     */
    private function get_product_landing_page($product_id) {
        $landing_page_id = get_post_meta($product_id, '_wcefp_landing_page_id', true);
        
        if ($landing_page_id && get_post_status($landing_page_id) === 'publish') {
            return $landing_page_id;
        }
        
        return false;
    }
    
    /**
     * Register admin settings for archive filtering
     * 
     * @return void
     */
    public function register_admin_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Add settings section
        add_settings_section(
            'wcefp_archive_filtering',
            __('Archive & Search Filtering', 'wceventsfp'),
            [$this, 'render_settings_section_description'],
            'wcefp_settings'
        );
        
        // Hide from archives setting
        add_settings_field(
            'wcefp_hide_from_archives',
            __('Hide from Shop Archives', 'wceventsfp'),
            [$this, 'render_checkbox_field'],
            'wcefp_settings',
            'wcefp_archive_filtering',
            [
                'option_name' => 'wcefp_hide_from_archives',
                'description' => __('Hide event/experience products from shop page and category archives', 'wceventsfp'),
                'default' => true
            ]
        );
        
        // Hide from search setting
        add_settings_field(
            'wcefp_hide_from_search',
            __('Hide from Search Results', 'wceventsfp'),
            [$this, 'render_checkbox_field'],
            'wcefp_settings',
            'wcefp_archive_filtering',
            [
                'option_name' => 'wcefp_hide_from_search',
                'description' => __('Hide event/experience products from search results', 'wceventsfp'),
                'default' => true
            ]
        );
        
        // Redirect single products setting
        add_settings_field(
            'wcefp_redirect_single_products',
            __('Redirect Single Products', 'wceventsfp'),
            [$this, 'render_checkbox_field'],
            'wcefp_settings',
            'wcefp_archive_filtering',
            [
                'option_name' => 'wcefp_redirect_single_products',
                'description' => __('Redirect single event/experience product pages to their dedicated landing pages', 'wceventsfp'),
                'default' => false
            ]
        );
        
        // Register settings
        register_setting('wcefp_settings', 'wcefp_hide_from_archives', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('wcefp_settings', 'wcefp_hide_from_search', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('wcefp_settings', 'wcefp_redirect_single_products', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
    }
    
    /**
     * Render settings section description
     * 
     * @return void
     */
    public function render_settings_section_description() {
        echo '<p>' . esc_html__('Control how event/experience products appear in WooCommerce archives and search results. This allows you to create dedicated booking experiences while keeping products hidden from standard shop browsing.', 'wceventsfp') . '</p>';
    }
    
    /**
     * Render checkbox field
     * 
     * @param array $args Field arguments
     * @return void
     */
    public function render_checkbox_field($args) {
        $option_name = $args['option_name'];
        $description = $args['description'] ?? '';
        $default = $args['default'] ?? false;
        
        $value = get_option($option_name, $default);
        
        echo '<label>';
        echo '<input type="checkbox" name="' . esc_attr($option_name) . '" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . esc_html($description);
        echo '</label>';
    }
    
    /**
     * Get filtered product count for admin display
     * 
     * @return array
     */
    public function get_filtered_product_stats() {
        global $wpdb;
        
        // Count total event/experience products
        $total_events = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product' 
             AND p.post_status = 'publish'
             AND pm.meta_key = '_wcefp_product_type'
             AND pm.meta_value IN ('evento', 'esperienza')"
        );
        
        // Count products with landing pages
        $with_landing_pages = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id
             INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             AND pm1.meta_key = '_wcefp_product_type'
             AND pm1.meta_value IN ('evento', 'esperienza')
             AND pm2.meta_key = '_wcefp_landing_page_id'
             AND pm2.meta_value != ''"
        );
        
        return [
            'total_events' => intval($total_events),
            'with_landing_pages' => intval($with_landing_pages),
            'archive_filtering_enabled' => $this->is_archive_filtering_enabled(),
            'search_filtering_enabled' => $this->is_search_filtering_enabled(),
            'redirect_enabled' => $this->is_single_redirect_enabled()
        ];
    }
}