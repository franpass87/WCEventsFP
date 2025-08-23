<?php
/**
 * Multi-Event Booking Manager
 * 
 * Handles booking multiple events in a single transaction with cart functionality
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage BookingFeatures
 * @since 2.2.0
 */

namespace WCEFP\BookingFeatures;

use WCEFP\Core\Database\BookingRepository;

class MultiEventBookingManager {
    
    private $booking_repository;
    
    public function __construct() {
        $this->booking_repository = new BookingRepository();
        
        // WooCommerce integration hooks
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_action('woocommerce_before_calculate_totals', [$this, 'calculate_cart_totals'], 10, 1);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
        add_action('woocommerce_thankyou', [$this, 'process_multi_event_booking'], 10, 1);
        
        // AJAX handlers
        add_action('wp_ajax_wcefp_add_event_to_cart', [$this, 'add_event_to_cart_ajax']);
        add_action('wp_ajax_nopriv_wcefp_add_event_to_cart', [$this, 'add_event_to_cart_ajax']);
        add_action('wp_ajax_wcefp_get_cart_summary', [$this, 'get_cart_summary_ajax']);
        add_action('wp_ajax_nopriv_wcefp_get_cart_summary', [$this, 'get_cart_summary_ajax']);
        
        // Register shortcodes
        add_shortcode('wcefp_multi_event_cart', [$this, 'render_multi_event_cart']);
        add_shortcode('wcefp_event_booking_form', [$this, 'render_event_booking_form']);
    }
    
    /**
     * Add event-specific data to cart item
     *
     * @param array $cart_item_data Cart item data
     * @param int $product_id Product ID
     * @param int $variation_id Variation ID
     * @return array Modified cart item data
     */
    public function add_cart_item_data($cart_item_data, $product_id, $variation_id) {
        // Check if this is an event product
        if (!$this->is_event_product($product_id)) {
            return $cart_item_data;
        }
        
        $event_data = $this->get_event_booking_data_from_request();
        
        if ($event_data) {
            $cart_item_data['wcefp_event_data'] = $event_data;
            $cart_item_data['wcefp_is_multi_event'] = true;
            
            // Add unique identifier to prevent merging identical events with different dates
            $cart_item_data['wcefp_unique_key'] = md5(serialize($event_data));
        }
        
        return $cart_item_data;
    }
    
    /**
     * Calculate cart totals for multi-event bookings
     *
     * @param WC_Cart $cart Cart object
     */
    public function calculate_cart_totals($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        $multi_event_items = [];
        $total_discount = 0;
        
        // Collect multi-event items
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcefp_is_multi_event']) && $cart_item['wcefp_is_multi_event']) {
                $multi_event_items[$cart_item_key] = $cart_item;
            }
        }
        
        // Apply multi-event discounts
        if (count($multi_event_items) > 1) {
            $total_discount = $this->calculate_multi_event_discount($multi_event_items);
            
            if ($total_discount > 0) {
                // Apply discount proportionally to each item
                foreach ($multi_event_items as $cart_item_key => $cart_item) {
                    $item_discount = ($cart_item['data']->get_price() / $this->get_total_base_price($multi_event_items)) * $total_discount;
                    $new_price = max(0, $cart_item['data']->get_price() - $item_discount);
                    $cart_item['data']->set_price($new_price);
                }
            }
        }
        
        // Apply dynamic pricing for each event
        foreach ($multi_event_items as $cart_item_key => $cart_item) {
            $dynamic_price = $this->calculate_dynamic_price($cart_item);
            if ($dynamic_price !== false) {
                $cart_item['data']->set_price($dynamic_price);
            }
        }
    }
    
    /**
     * Add order item meta for multi-event bookings
     *
     * @param WC_Order_Item_Product $item Order item
     * @param string $cart_item_key Cart item key
     * @param array $values Cart item values
     * @param WC_Order $order Order object
     */
    public function add_order_item_meta($item, $cart_item_key, $values, $order) {
        if (isset($values['wcefp_event_data'])) {
            $event_data = $values['wcefp_event_data'];
            
            // Add event-specific meta data
            foreach ($event_data as $key => $value) {
                $item->add_meta_data('_wcefp_' . $key, $value);
            }
            
            // Mark as multi-event booking
            $item->add_meta_data('_wcefp_is_multi_event', true);
            $item->add_meta_data('_wcefp_booking_type', 'multi_event');
        }
    }
    
    /**
     * Process multi-event booking after order completion
     *
     * @param int $order_id Order ID
     */
    public function process_multi_event_booking($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $multi_event_items = [];
        
        // Collect multi-event items from order
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_meta('_wcefp_is_multi_event')) {
                $multi_event_items[] = [
                    'item_id' => $item_id,
                    'item' => $item,
                    'event_id' => $item->get_meta('_wcefp_event_id'),
                    'event_date' => $item->get_meta('_wcefp_event_date'),
                    'participants' => $item->get_meta('_wcefp_participants') ?: 1
                ];
            }
        }
        
        if (empty($multi_event_items)) {
            return;
        }
        
        // Create booking records for each event
        $booking_ids = [];
        foreach ($multi_event_items as $event_item) {
            $booking_id = $this->create_event_booking($order, $event_item);
            if ($booking_id && !is_wp_error($booking_id)) {
                $booking_ids[] = $booking_id;
            }
        }
        
        // Link bookings as part of multi-event group
        if (count($booking_ids) > 1) {
            $this->link_multi_event_bookings($booking_ids, $order_id);
        }
        
        // Send confirmation emails
        $this->send_multi_event_confirmation($order, $booking_ids);
    }
    
    /**
     * Create individual event booking
     *
     * @param WC_Order $order Order object
     * @param array $event_item Event item data
     * @return int|WP_Error Booking ID or error
     */
    private function create_event_booking($order, $event_item) {
        $booking_data = [
            'post_title' => sprintf(
                __('Booking #%s - %s', 'wceventsfp'),
                $order->get_order_number(),
                get_the_title($event_item['event_id'])
            ),
            'post_content' => '',
            'post_status' => 'publish',
            'post_type' => 'booking',
            'meta_input' => [
                '_order_id' => $order->get_id(),
                '_order_item_id' => $event_item['item_id'],
                '_event_id' => $event_item['event_id'],
                '_event_date_time' => $event_item['event_date'],
                '_participants' => $event_item['participants'],
                '_customer_email' => $order->get_billing_email(),
                '_customer_name' => $order->get_formatted_billing_full_name(),
                '_customer_phone' => $order->get_billing_phone(),
                '_booking_status' => 'confirmed',
                '_booking_reference' => $this->generate_booking_reference(),
                '_created_at' => current_time('mysql'),
                '_multi_event_group' => true
            ]
        ];
        
        $booking_id = $this->booking_repository->create_booking($booking_data);
        
        if (!is_wp_error($booking_id)) {
            // Trigger booking confirmation
            do_action('wcefp_booking_confirmed', $booking_id);
        }
        
        return $booking_id;
    }
    
    /**
     * Link multiple bookings as part of the same group
     *
     * @param array $booking_ids Booking IDs
     * @param int $order_id Order ID
     */
    private function link_multi_event_bookings($booking_ids, $order_id) {
        $group_id = 'MEG_' . $order_id . '_' . time();
        
        foreach ($booking_ids as $booking_id) {
            update_post_meta($booking_id, '_multi_event_group_id', $group_id);
            update_post_meta($booking_id, '_multi_event_booking_ids', $booking_ids);
            update_post_meta($booking_id, '_multi_event_count', count($booking_ids));
        }
    }
    
    /**
     * Calculate multi-event discount
     *
     * @param array $multi_event_items Multi-event cart items
     * @return float Discount amount
     */
    private function calculate_multi_event_discount($multi_event_items) {
        $item_count = count($multi_event_items);
        $total_price = $this->get_total_base_price($multi_event_items);
        
        // Get discount settings
        $discount_rules = get_option('wcefp_multi_event_discount_rules', [
            2 => 5,  // 2 events: 5% discount
            3 => 10, // 3 events: 10% discount
            4 => 15, // 4+ events: 15% discount
        ]);
        
        $discount_percentage = 0;
        
        // Find applicable discount
        foreach ($discount_rules as $min_events => $discount) {
            if ($item_count >= $min_events) {
                $discount_percentage = max($discount_percentage, $discount);
            }
        }
        
        return $total_price * ($discount_percentage / 100);
    }
    
    /**
     * Get total base price of multi-event items
     *
     * @param array $multi_event_items Multi-event cart items
     * @return float Total price
     */
    private function get_total_base_price($multi_event_items) {
        $total = 0;
        foreach ($multi_event_items as $item) {
            $total += $item['data']->get_regular_price() * $item['quantity'];
        }
        return $total;
    }
    
    /**
     * Calculate dynamic price for event item
     *
     * @param array $cart_item Cart item
     * @return float|false Dynamic price or false if no change
     */
    private function calculate_dynamic_price($cart_item) {
        if (!isset($cart_item['wcefp_event_data'])) {
            return false;
        }
        
        $event_data = $cart_item['wcefp_event_data'];
        $base_price = $cart_item['data']->get_regular_price();
        
        // Apply group size discounts
        $participants = intval($event_data['participants'] ?? 1);
        if ($participants > 1) {
            $group_discount = $this->calculate_group_discount($participants, $base_price);
            $base_price -= $group_discount;
        }
        
        // Apply seasonal pricing
        if (!empty($event_data['event_date'])) {
            $seasonal_adjustment = $this->calculate_seasonal_adjustment($event_data['event_date'], $base_price);
            $base_price += $seasonal_adjustment;
        }
        
        return max(0, $base_price);
    }
    
    /**
     * Calculate group discount
     *
     * @param int $participants Number of participants
     * @param float $base_price Base price
     * @return float Discount amount
     */
    private function calculate_group_discount($participants, $base_price) {
        $group_discounts = get_option('wcefp_group_discount_tiers', [
            5 => 5,   // 5+ people: 5% discount per person
            10 => 10, // 10+ people: 10% discount per person
            20 => 15  // 20+ people: 15% discount per person
        ]);
        
        $discount_percentage = 0;
        
        foreach ($group_discounts as $min_size => $discount) {
            if ($participants >= $min_size) {
                $discount_percentage = max($discount_percentage, $discount);
            }
        }
        
        return $base_price * ($discount_percentage / 100);
    }
    
    /**
     * Calculate seasonal pricing adjustment
     *
     * @param string $event_date Event date
     * @param float $base_price Base price
     * @return float Adjustment amount (positive for increase, negative for decrease)
     */
    private function calculate_seasonal_adjustment($event_date, $base_price) {
        $seasonal_rules = get_option('wcefp_seasonal_pricing_rules', [
            'peak' => ['months' => [6, 7, 8], 'adjustment' => 20], // Summer: +20%
            'low' => ['months' => [1, 2, 11, 12], 'adjustment' => -10], // Winter: -10%
        ]);
        
        $event_month = date('n', strtotime($event_date));
        $adjustment_percentage = 0;
        
        foreach ($seasonal_rules as $rule) {
            if (in_array($event_month, $rule['months'])) {
                $adjustment_percentage = $rule['adjustment'];
                break;
            }
        }
        
        return $base_price * ($adjustment_percentage / 100);
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
     * Get event booking data from request
     *
     * @return array|false Event booking data or false
     */
    private function get_event_booking_data_from_request() {
        $event_data = [];
        
        // Get data from POST request
        if (isset($_POST['wcefp_event_id'])) {
            $event_data['event_id'] = absint($_POST['wcefp_event_id']);
        }
        
        if (isset($_POST['wcefp_event_date'])) {
            $event_data['event_date'] = sanitize_text_field($_POST['wcefp_event_date']);
        }
        
        if (isset($_POST['wcefp_participants'])) {
            $event_data['participants'] = absint($_POST['wcefp_participants']);
        }
        
        if (isset($_POST['wcefp_special_requirements'])) {
            $event_data['special_requirements'] = sanitize_textarea_field($_POST['wcefp_special_requirements']);
        }
        
        return !empty($event_data) ? $event_data : false;
    }
    
    /**
     * Generate unique booking reference
     *
     * @return string Booking reference
     */
    private function generate_booking_reference() {
        return 'WCEFP-' . strtoupper(wp_generate_password(8, false));
    }
    
    /**
     * Send multi-event booking confirmation
     *
     * @param WC_Order $order Order object
     * @param array $booking_ids Booking IDs
     */
    private function send_multi_event_confirmation($order, $booking_ids) {
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_formatted_billing_full_name();
        
        $subject = sprintf(
            __('Multi-Event Booking Confirmation - %d Events', 'wceventsfp'),
            count($booking_ids)
        );
        
        $message = sprintf(
            __('Dear %s,\n\nYour multi-event booking has been confirmed! You have successfully booked %d events.\n\nBooking Details:\n', 'wceventsfp'),
            $customer_name,
            count($booking_ids)
        );
        
        foreach ($booking_ids as $booking_id) {
            $event_id = get_post_meta($booking_id, '_event_id', true);
            $event_date = get_post_meta($booking_id, '_event_date_time', true);
            $booking_ref = get_post_meta($booking_id, '_booking_reference', true);
            
            $message .= sprintf(
                "- %s (%s) - Ref: %s\n",
                get_the_title($event_id),
                date('F j, Y g:i A', strtotime($event_date)),
                $booking_ref
            );
        }
        
        $message .= __('\nThank you for your booking!', 'wceventsfp');
        
        wp_mail($customer_email, $subject, $message);
    }
    
    /**
     * AJAX handler for adding event to cart
     */
    public function add_event_to_cart_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcefp_cart_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed', 'wceventsfp')]);
        }
        
        $product_id = absint($_POST['product_id']);
        $event_id = absint($_POST['event_id']);
        $event_date = sanitize_text_field($_POST['event_date']);
        $participants = absint($_POST['participants']) ?: 1;
        
        if (!$product_id || !$event_id) {
            wp_send_json_error(['message' => __('Missing required data', 'wceventsfp')]);
        }
        
        // Add to cart with event data
        $_POST['wcefp_event_id'] = $event_id;
        $_POST['wcefp_event_date'] = $event_date;
        $_POST['wcefp_participants'] = $participants;
        
        $cart_item_key = WC()->cart->add_to_cart($product_id, $participants);
        
        if ($cart_item_key) {
            wp_send_json_success([
                'message' => __('Event added to cart', 'wceventsfp'),
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_total' => WC()->cart->get_cart_total()
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to add event to cart', 'wceventsfp')]);
        }
    }
    
    /**
     * AJAX handler for getting cart summary
     */
    public function get_cart_summary_ajax() {
        $cart_items = [];
        $multi_event_count = 0;
        $total_discount = 0;
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wcefp_is_multi_event']) && $cart_item['wcefp_is_multi_event']) {
                $multi_event_count++;
                $event_data = $cart_item['wcefp_event_data'];
                
                $cart_items[] = [
                    'key' => $cart_item_key,
                    'event_title' => get_the_title($event_data['event_id']),
                    'event_date' => date('F j, Y g:i A', strtotime($event_data['event_date'])),
                    'participants' => $event_data['participants'],
                    'price' => $cart_item['data']->get_price(),
                    'total' => $cart_item['data']->get_price() * $cart_item['quantity']
                ];
            }
        }
        
        // Calculate discount if multiple events
        if ($multi_event_count > 1) {
            $multi_event_items = array_filter(WC()->cart->get_cart(), function($item) {
                return isset($item['wcefp_is_multi_event']) && $item['wcefp_is_multi_event'];
            });
            $total_discount = $this->calculate_multi_event_discount($multi_event_items);
        }
        
        wp_send_json_success([
            'items' => $cart_items,
            'multi_event_count' => $multi_event_count,
            'total_discount' => $total_discount,
            'cart_total' => WC()->cart->get_total(),
            'discount_applicable' => $multi_event_count > 1
        ]);
    }
    
    /**
     * Render multi-event cart shortcode
     */
    public function render_multi_event_cart($atts) {
        $atts = shortcode_atts([
            'show_discount_info' => 'yes',
            'theme' => 'default'
        ], $atts);
        
        // Enqueue assets
        wp_enqueue_script('wcefp-multi-event-cart', 
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/multi-event-cart.js', 
            ['jquery'], '2.2.0', true
        );
        wp_enqueue_style('wcefp-multi-event-cart', 
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/multi-event-cart.css', 
            [], '2.2.0'
        );
        
        // Localize script
        wp_localize_script('wcefp-multi-event-cart', 'wcefp_cart', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_cart_nonce'),
            'strings' => [
                'added_to_cart' => __('Added to cart', 'wceventsfp'),
                'updating_cart' => __('Updating cart...', 'wceventsfp'),
                'error' => __('An error occurred', 'wceventsfp')
            ]
        ]);
        
        ob_start();
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/multi-event-cart.php';
        return ob_get_clean();
    }
    
    /**
     * Render event booking form shortcode
     */
    public function render_event_booking_form($atts) {
        $atts = shortcode_atts([
            'event_id' => 0,
            'product_id' => 0,
            'show_calendar' => 'yes',
            'theme' => 'default'
        ], $atts);
        
        if (!$atts['event_id'] || !$atts['product_id']) {
            return '<p>' . __('Event ID and Product ID are required', 'wceventsfp') . '</p>';
        }
        
        ob_start();
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/event-booking-form.php';
        return ob_get_clean();
    }
}