<?php
/**
 * Advanced Pricing Manager
 * 
 * Handles dynamic pricing, group discounts, seasonal pricing, and advanced pricing features
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage BookingFeatures
 * @since 2.2.0
 */

namespace WCEFP\BookingFeatures;

use WCEFP\Utils\DateTimeHelper;

class AdvancedPricingManager {
    
    private $datetime_helper;
    
    public function __construct() {
        $this->datetime_helper = new DateTimeHelper();
        
        // WooCommerce pricing hooks
        add_filter('woocommerce_product_get_price', [$this, 'modify_product_price'], 20, 2);
        add_filter('woocommerce_product_variation_get_price', [$this, 'modify_product_price'], 20, 2);
        add_filter('wcefp_booking_price', [$this, 'calculate_dynamic_price'], 10, 3);
        
        // Admin hooks for pricing management
        add_action('woocommerce_product_options_pricing', [$this, 'add_pricing_options']);
        add_action('woocommerce_process_product_meta', [$this, 'save_pricing_options']);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_calculate_price', [$this, 'calculate_price_ajax']);
        add_action('wp_ajax_nopriv_wcefp_calculate_price', [$this, 'calculate_price_ajax']);
        add_action('wp_ajax_wcefp_get_pricing_calendar', [$this, 'get_pricing_calendar_ajax']);
        
        // Shortcode for pricing calculator
        add_shortcode('wcefp_pricing_calculator', [$this, 'render_pricing_calculator']);
    }
    
    /**
     * Modify product price based on dynamic pricing rules
     *
     * @param float $price Product price
     * @param WC_Product $product Product object
     * @return float Modified price
     */
    public function modify_product_price($price, $product) {
        // Only apply to event products
        if (!$this->is_event_product($product->get_id())) {
            return $price;
        }
        
        // Get current context (session data, cart data, etc.)
        $context = $this->get_pricing_context();
        
        if (!$context) {
            return $price;
        }
        
        // Calculate dynamic price
        $dynamic_price = $this->calculate_dynamic_price($price, $product->get_id(), $context);
        
        return $dynamic_price !== false ? $dynamic_price : $price;
    }
    
    /**
     * Calculate dynamic price based on various factors
     *
     * @param float $base_price Base price
     * @param int $product_id Product ID
     * @param array $context Pricing context
     * @return float|false Dynamic price or false if no change
     */
    public function calculate_dynamic_price($base_price, $product_id, $context) {
        $price = $base_price;
        
        // Apply demand-based pricing
        $demand_adjustment = $this->calculate_demand_pricing($product_id, $context);
        $price += $demand_adjustment;
        
        // Apply seasonal pricing
        if (!empty($context['date'])) {
            $seasonal_adjustment = $this->calculate_seasonal_pricing($context['date'], $base_price);
            $price += $seasonal_adjustment;
        }
        
        // Apply group discounts
        if (!empty($context['participants']) && $context['participants'] > 1) {
            $group_discount = $this->calculate_group_discount($context['participants'], $price);
            $price -= $group_discount;
        }
        
        // Apply early bird pricing
        if (!empty($context['date'])) {
            $early_bird_discount = $this->calculate_early_bird_discount($context['date'], $price);
            $price -= $early_bird_discount;
        }
        
        // Apply last-minute pricing
        if (!empty($context['date'])) {
            $last_minute_adjustment = $this->calculate_last_minute_pricing($context['date'], $price);
            $price += $last_minute_adjustment;
        }
        
        // Apply loyalty discounts
        if (!empty($context['customer_id'])) {
            $loyalty_discount = $this->calculate_loyalty_discount($context['customer_id'], $price);
            $price -= $loyalty_discount;
        }
        
        // Apply capacity-based pricing
        $capacity_adjustment = $this->calculate_capacity_pricing($product_id, $context);
        $price += $capacity_adjustment;
        
        // Ensure price doesn't go below minimum
        $min_price = get_post_meta($product_id, '_wcefp_min_price', true) ?: ($base_price * 0.5);
        $price = max($price, $min_price);
        
        // Ensure price doesn't exceed maximum
        $max_price = get_post_meta($product_id, '_wcefp_max_price', true) ?: ($base_price * 2);
        $price = min($price, $max_price);
        
        return $price;
    }
    
    /**
     * Calculate demand-based pricing
     *
     * @param int $product_id Product ID
     * @param array $context Pricing context
     * @return float Price adjustment
     */
    private function calculate_demand_pricing($product_id, $context) {
        // Get demand metrics for the past 30 days
        $bookings_count = $this->get_recent_bookings_count($product_id, 30);
        $views_count = $this->get_recent_views_count($product_id, 7);
        
        // Get demand thresholds
        $thresholds = get_post_meta($product_id, '_wcefp_demand_thresholds', true) ?: [
            'high' => ['bookings' => 20, 'views' => 100, 'adjustment' => 15],
            'medium' => ['bookings' => 10, 'views' => 50, 'adjustment' => 5],
            'low' => ['bookings' => 0, 'views' => 0, 'adjustment' => -10]
        ];
        
        $base_price = get_post_meta($product_id, '_regular_price', true) ?: 0;
        
        foreach ($thresholds as $level => $threshold) {
            if ($bookings_count >= $threshold['bookings'] || $views_count >= $threshold['views']) {
                return $base_price * ($threshold['adjustment'] / 100);
            }
        }
        
        return 0;
    }
    
    /**
     * Calculate seasonal pricing
     *
     * @param string $date Event date
     * @param float $base_price Base price
     * @return float Price adjustment
     */
    private function calculate_seasonal_pricing($date, $base_price) {
        $seasonal_rules = get_option('wcefp_seasonal_pricing_rules', []);
        
        if (empty($seasonal_rules)) {
            return 0;
        }
        
        $event_date = new \DateTime($date);
        $month = intval($event_date->format('n'));
        $day_of_year = intval($event_date->format('z'));
        
        foreach ($seasonal_rules as $rule) {
            $matches = false;
            
            // Check month-based rules
            if (!empty($rule['months']) && in_array($month, $rule['months'])) {
                $matches = true;
            }
            
            // Check date range rules
            if (!empty($rule['date_range'])) {
                $start_day = intval(date('z', strtotime($rule['date_range']['start'])));
                $end_day = intval(date('z', strtotime($rule['date_range']['end'])));
                
                if ($day_of_year >= $start_day && $day_of_year <= $end_day) {
                    $matches = true;
                }
            }
            
            // Check special dates
            if (!empty($rule['special_dates']) && in_array($event_date->format('Y-m-d'), $rule['special_dates'])) {
                $matches = true;
            }
            
            if ($matches) {
                return $base_price * ($rule['adjustment'] / 100);
            }
        }
        
        return 0;
    }
    
    /**
     * Calculate group discount
     *
     * @param int $participants Number of participants
     * @param float $price Current price
     * @return float Discount amount
     */
    private function calculate_group_discount($participants, $price) {
        $group_tiers = get_option('wcefp_group_discount_tiers', [
            5 => 5,   // 5-9 people: 5% discount
            10 => 10, // 10-19 people: 10% discount
            20 => 15, // 20+ people: 15% discount
        ]);
        
        $discount_percentage = 0;
        
        // Find the highest applicable discount
        foreach ($group_tiers as $min_size => $discount) {
            if ($participants >= $min_size) {
                $discount_percentage = max($discount_percentage, $discount);
            }
        }
        
        return $price * ($discount_percentage / 100);
    }
    
    /**
     * Calculate early bird discount
     *
     * @param string $event_date Event date
     * @param float $price Current price
     * @return float Discount amount
     */
    private function calculate_early_bird_discount($event_date, $price) {
        $early_bird_settings = get_option('wcefp_early_bird_settings', [
            'enabled' => false,
            'days_before' => 30,
            'discount_percentage' => 15
        ]);
        
        if (!$early_bird_settings['enabled']) {
            return 0;
        }
        
        $event_datetime = new \DateTime($event_date);
        $now = new \DateTime();
        $days_until_event = $now->diff($event_datetime)->days;
        
        if ($days_until_event >= $early_bird_settings['days_before']) {
            return $price * ($early_bird_settings['discount_percentage'] / 100);
        }
        
        return 0;
    }
    
    /**
     * Calculate last-minute pricing
     *
     * @param string $event_date Event date
     * @param float $price Current price
     * @return float Price adjustment
     */
    private function calculate_last_minute_pricing($event_date, $price) {
        $last_minute_settings = get_option('wcefp_last_minute_settings', [
            'enabled' => false,
            'hours_before' => 48,
            'adjustment_percentage' => -20 // Discount for last-minute bookings
        ]);
        
        if (!$last_minute_settings['enabled']) {
            return 0;
        }
        
        $event_datetime = new \DateTime($event_date);
        $now = new \DateTime();
        $hours_until_event = ($event_datetime->getTimestamp() - $now->getTimestamp()) / 3600;
        
        if ($hours_until_event <= $last_minute_settings['hours_before']) {
            return $price * ($last_minute_settings['adjustment_percentage'] / 100);
        }
        
        return 0;
    }
    
    /**
     * Calculate loyalty discount
     *
     * @param int $customer_id Customer ID
     * @param float $price Current price
     * @return float Discount amount
     */
    private function calculate_loyalty_discount($customer_id, $price) {
        if (!$customer_id) {
            return 0;
        }
        
        // Get customer's booking history
        $previous_bookings = $this->get_customer_bookings_count($customer_id);
        
        $loyalty_tiers = get_option('wcefp_loyalty_tiers', [
            3 => 5,   // 3+ bookings: 5% discount
            5 => 8,   // 5+ bookings: 8% discount
            10 => 12, // 10+ bookings: 12% discount
        ]);
        
        $discount_percentage = 0;
        
        foreach ($loyalty_tiers as $min_bookings => $discount) {
            if ($previous_bookings >= $min_bookings) {
                $discount_percentage = max($discount_percentage, $discount);
            }
        }
        
        return $price * ($discount_percentage / 100);
    }
    
    /**
     * Calculate capacity-based pricing
     *
     * @param int $product_id Product ID
     * @param array $context Pricing context
     * @return float Price adjustment
     */
    private function calculate_capacity_pricing($product_id, $context) {
        if (empty($context['date'])) {
            return 0;
        }
        
        // Get event capacity and current bookings
        $max_capacity = get_post_meta($product_id, '_max_capacity', true) ?: 50;
        $current_bookings = $this->get_date_bookings_count($product_id, $context['date']);
        
        $utilization_percentage = ($current_bookings / $max_capacity) * 100;
        
        $capacity_rules = get_post_meta($product_id, '_wcefp_capacity_pricing', true) ?: [
            90 => 20, // 90%+ capacity: +20% price
            75 => 10, // 75%+ capacity: +10% price
            50 => 5,  // 50%+ capacity: +5% price
        ];
        
        $base_price = get_post_meta($product_id, '_regular_price', true) ?: 0;
        
        foreach ($capacity_rules as $threshold => $adjustment) {
            if ($utilization_percentage >= $threshold) {
                return $base_price * ($adjustment / 100);
            }
        }
        
        return 0;
    }
    
    /**
     * Get pricing context from various sources
     *
     * @return array|false Pricing context or false
     */
    private function get_pricing_context() {
        $context = [];
        
        // Check session data
        $session = WC()->session;
        if ($session) {
            $context['customer_id'] = get_current_user_id();
            $context['session_data'] = $session->get_session_data();
        }
        
        // Check POST data for AJAX requests
        if (!empty($_POST['wcefp_event_date'])) {
            $context['date'] = sanitize_text_field($_POST['wcefp_event_date']);
        }
        
        if (!empty($_POST['wcefp_participants'])) {
            $context['participants'] = absint($_POST['wcefp_participants']);
        }
        
        if (!empty($_POST['wcefp_product_id'])) {
            $context['product_id'] = absint($_POST['wcefp_product_id']);
        }
        
        return !empty($context) ? $context : false;
    }
    
    /**
     * Get recent bookings count for demand calculation
     *
     * @param int $product_id Product ID
     * @param int $days Number of days to look back
     * @return int Bookings count
     */
    private function get_recent_bookings_count($product_id, $days) {
        global $wpdb;
        
        $start_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'booking'
            AND p.post_status = 'publish'
            AND p.post_date >= %s
            AND pm.meta_key = '_product_id'
            AND pm.meta_value = %d
        ", $start_date, $product_id)));
    }
    
    /**
     * Get recent views count for demand calculation
     *
     * @param int $product_id Product ID
     * @param int $days Number of days to look back
     * @return int Views count
     */
    private function get_recent_views_count($product_id, $days) {
        // This would integrate with analytics tracking
        // For now, return a placeholder based on post views
        return intval(get_post_meta($product_id, '_wcefp_recent_views', true) ?: 0);
    }
    
    /**
     * Get customer bookings count
     *
     * @param int $customer_id Customer ID
     * @return int Bookings count
     */
    private function get_customer_bookings_count($customer_id) {
        global $wpdb;
        
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'booking'
            AND p.post_status = 'publish'
            AND p.post_author = %d
        ", $customer_id)));
    }
    
    /**
     * Get bookings count for specific date
     *
     * @param int $product_id Product ID
     * @param string $date Event date
     * @return int Bookings count
     */
    private function get_date_bookings_count($product_id, $date) {
        global $wpdb;
        
        $event_date = date('Y-m-d', strtotime($date));
        
        return intval($wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_product_id'
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_event_date_time'
            WHERE p.post_type = 'booking'
            AND p.post_status = 'publish'
            AND pm1.meta_value = %d
            AND DATE(pm2.meta_value) = %s
        ", $product_id, $event_date)));
    }
    
    /**
     * Check if product is an event product
     *
     * @param int $product_id Product ID
     * @return bool True if event product
     */
    private function is_event_product($product_id) {
        return get_post_meta($product_id, '_is_wcefp_event', true) === 'yes';
    }
    
    /**
     * Add pricing options to product admin
     */
    public function add_pricing_options() {
        global $post;
        
        if (!$this->is_event_product($post->ID)) {
            return;
        }
        
        echo '<div class="options_group">';
        echo '<h3>' . __('Advanced Pricing Settings', 'wceventsfp') . '</h3>';
        
        // Dynamic pricing toggle
        woocommerce_wp_checkbox([
            'id' => '_wcefp_dynamic_pricing_enabled',
            'label' => __('Enable Dynamic Pricing', 'wceventsfp'),
            'description' => __('Enable automatic price adjustments based on demand, capacity, etc.', 'wceventsfp')
        ]);
        
        // Min/Max prices
        woocommerce_wp_text_input([
            'id' => '_wcefp_min_price',
            'label' => __('Minimum Price', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => [
                'step' => '0.01',
                'min' => '0'
            ]
        ]);
        
        woocommerce_wp_text_input([
            'id' => '_wcefp_max_price',
            'label' => __('Maximum Price', 'wceventsfp'),
            'type' => 'number',
            'custom_attributes' => [
                'step' => '0.01',
                'min' => '0'
            ]
        ]);
        
        echo '</div>';
    }
    
    /**
     * Save pricing options
     */
    public function save_pricing_options($post_id) {
        $fields = [
            '_wcefp_dynamic_pricing_enabled',
            '_wcefp_min_price',
            '_wcefp_max_price'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * AJAX handler for price calculation
     */
    public function calculate_price_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcefp_pricing_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed', 'wceventsfp')]);
        }
        
        $product_id = absint($_POST['product_id']);
        $context = [
            'date' => sanitize_text_field($_POST['date'] ?? ''),
            'participants' => absint($_POST['participants'] ?? 1),
            'customer_id' => get_current_user_id()
        ];
        
        if (!$product_id) {
            wp_send_json_error(['message' => __('Product ID is required', 'wceventsfp')]);
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => __('Product not found', 'wceventsfp')]);
        }
        
        $base_price = $product->get_regular_price();
        $dynamic_price = $this->calculate_dynamic_price($base_price, $product_id, $context);
        
        $pricing_breakdown = [
            'base_price' => $base_price,
            'final_price' => $dynamic_price,
            'total_adjustment' => $dynamic_price - $base_price,
            'adjustments' => $this->get_pricing_breakdown($base_price, $product_id, $context)
        ];
        
        wp_send_json_success($pricing_breakdown);
    }
    
    /**
     * Get detailed pricing breakdown
     *
     * @param float $base_price Base price
     * @param int $product_id Product ID
     * @param array $context Pricing context
     * @return array Pricing breakdown
     */
    private function get_pricing_breakdown($base_price, $product_id, $context) {
        $breakdown = [];
        
        // Demand pricing
        $demand_adjustment = $this->calculate_demand_pricing($product_id, $context);
        if ($demand_adjustment != 0) {
            $breakdown[] = [
                'type' => 'demand',
                'label' => __('Demand Adjustment', 'wceventsfp'),
                'amount' => $demand_adjustment,
                'description' => $demand_adjustment > 0 ? __('High demand pricing', 'wceventsfp') : __('Low demand discount', 'wceventsfp')
            ];
        }
        
        // Seasonal pricing
        if (!empty($context['date'])) {
            $seasonal_adjustment = $this->calculate_seasonal_pricing($context['date'], $base_price);
            if ($seasonal_adjustment != 0) {
                $breakdown[] = [
                    'type' => 'seasonal',
                    'label' => __('Seasonal Pricing', 'wceventsfp'),
                    'amount' => $seasonal_adjustment,
                    'description' => $seasonal_adjustment > 0 ? __('Peak season surcharge', 'wceventsfp') : __('Off-season discount', 'wceventsfp')
                ];
            }
        }
        
        // Group discount
        if (!empty($context['participants']) && $context['participants'] > 1) {
            $group_discount = $this->calculate_group_discount($context['participants'], $base_price + $demand_adjustment + ($seasonal_adjustment ?? 0));
            if ($group_discount > 0) {
                $breakdown[] = [
                    'type' => 'group',
                    'label' => __('Group Discount', 'wceventsfp'),
                    'amount' => -$group_discount,
                    'description' => sprintf(__('%d participants group discount', 'wceventsfp'), $context['participants'])
                ];
            }
        }
        
        return $breakdown;
    }
    
    /**
     * Render pricing calculator shortcode
     */
    public function render_pricing_calculator($atts) {
        $atts = shortcode_atts([
            'product_id' => 0,
            'show_breakdown' => 'yes',
            'theme' => 'default'
        ], $atts);
        
        if (!$atts['product_id']) {
            return '<p>' . __('Product ID is required', 'wceventsfp') . '</p>';
        }
        
        // Enqueue assets
        wp_enqueue_script('wcefp-pricing-calculator', 
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/pricing-calculator.js', 
            ['jquery'], '2.2.0', true
        );
        wp_enqueue_style('wcefp-pricing-calculator', 
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/pricing-calculator.css', 
            [], '2.2.0'
        );
        
        // Localize script
        wp_localize_script('wcefp-pricing-calculator', 'wcefp_pricing', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_pricing_nonce'),
            'product_id' => $atts['product_id'],
            'strings' => [
                'calculating' => __('Calculating price...', 'wceventsfp'),
                'error' => __('Error calculating price', 'wceventsfp'),
                'base_price' => __('Base Price', 'wceventsfp'),
                'final_price' => __('Final Price', 'wceventsfp'),
                'savings' => __('You Save', 'wceventsfp'),
                'surcharge' => __('Additional Charge', 'wceventsfp')
            ]
        ]);
        
        ob_start();
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/pricing-calculator.php';
        return ob_get_clean();
    }
}