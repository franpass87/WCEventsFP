<?php
/**
 * Safe Renderer Utility
 * 
 * Provides error-safe rendering functions to prevent WSOD on frontend
 * 
 * @package WCEFP
 * @subpackage Utils
 * @since 2.1.5
 */

namespace WCEFP\Utils;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Safe renderer class for preventing fatal errors on frontend
 */
class SafeRenderer {
    
    /**
     * Safely render booking widget with comprehensive error handling
     * 
     * @param int $product_id Product ID
     * @return string Safe HTML output
     */
    public static function safe_booking_widget($product_id) {
        try {
            $product_id = absint($product_id);
            if (!$product_id) {
                return '<!-- WCEFP: Invalid product ID -->';
            }
            
            // Check if product exists
            $product = wc_get_product($product_id);
            if (!$product) {
                self::log_error('Product not found', ['product_id' => $product_id]);
                return '<!-- WCEFP: Product not found -->';
            }
            
            // Check if product is WCEFP event type
            if (!in_array($product->get_type(), ['wcefp_event', 'wcefp_experience'], true)) {
                return '<!-- WCEFP: Not an event product -->';
            }
            
            // Load legacy frontend class safely
            if (!class_exists('WCEFP_Frontend')) {
                $frontend_file = WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-frontend.php';
                if (file_exists($frontend_file)) {
                    require_once $frontend_file;
                } else {
                    self::log_error('Frontend class file not found', ['file' => $frontend_file]);
                    return self::fallback_widget($product_id);
                }
            }
            
            // Safely call the shortcode function
            if (class_exists('WCEFP_Frontend') && method_exists('WCEFP_Frontend', 'shortcode_booking')) {
                $output = \WCEFP_Frontend::shortcode_booking(['product_id' => $product_id]);
                return $output ?: '<!-- WCEFP: Empty widget output -->';
            } else {
                self::log_error('Frontend class or method not available');
                return self::fallback_widget($product_id);
            }
            
        } catch (\Error $e) {
            self::log_error('Fatal error in booking widget', [
                'product_id' => $product_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return self::error_widget($e->getMessage());
        } catch (\Exception $e) {
            self::log_error('Exception in booking widget', [
                'product_id' => $product_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return self::error_widget($e->getMessage());
        } catch (\Throwable $e) {
            self::log_error('Throwable in booking widget', [
                'product_id' => $product_id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return self::error_widget($e->getMessage());
        }
    }
    
    /**
     * Safely render shortcode with error handling
     * 
     * @param string $shortcode_name Shortcode name
     * @param array $atts Shortcode attributes
     * @return string Safe HTML output
     */
    public static function safe_shortcode($shortcode_name, $atts = []) {
        try {
            if (!shortcode_exists($shortcode_name)) {
                self::log_error('Shortcode not registered', ['shortcode' => $shortcode_name]);
                return "<!-- WCEFP: Shortcode '{$shortcode_name}' not found -->";
            }
            
            $output = do_shortcode("[{$shortcode_name} " . self::build_atts_string($atts) . "]");
            return $output ?: "<!-- WCEFP: Empty shortcode output for '{$shortcode_name}' -->";
            
        } catch (\Throwable $e) {
            self::log_error('Error in shortcode rendering', [
                'shortcode' => $shortcode_name,
                'atts' => $atts,
                'error' => $e->getMessage()
            ]);
            return self::error_widget($e->getMessage());
        }
    }
    
    /**
     * Log errors safely
     * 
     * @param string $message Error message
     * @param array $context Additional context
     */
    private static function log_error($message, $context = []) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            $log_message = 'WCEFP SafeRenderer: ' . $message;
            if (!empty($context)) {
                $log_message .= ' | Context: ' . wp_json_encode($context);
            }
            error_log($log_message);
        }
        
        // Also use plugin logger if available
        if (class_exists('\WCEFP\Utils\Logger')) {
            try {
                \WCEFP\Utils\Logger::error($message, $context);
            } catch (\Throwable $e) {
                // Fallback to error_log if Logger fails
                error_log('WCEFP SafeRenderer Logger failed: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Create fallback widget when main widget fails
     * 
     * @param int $product_id Product ID
     * @return string Fallback HTML
     */
    private static function fallback_widget($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return '<!-- WCEFP: Fallback widget - product not found -->';
        }
        
        return '<div class="wcefp-fallback-widget notice notice-info" style="padding: 15px; margin: 20px 0; border: 1px solid #c3c4c7; border-radius: 4px;">' .
               '<p><strong>' . __('Booking System', 'wceventsfp') . '</strong></p>' .
               '<p>' . sprintf(__('The booking system for "%s" is temporarily unavailable. Please contact us directly to make a reservation.', 'wceventsfp'), esc_html($product->get_name())) . '</p>' .
               '</div>';
    }
    
    /**
     * Create error widget for debugging
     * 
     * @param string $error_message Error message
     * @return string Error widget HTML
     */
    private static function error_widget($error_message) {
        if (!current_user_can('manage_options') || (!defined('WP_DEBUG') || !WP_DEBUG)) {
            return '<!-- WCEFP Error: Booking widget failed to load -->';
        }
        
        return '<div class="wcefp-error-widget notice notice-error" style="padding: 15px; margin: 20px 0; border: 1px solid #dc3232; border-radius: 4px; background: #fbeaea;">' .
               '<p><strong>' . __('WCEFP Debug Error', 'wceventsfp') . '</strong></p>' .
               '<p>' . esc_html($error_message) . '</p>' .
               '<p><small>' . __('This error is only shown to administrators when WP_DEBUG is enabled.', 'wceventsfp') . '</small></p>' .
               '</div>';
    }
    
    /**
     * Build attributes string for shortcode
     * 
     * @param array $atts Attributes array
     * @return string Attributes string
     */
    private static function build_atts_string($atts) {
        $parts = [];
        foreach ($atts as $key => $value) {
            $parts[] = $key . '="' . esc_attr($value) . '"';
        }
        return implode(' ', $parts);
    }
    
    /**
     * Safely enqueue frontend assets only where needed
     * 
     * @return void
     */
    public static function safe_enqueue_frontend_assets() {
        try {
            // Only enqueue on product pages or pages with WCEFP shortcodes
            if (!self::should_enqueue_assets()) {
                return;
            }
            
            self::enqueue_wcefp_assets();
            
        } catch (\Throwable $e) {
            self::log_error('Error enqueuing frontend assets', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if WCEFP assets should be enqueued
     * 
     * @return bool
     */
    private static function should_enqueue_assets() {
        global $post, $product;
        
        // Always enqueue on WooCommerce product pages
        if (is_product()) {
            if ($product && in_array($product->get_type(), ['wcefp_event', 'wcefp_experience'], true)) {
                return true;
            }
        }
        
        // Check for WCEFP shortcodes in post content
        if ($post && has_shortcode($post->post_content, 'wcefp_booking')) {
            return true;
        }
        
        if ($post && has_shortcode($post->post_content, 'wcefp_events')) {
            return true;
        }
        
        // Check for other WCEFP shortcodes
        $wcefp_shortcodes = [
            'wcefp_event', 'wcefp_booking_form', 'wcefp_search',
            'wcefp_featured_events', 'wcefp_upcoming_events', 'wcefp_event_calendar'
        ];
        
        foreach ($wcefp_shortcodes as $shortcode) {
            if ($post && has_shortcode($post->post_content, $shortcode)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Enqueue WCEFP assets safely
     * 
     * @return void
     */
    private static function enqueue_wcefp_assets() {
        $plugin_url = defined('WCEFP_PLUGIN_URL') ? WCEFP_PLUGIN_URL : plugin_dir_url(WCEFP_PLUGIN_FILE);
        $version = defined('WCEFP_VERSION') ? WCEFP_VERSION : '2.1.4';
        
        // Enqueue frontend CSS if it exists
        $frontend_css = $plugin_url . 'assets/css/frontend.css';
        if (file_exists(WCEFP_PLUGIN_DIR . 'assets/css/frontend.css')) {
            wp_enqueue_style('wcefp-frontend', $frontend_css, [], $version);
        }
        
        // Enqueue frontend JS if it exists
        $frontend_js = $plugin_url . 'assets/js/frontend.js';
        if (file_exists(WCEFP_PLUGIN_DIR . 'assets/js/frontend.js')) {
            wp_enqueue_script('wcefp-frontend', $frontend_js, ['jquery'], $version, true);
            
            // Localize script with safe data
            wp_localize_script('wcefp-frontend', 'wcefp_frontend', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcefp_public'),
                'currency' => get_option('woocommerce_currency', 'EUR'),
                'strings' => [
                    'loading' => __('Loading...', 'wceventsfp'),
                    'error' => __('An error occurred. Please try again.', 'wceventsfp'),
                    'select_date' => __('Select Date', 'wceventsfp'),
                    'select_time' => __('Select Time', 'wceventsfp'),
                ]
            ]);
        }
    }
}