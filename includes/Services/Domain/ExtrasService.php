<?php
/**
 * Extras Service
 * 
 * @package WCEFP
 * @subpackage Services\Domain
 * @since 2.2.0
 */

namespace WCEFP\Services\Domain;

use WCEFP\Core\SecurityManager;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Extra services and add-ons management service
 */
class ExtrasService {
    
    /**
     * Pricing types for extras
     */
    const PRICING_FIXED = 'fixed';
    const PRICING_PER_PERSON = 'per_person';
    const PRICING_PER_ADULT = 'per_adult';
    const PRICING_PER_CHILD = 'per_child';
    const PRICING_PER_ORDER = 'per_order';
    
    /**
     * Get available extras for a product
     * 
     * @param int $product_id Product ID
     * @param array $context Booking context (date, tickets, etc.)
     * @return array Available extras
     */
    public function get_available_extras($product_id, $context = []) {
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
            return [];
        }
        
        // Get product-specific extras
        $product_extras = $this->get_product_extras($product_id);
        
        // Get reusable extras from CPT if available
        $reusable_extras = $this->get_reusable_extras($product_id);
        
        // Combine and filter extras
        $all_extras = array_merge($product_extras, $reusable_extras);
        
        // Apply context-based filtering and pricing
        $filtered_extras = [];
        foreach ($all_extras as $extra) {
            if ($this->is_extra_available($extra, $context)) {
                $extra = $this->apply_dynamic_pricing($extra, $context);
                $filtered_extras[] = $extra;
            }
        }
        
        return apply_filters('wcefp_available_extras', $filtered_extras, $product_id, $context);
    }
    
    /**
     * Get product-specific extras
     * 
     * @param int $product_id Product ID
     * @return array Product extras
     */
    private function get_product_extras($product_id) {
        $product = wc_get_product($product_id);
        $extras = $product->get_meta('_wcefp_product_extras', true);
        
        if (empty($extras) || !is_array($extras)) {
            return [];
        }
        
        // Add source information
        foreach ($extras as &$extra) {
            $extra['source'] = 'product';
            $extra['product_id'] = $product_id;
        }
        
        return $extras;
    }
    
    /**
     * Get reusable extras from CPT
     * 
     * @param int $product_id Product ID
     * @return array Reusable extras
     */
    private function get_reusable_extras($product_id) {
        // Check if the Extras CPT class exists
        if (!class_exists('WCEFP\\Modules\\ExtrasModule')) {
            return [];
        }
        
        // Get linked extras for this product
        $linked_extras = get_post_meta($product_id, '_wcefp_linked_extras', true) ?: [];
        
        if (empty($linked_extras)) {
            return [];
        }
        
        $reusable_extras = [];
        foreach ($linked_extras as $extra_id) {
            $extra_post = get_post($extra_id);
            if (!$extra_post || $extra_post->post_status !== 'publish') {
                continue;
            }
            
            $extra_data = [
                'id' => $extra_id,
                'source' => 'reusable',
                'title' => $extra_post->post_title,
                'description' => $extra_post->post_content,
                'price' => (float) get_post_meta($extra_id, '_wcefp_extra_price', true),
                'pricing_type' => get_post_meta($extra_id, '_wcefp_extra_pricing_type', true) ?: self::PRICING_FIXED,
                'max_quantity' => (int) get_post_meta($extra_id, '_wcefp_extra_max_quantity', true) ?: 10,
                'is_required' => !empty(get_post_meta($extra_id, '_wcefp_extra_required', true)),
                'stock_quantity' => (int) get_post_meta($extra_id, '_wcefp_extra_stock', true),
                'stock_enabled' => !empty(get_post_meta($extra_id, '_wcefp_extra_manage_stock', true)),
                'category' => get_post_meta($extra_id, '_wcefp_extra_category', true) ?: 'general',
                'conditions' => get_post_meta($extra_id, '_wcefp_extra_conditions', true) ?: []
            ];
            
            $reusable_extras[] = $extra_data;
        }
        
        return $reusable_extras;
    }
    
    /**
     * Check if an extra is available based on context
     * 
     * @param array $extra Extra configuration
     * @param array $context Booking context
     * @return bool Availability
     */
    private function is_extra_available($extra, $context) {
        // Check stock if enabled
        if (!empty($extra['stock_enabled']) && isset($extra['stock_quantity'])) {
            if ($extra['stock_quantity'] <= 0) {
                return false;
            }
        }
        
        // Check date-based conditions
        if (!empty($extra['conditions']['available_dates'])) {
            $booking_date = $context['date'] ?? null;
            if ($booking_date && !$this->is_date_in_range($booking_date, $extra['conditions']['available_dates'])) {
                return false;
            }
        }
        
        // Check ticket-based conditions
        if (!empty($extra['conditions']['required_tickets'])) {
            $selected_tickets = $context['tickets'] ?? [];
            if (!$this->has_required_tickets($selected_tickets, $extra['conditions']['required_tickets'])) {
                return false;
            }
        }
        
        // Check minimum participants condition
        if (!empty($extra['conditions']['min_participants'])) {
            $total_participants = array_sum($context['tickets'] ?? []);
            if ($total_participants < $extra['conditions']['min_participants']) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Apply dynamic pricing to an extra
     * 
     * @param array $extra Extra configuration
     * @param array $context Booking context
     * @return array Extra with dynamic pricing applied
     */
    private function apply_dynamic_pricing($extra, $context) {
        $base_price = (float) $extra['price'];
        $pricing_type = $extra['pricing_type'] ?? self::PRICING_FIXED;
        
        // Apply seasonal adjustments
        if (!empty($context['date'])) {
            $seasonal_adjustment = $this->get_seasonal_extra_adjustment($extra['id'] ?? null, $context['date']);
            $base_price += $seasonal_adjustment;
        }
        
        // Apply quantity-based discounts
        $quantity = $context['extra_quantities'][$extra['id']] ?? 1;
        if ($quantity > 1) {
            $bulk_discount = $this->get_bulk_discount($extra, $quantity);
            $base_price -= $bulk_discount;
        }
        
        $extra['calculated_price'] = max(0, $base_price);
        $extra['pricing_breakdown'] = [
            'base_price' => (float) $extra['price'],
            'seasonal_adjustment' => $seasonal_adjustment ?? 0,
            'bulk_discount' => $bulk_discount ?? 0,
            'final_price' => $extra['calculated_price']
        ];
        
        return $extra;
    }
    
    /**
     * Get seasonal adjustment for an extra
     * 
     * @param int|null $extra_id Extra ID
     * @param string $date Booking date
     * @return float Adjustment amount
     */
    private function get_seasonal_extra_adjustment($extra_id, $date) {
        // Basic seasonal pricing - can be extended with more complex rules
        $month = (int) date('n', strtotime($date));
        
        $seasonal_multipliers = [
            12 => 1.2, // December +20%
            1 => 1.15,  // January +15%
            7 => 1.1,   // July +10%
            8 => 1.1    // August +10%
        ];
        
        $multiplier = $seasonal_multipliers[$month] ?? 1.0;
        
        if ($extra_id) {
            $base_price = (float) get_post_meta($extra_id, '_wcefp_extra_price', true);
        } else {
            $base_price = 0;
        }
        
        return ($multiplier - 1.0) * $base_price;
    }
    
    /**
     * Get bulk discount for an extra
     * 
     * @param array $extra Extra configuration
     * @param int $quantity Quantity
     * @return float Discount amount
     */
    private function get_bulk_discount($extra, $quantity) {
        $base_price = (float) $extra['price'];
        
        // Standard bulk discount tiers
        if ($quantity >= 10) {
            return $base_price * 0.15; // 15% discount
        } elseif ($quantity >= 5) {
            return $base_price * 0.10; // 10% discount
        } elseif ($quantity >= 3) {
            return $base_price * 0.05; // 5% discount
        }
        
        return 0;
    }
    
    /**
     * Calculate extra prices for selected extras
     * 
     * @param int $product_id Product ID
     * @param array $selected_extras Array of extra_id => quantity
     * @param array $context Booking context
     * @return array Price calculation
     */
    public function calculate_extras_price($product_id, $selected_extras, $context = []) {
        if (empty($selected_extras)) {
            return [
                'subtotal' => 0,
                'total' => 0,
                'extras' => [],
                'discounts' => []
            ];
        }
        
        $available_extras = $this->get_available_extras($product_id, array_merge($context, [
            'extra_quantities' => $selected_extras
        ]));
        
        $calculation = [
            'subtotal' => 0,
            'total' => 0,
            'extras' => [],
            'discounts' => []
        ];
        
        foreach ($selected_extras as $extra_id => $quantity) {
            if ($quantity <= 0) continue;
            
            $extra = $this->find_extra_by_id($available_extras, $extra_id);
            if (!$extra) continue;
            
            $unit_price = $this->calculate_unit_price($extra, $context);
            $line_total = $unit_price * $quantity;
            
            $calculation['extras'][] = [
                'id' => $extra_id,
                'title' => $extra['title'] ?? $extra['name'] ?? '',
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'line_total' => $line_total,
                'pricing_type' => $extra['pricing_type'] ?? self::PRICING_FIXED
            ];
            
            $calculation['subtotal'] += $line_total;
        }
        
        // Apply combo discounts if multiple extras are selected
        $combo_discount = $this->calculate_combo_discount($selected_extras, $calculation['subtotal']);
        if ($combo_discount > 0) {
            $calculation['discounts'][] = [
                'type' => 'combo',
                'label' => __('Multi-extra combo discount', 'wceventsfp'),
                'amount' => -$combo_discount
            ];
            $calculation['subtotal'] -= $combo_discount;
        }
        
        $calculation['total'] = max(0, $calculation['subtotal']);
        
        return apply_filters('wcefp_extras_price_calculated', $calculation, $product_id, $selected_extras, $context);
    }
    
    /**
     * Calculate unit price for an extra based on pricing type
     * 
     * @param array $extra Extra configuration
     * @param array $context Booking context
     * @return float Unit price
     */
    private function calculate_unit_price($extra, $context) {
        $base_price = $extra['calculated_price'] ?? $extra['price'];
        $pricing_type = $extra['pricing_type'] ?? self::PRICING_FIXED;
        
        switch ($pricing_type) {
            case self::PRICING_PER_PERSON:
                $total_participants = array_sum($context['tickets'] ?? []);
                return $base_price * $total_participants;
                
            case self::PRICING_PER_ADULT:
                $adults = $context['tickets']['adult'] ?? 0;
                return $base_price * $adults;
                
            case self::PRICING_PER_CHILD:
                $children = $context['tickets']['child'] ?? 0;
                return $base_price * $children;
                
            case self::PRICING_PER_ORDER:
                return $base_price; // Same as fixed for unit calculation
                
            case self::PRICING_FIXED:
            default:
                return $base_price;
        }
    }
    
    /**
     * Calculate combo discount for multiple extras
     * 
     * @param array $selected_extras Selected extras
     * @param float $subtotal Current subtotal
     * @return float Discount amount
     */
    private function calculate_combo_discount($selected_extras, $subtotal) {
        $extra_count = count(array_filter($selected_extras, function($qty) {
            return $qty > 0;
        }));
        
        if ($extra_count >= 3) {
            return $subtotal * 0.10; // 10% discount for 3+ extras
        } elseif ($extra_count >= 2) {
            return $subtotal * 0.05; // 5% discount for 2 extras
        }
        
        return 0;
    }
    
    /**
     * Find extra by ID from available extras list
     * 
     * @param array $available_extras Available extras
     * @param string $extra_id Extra ID
     * @return array|null Extra configuration
     */
    private function find_extra_by_id($available_extras, $extra_id) {
        foreach ($available_extras as $extra) {
            if (($extra['id'] ?? '') == $extra_id) {
                return $extra;
            }
        }
        return null;
    }
    
    /**
     * Validate extra selection
     * 
     * @param int $product_id Product ID
     * @param array $selected_extras Selected extras
     * @param array $context Booking context
     * @return array Validation result
     */
    public function validate_extras_selection($product_id, $selected_extras, $context = []) {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        if (empty($selected_extras)) {
            // Check if any extras are required
            $available_extras = $this->get_available_extras($product_id, $context);
            $required_extras = array_filter($available_extras, function($extra) {
                return !empty($extra['is_required']);
            });
            
            if (!empty($required_extras)) {
                $validation['valid'] = false;
                foreach ($required_extras as $extra) {
                    $validation['errors'][] = sprintf(
                        __('The extra "%s" is required for this booking.', 'wceventsfp'),
                        $extra['title'] ?? $extra['name'] ?? ''
                    );
                }
            }
            
            return $validation;
        }
        
        $available_extras = $this->get_available_extras($product_id, $context);
        
        foreach ($selected_extras as $extra_id => $quantity) {
            if ($quantity <= 0) continue;
            
            $extra = $this->find_extra_by_id($available_extras, $extra_id);
            if (!$extra) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(__('Invalid extra selected: %s', 'wceventsfp'), $extra_id);
                continue;
            }
            
            // Check quantity limits
            $max_quantity = $extra['max_quantity'] ?? 10;
            if ($quantity > $max_quantity) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(
                    __('Maximum quantity for "%s" is %d.', 'wceventsfp'),
                    $extra['title'] ?? $extra['name'] ?? '',
                    $max_quantity
                );
            }
            
            // Check stock if enabled
            if (!empty($extra['stock_enabled']) && isset($extra['stock_quantity'])) {
                if ($quantity > $extra['stock_quantity']) {
                    $validation['valid'] = false;
                    $validation['errors'][] = sprintf(
                        __('Only %d units of "%s" are available.', 'wceventsfp'),
                        $extra['stock_quantity'],
                        $extra['title'] ?? $extra['name'] ?? ''
                    );
                }
            }
        }
        
        return apply_filters('wcefp_extras_selection_validated', $validation, $product_id, $selected_extras, $context);
    }
    
    /**
     * Reserve stock for selected extras
     * 
     * @param int $product_id Product ID
     * @param array $selected_extras Selected extras
     * @param int $order_id Order ID
     * @return bool Success
     */
    public function reserve_extras_stock($product_id, $selected_extras, $order_id) {
        if (empty($selected_extras)) {
            return true;
        }
        
        $success = true;
        
        foreach ($selected_extras as $extra_id => $quantity) {
            if ($quantity <= 0) continue;
            
            // Only reserve stock for reusable extras that have stock management enabled
            $extra_post = get_post($extra_id);
            if (!$extra_post || get_post_type($extra_id) !== 'wcefp_extra') {
                continue;
            }
            
            $manage_stock = get_post_meta($extra_id, '_wcefp_extra_manage_stock', true);
            if (!$manage_stock) {
                continue;
            }
            
            $current_stock = (int) get_post_meta($extra_id, '_wcefp_extra_stock', true);
            $new_stock = max(0, $current_stock - $quantity);
            
            update_post_meta($extra_id, '_wcefp_extra_stock', $new_stock);
            
            // Log the stock reservation
            Logger::info("Extra stock reserved: Extra {$extra_id}, Quantity {$quantity}, Order {$order_id}");
            
            // Create reservation record for tracking
            $this->create_stock_reservation($extra_id, $quantity, $order_id);
        }
        
        do_action('wcefp_extras_stock_reserved', $product_id, $selected_extras, $order_id);
        
        return $success;
    }
    
    /**
     * Create stock reservation record
     * 
     * @param int $extra_id Extra ID
     * @param int $quantity Quantity reserved
     * @param int $order_id Order ID
     * @return void
     */
    private function create_stock_reservation($extra_id, $quantity, $order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_extra_reservations';
        
        $wpdb->insert(
            $table_name,
            [
                'extra_id' => $extra_id,
                'order_id' => $order_id,
                'quantity' => $quantity,
                'status' => 'reserved',
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', time() + (15 * MINUTE_IN_SECONDS))
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Check if a date is in the specified range
     * 
     * @param string $date Date to check
     * @param array $date_range Date range configuration
     * @return bool Whether date is in range
     */
    private function is_date_in_range($date, $date_range) {
        $check_date = strtotime($date);
        
        if (isset($date_range['start']) && isset($date_range['end'])) {
            $start_date = strtotime($date_range['start']);
            $end_date = strtotime($date_range['end']);
            
            return $check_date >= $start_date && $check_date <= $end_date;
        }
        
        return true;
    }
    
    /**
     * Check if required tickets are present
     * 
     * @param array $selected_tickets Selected tickets
     * @param array $required_tickets Required ticket configuration
     * @return bool Whether requirements are met
     */
    private function has_required_tickets($selected_tickets, $required_tickets) {
        foreach ($required_tickets as $ticket_type => $min_quantity) {
            $selected_quantity = $selected_tickets[$ticket_type] ?? 0;
            if ($selected_quantity < $min_quantity) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Create database table for extra reservations
     * 
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_extra_reservations';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            extra_id bigint(20) UNSIGNED NOT NULL,
            order_id bigint(20) UNSIGNED NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            status varchar(20) NOT NULL DEFAULT 'reserved',
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY extra_id (extra_id),
            KEY order_id (order_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Clean up expired extra reservations
     * 
     * @return int Number of cleaned up reservations
     */
    public function cleanup_expired_reservations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_extra_reservations';
        
        // Get expired reservations to restore stock
        $expired_reservations = $wpdb->get_results("
            SELECT extra_id, quantity
            FROM {$table_name}
            WHERE status = 'reserved'
            AND expires_at < NOW()
        ");
        
        // Restore stock for expired reservations
        foreach ($expired_reservations as $reservation) {
            $current_stock = (int) get_post_meta($reservation->extra_id, '_wcefp_extra_stock', true);
            $new_stock = $current_stock + $reservation->quantity;
            update_post_meta($reservation->extra_id, '_wcefp_extra_stock', $new_stock);
        }
        
        // Remove expired reservations
        $result = $wpdb->query("
            DELETE FROM {$table_name}
            WHERE status = 'reserved'
            AND expires_at < NOW()
        ");
        
        if ($result) {
            Logger::info("Cleaned up {$result} expired extra reservations");
        }
        
        return (int) $result;
    }
}