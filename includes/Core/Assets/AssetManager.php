<?php
/**
 * Asset Manager
 * 
 * @package WCEFP
 * @subpackage Core\Assets
 * @since 2.1.1
 */

namespace WCEFP\Core\Assets;

use WCEFP\Utils\StringHelper;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages plugin assets (CSS, JavaScript)
 */
class AssetManager {
    
    /**
     * Plugin version
     * 
     * @var string
     */
    private $version;
    
    /**
     * Plugin URL
     * 
     * @var string
     */
    private $plugin_url;
    
    /**
     * Constructor
     * 
     * @param string $version Plugin version
     * @param string $plugin_url Plugin URL
     */
    public function __construct($version, $plugin_url) {
        $this->version = $version;
        $this->plugin_url = $plugin_url;
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     * 
     * @return void
     */
    private function init_hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_head', [$this, 'add_emergency_error_display'], 1);
    }
    
    /**
     * Enqueue frontend assets
     * 
     * @return void
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on relevant pages
        if (!$this->is_wcefp_frontend_page()) {
            return;
        }
        
        // Check if we have WCEFP shortcodes or blocks on current page
        $has_wcefp_content = $this->has_wcefp_content();
        
        // Always enqueue base frontend assets
        wp_enqueue_style(
            'wcefp-frontend',
            $this->plugin_url . 'assets/css/frontend.css',
            [],
            $this->version
        );
        
        wp_enqueue_script(
            'wcefp-frontend',
            $this->plugin_url . 'assets/js/frontend.js',
            ['jquery'],
            $this->version,
            true
        );
        
        // Conditionally enqueue widget system if WCEFP content is present
        if ($has_wcefp_content) {
            wp_enqueue_style(
                'wcefp-widgets',
                $this->plugin_url . 'assets/css/wcefp-widgets.css',
                ['wcefp-frontend'],
                $this->version
            );
            
            wp_enqueue_script(
                'wcefp-widgets',
                $this->plugin_url . 'assets/js/wcefp-widgets.js',
                ['jquery', 'wcefp-frontend'],
                $this->version,
                true
            );
        }
        
        // Conditionally enqueue experiences catalog assets
        if ($this->has_experiences_catalog()) {
            wp_enqueue_style(
                'wcefp-experiences-catalog',
                $this->plugin_url . 'assets/css/experiences-catalog.css',
                ['wcefp-frontend'],
                $this->version
            );
            
            wp_enqueue_script(
                'wcefp-experiences-catalog',
                $this->plugin_url . 'assets/js/experiences-catalog.js',
                ['jquery', 'wcefp-frontend'],
                $this->version,
                true
            );
        }
        
        // Localize script with data
        wp_localize_script('wcefp-frontend', 'WCEFPData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_frontend'),
            'has_widgets' => $has_wcefp_content,
            'strings' => [
                'loading' => __('Loading...', 'wceventsfp'),
                'error' => __('An error occurred. Please try again.', 'wceventsfp'),
                'success' => __('Success!', 'wceventsfp'),
                'required_field' => __('This field is required.', 'wceventsfp'),
                'invalid_email' => __('Please enter a valid email address.', 'wceventsfp'),
                'invalid_number' => __('Please enter a valid number.', 'wceventsfp'),
                'invalid_date' => __('Please enter a valid date.', 'wceventsfp'),
                'form_submitted' => __('Form submitted successfully!', 'wceventsfp'),
                'connection_error' => __('Connection error. Please check your internet connection.', 'wceventsfp')
            ]
        ]);
        
        // Enqueue conversion optimization if enabled
        if (get_option('wcefp_conversion_optimization', false)) {
            wp_enqueue_script(
                'wcefp-conversion-optimization',
                $this->plugin_url . 'assets/js/conversion-optimization.js',
                ['wcefp-frontend'],
                $this->version,
                true
            );
        }
    }
    
    /**
     * Enqueue admin assets
     * 
     * @return void
     */
    public function enqueue_admin_assets() {
        $screen = get_current_screen();
        
        // Only load on relevant admin pages
        if (!$this->is_wcefp_admin_page($screen)) {
            return;
        }
        
        wp_enqueue_style(
            'wcefp-admin',
            $this->plugin_url . 'assets/css/admin.css',
            [],
            $this->version
        );
        
        // Product editor specific assets
        if ($this->is_product_edit_page($screen)) {
            wp_enqueue_style(
                'wcefp-product-editor',
                $this->plugin_url . 'assets/admin/css/product-editor.css',
                ['wcefp-admin'],
                $this->version
            );
            
            wp_enqueue_script(
                'wcefp-product-editor',
                $this->plugin_url . 'assets/admin/js/product-editor.js',
                ['jquery', 'select2'],
                $this->version,
                true
            );
        }
        
        wp_enqueue_script(
            'wcefp-modals',
            $this->plugin_url . 'assets/js/wcefp-modals.js',
            ['jquery'],
            $this->version,
            true
        );

        wp_enqueue_script(
            'wcefp-admin',
            $this->plugin_url . 'assets/js/admin.js',
            ['jquery', 'wp-util', 'wcefp-modals'],
            $this->version,
            true
        );
        
        // Localize admin script
        wp_localize_script('wcefp-admin', 'WCEFPAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_admin'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete this item?', 'wceventsfp'),
                'loading' => __('Loading...', 'wceventsfp'),
                'saved' => __('Saved successfully!', 'wceventsfp'),
                'error' => __('An error occurred. Please try again.', 'wceventsfp')
            ]
        ]);
        
        // Enqueue additional assets based on page
        if (StringHelper::safe_strpos($screen->id, 'wcefp_analytics') !== false) {
            wp_enqueue_script('chart-js', $this->plugin_url . 'assets/js/chart.min.js', [], $this->version);
        }
        
        if (StringHelper::safe_strpos($screen->id, 'wcefp_calendar') !== false) {
            wp_enqueue_script('fullcalendar', $this->plugin_url . 'assets/js/fullcalendar.min.js', [], $this->version);
        }
    }
    
    /**
     * Add emergency error display to head
     * 
     * @return void
     */
    public function add_emergency_error_display() {
        if (empty($GLOBALS['wcefp_emergency_errors'])) {
            return;
        }
        
        // Enqueue the CSS if not already done
        if (!wp_style_is('wcefp-admin', 'enqueued')) {
            echo '<link rel="stylesheet" href="' . esc_url($this->plugin_url . 'assets/css/admin.css') . '" type="text/css" />';
        }
        
        // Display error messages
        foreach ($GLOBALS['wcefp_emergency_errors'] as $index => $error) {
            $class = 'wcefp-emergency-error ' . esc_attr($error['type']);
            echo '<div class="' . $class . '" id="wcefp-error-' . $index . '">';
            echo '<button class="wcefp-emergency-error-close" onclick="document.getElementById(\'wcefp-error-' . $index . '\').style.display=\'none\'">&times;</button>';
            echo '<strong>WCEventsFP Plugin Error:</strong> ' . esc_html($error['message']);
            echo '</div>';
        }
    }
    
    /**
     * Check if current page is a WCEFP frontend page
     * 
     * @return bool
     */
    private function is_wcefp_frontend_page() {
        global $post;
        
        // Check if it's a product page
        if (is_product()) {
            return true;
        }
        
        // Check if it's a shop or product archive page
        if (is_shop() || is_product_category() || is_product_tag()) {
            return true;
        }
        
        // Check if page contains WCEFP shortcode
        if ($post && has_shortcode($post->post_content, 'wcefp_')) {
            return true;
        }
        
        // Check if cart contains WCEFP products
        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                if ($product && in_array($product->get_type(), ['evento', 'esperienza'])) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if current page has WCEFP content requiring widget system
     * 
     * @return bool
     */
    private function has_wcefp_content() {
        global $post;
        
        // Check for WCEFP shortcodes
        if ($post) {
            $shortcodes = [
                'wcefp_booking_form', 
                'wcefp_event_calendar', 
                'wcefp_event_list',
                'wcefp_experiences',
                'wcefp_events',
                'wcefp_search'
            ];
            foreach ($shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    return true;
                }
            }
        }
        
        // Check for Gutenberg blocks
        if ($post && function_exists('has_block')) {
            $blocks = [
                'wcefp/booking-form', 
                'wcefp/event-calendar', 
                'wcefp/event-list',
                'wcefp/experiences-catalog'
            ];
            foreach ($blocks as $block) {
                if (has_block($block, $post)) {
                    return true;
                }
            }
        }
        
        // Check if it's a WCEFP product page
        if (is_product()) {
            $product = wc_get_product();
            if ($product && in_array($product->get_type(), ['evento', 'esperienza'])) {
                return true;
            }
        }
        
        // Check if page has .wcefp-widget class (for dynamic content)
        if (is_page() || is_single()) {
            return true; // Let conditional loading handle this
        }
        
        return false;
    }
    
    /**
     * Check if current admin page is WCEFP related
     * 
     * @param \WP_Screen $screen Current screen object
     * @return bool
     */
    private function is_wcefp_admin_page($screen) {
        if (!$screen) {
            return false;
        }
        
        // WCEFP admin pages
        if (StringHelper::safe_strpos($screen->id, 'wcefp') !== false) {
            return true;
        }
        
        // Product edit pages
        if ($screen->post_type === 'product') {
            return true;
        }
        
        // WooCommerce order pages (for booking integration)
        if ($screen->id === 'shop_order' || $screen->id === 'edit-shop_order') {
            return true;
        }
        
        // Dashboard (for widgets)
        if ($screen->id === 'dashboard') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if current page is a product edit page
     * 
     * @param \WP_Screen $screen Current screen object
     * @return bool
     */
    private function is_product_edit_page($screen) {
        return $screen && (
            $screen->id === 'product' || 
            $screen->post_type === 'product'
        );
    }
    
    /**
     * Check if current page has experiences catalog content
     * 
     * @return bool
     */
    private function has_experiences_catalog() {
        global $post;
        
        if (!$post) return false;
        
        // Check for experiences catalog shortcode
        if (has_shortcode($post->post_content, 'wcefp_experiences')) {
            return true;
        }
        
        // Check for Gutenberg experiences catalog block
        if (function_exists('has_block') && has_block('wcefp/experiences-catalog', $post)) {
            return true;
        }
        
        return false;
    }
}