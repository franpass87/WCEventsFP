<?php
/**
 * Tickets Service
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
 * Multi-type ticketing service for events and experiences
 */
class TicketsService {
    
    /**
     * Ticket types
     */
    const TICKET_TYPE_ADULT = 'adult';
    const TICKET_TYPE_CHILD = 'child';
    const TICKET_TYPE_SENIOR = 'senior';
    const TICKET_TYPE_STUDENT = 'student';
    const TICKET_TYPE_GROUP = 'group';
    
    /**
     * Get available ticket types for a product
     * 
     * @param int $product_id Product ID
     * @return array Ticket types configuration
     */
    public function get_ticket_types($product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
            return [];
        }
        
        // Get stored ticket types or create default
        $ticket_types = $product->get_meta('_wcefp_ticket_types', true);
        
        if (empty($ticket_types) || !is_array($ticket_types)) {
            $ticket_types = $this->get_default_ticket_types($product);
        }
        
        // Apply pricing and availability filters
        foreach ($ticket_types as &$ticket_type) {
            $ticket_type = $this->apply_dynamic_pricing($ticket_type, $product_id);
            $ticket_type['available'] = $this->is_ticket_type_available($product_id, $ticket_type['type']);
        }
        
        return $ticket_types;
    }
    
    /**
     * Get default ticket types based on product configuration
     * 
     * @param \WC_Product $product Product object
     * @return array Default ticket types
     */
    private function get_default_ticket_types($product) {
        $adult_price = (float) $product->get_meta('_wcefp_price_adult', true) ?: $product->get_price();
        $child_price = (float) $product->get_meta('_wcefp_price_child', true) ?: ($adult_price * 0.7);
        
        return [
            [
                'type' => self::TICKET_TYPE_ADULT,
                'label' => __('Adult', 'wceventsfp'),
                'description' => __('Standard adult ticket', 'wceventsfp'),
                'price' => $adult_price,
                'min_quantity' => 1,
                'max_quantity' => 10,
                'age_range' => [18, 99],
                'enabled' => true,
                'available' => true
            ],
            [
                'type' => self::TICKET_TYPE_CHILD,
                'label' => __('Child', 'wceventsfp'),
                'description' => __('Reduced price for children', 'wceventsfp'),
                'price' => $child_price,
                'min_quantity' => 0,
                'max_quantity' => 8,
                'age_range' => [3, 17],
                'enabled' => true,
                'available' => true
            ]
        ];
    }
    
    /**
     * Save ticket types configuration for a product
     * 
     * @param int $product_id Product ID
     * @param array $ticket_types Ticket types configuration
     * @return bool Success
     */
    public function save_ticket_types($product_id, $ticket_types) {
        if (!SecurityManager::can_user('manage_wcefp_events')) {
            return false;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Validate and sanitize ticket types
        $sanitized_types = [];
        foreach ($ticket_types as $ticket_type) {
            $sanitized_type = $this->sanitize_ticket_type($ticket_type);
            if ($sanitized_type) {
                $sanitized_types[] = $sanitized_type;
            }
        }
        
        // Save to product meta
        $product->update_meta_data('_wcefp_ticket_types', $sanitized_types);
        $product->save();
        
        Logger::info("Ticket types updated for product {$product_id}", $sanitized_types);
        
        // Trigger action for integrations
        do_action('wcefp_ticket_types_updated', $product_id, $sanitized_types);
        
        return true;
    }
    
    /**
     * Sanitize a single ticket type configuration
     * 
     * @param array $ticket_type Raw ticket type data
     * @return array|null Sanitized ticket type or null if invalid
     */
    private function sanitize_ticket_type($ticket_type) {
        if (!is_array($ticket_type) || empty($ticket_type['type'])) {
            return null;
        }
        
        return [
            'type' => sanitize_text_field($ticket_type['type']),
            'label' => sanitize_text_field($ticket_type['label'] ?? ''),
            'description' => sanitize_textarea_field($ticket_type['description'] ?? ''),
            'price' => (float) ($ticket_type['price'] ?? 0),
            'min_quantity' => max(0, (int) ($ticket_type['min_quantity'] ?? 0)),
            'max_quantity' => max(1, (int) ($ticket_type['max_quantity'] ?? 10)),
            'age_range' => [
                max(0, (int) ($ticket_type['age_range'][0] ?? 0)),
                max(1, (int) ($ticket_type['age_range'][1] ?? 99))
            ],
            'enabled' => !empty($ticket_type['enabled']),
            'conditions' => $this->sanitize_ticket_conditions($ticket_type['conditions'] ?? [])
        ];
    }
    
    /**
     * Sanitize ticket conditions
     * 
     * @param array $conditions Raw conditions
     * @return array Sanitized conditions
     */
    private function sanitize_ticket_conditions($conditions) {
        $sanitized = [];
        
        if (isset($conditions['requires_adult'])) {
            $sanitized['requires_adult'] = !empty($conditions['requires_adult']);
        }
        
        if (isset($conditions['seasonal_pricing'])) {
            $sanitized['seasonal_pricing'] = (bool) $conditions['seasonal_pricing'];
        }
        
        if (isset($conditions['group_discount_threshold'])) {
            $sanitized['group_discount_threshold'] = max(1, (int) $conditions['group_discount_threshold']);
        }
        
        return $sanitized;
    }
    
    /**
     * Apply dynamic pricing to a ticket type
     * 
     * @param array $ticket_type Ticket type configuration
     * @param int $product_id Product ID
     * @return array Ticket type with updated pricing
     */
    private function apply_dynamic_pricing($ticket_type, $product_id) {
        $original_price = $ticket_type['price'];
        $dynamic_price = $original_price;
        
        // Apply seasonal pricing if enabled
        if (!empty($ticket_type['conditions']['seasonal_pricing'])) {
            $seasonal_adjustment = $this->get_seasonal_price_adjustment($product_id, $ticket_type['type']);
            $dynamic_price += $seasonal_adjustment;
        }
        
        // Apply demand-based pricing
        $demand_adjustment = $this->get_demand_price_adjustment($product_id, $ticket_type['type']);
        $dynamic_price += $demand_adjustment;
        
        // Never go below 50% of original price
        $min_price = $original_price * 0.5;
        $dynamic_price = max($min_price, $dynamic_price);
        
        $ticket_type['price'] = round($dynamic_price, 2);
        $ticket_type['original_price'] = $original_price;
        $ticket_type['price_adjustments'] = [
            'seasonal' => $seasonal_adjustment ?? 0,
            'demand' => $demand_adjustment ?? 0
        ];
        
        return $ticket_type;
    }
    
    /**
     * Get seasonal price adjustment for a ticket type
     * 
     * @param int $product_id Product ID
     * @param string $ticket_type Ticket type
     * @return float Price adjustment
     */
    private function get_seasonal_price_adjustment($product_id, $ticket_type) {
        // Get current date/season
        $current_month = (int) date('n');
        
        // Define seasonal pricing rules (can be customized per product)
        $seasonal_rules = [
            'high_season' => [6, 7, 8, 12], // Summer + December
            'low_season' => [1, 2, 3, 11],  // Winter months
            'peak_season' => [7, 8]         // Peak summer
        ];
        
        $adjustment = 0;
        
        if (in_array($current_month, $seasonal_rules['peak_season'])) {
            $adjustment = 20; // +20€ in peak season
        } elseif (in_array($current_month, $seasonal_rules['high_season'])) {
            $adjustment = 10; // +10€ in high season
        } elseif (in_array($current_month, $seasonal_rules['low_season'])) {
            $adjustment = -5; // -5€ in low season
        }
        
        return (float) apply_filters('wcefp_seasonal_price_adjustment', $adjustment, $product_id, $ticket_type, $current_month);
    }
    
    /**
     * Get demand-based price adjustment
     * 
     * @param int $product_id Product ID
     * @param string $ticket_type Ticket type
     * @return float Price adjustment
     */
    private function get_demand_price_adjustment($product_id, $ticket_type) {
        // Calculate demand based on recent bookings and availability
        $recent_bookings = $this->count_recent_bookings($product_id, 7); // Last 7 days
        $average_capacity = $this->get_average_capacity($product_id);
        
        if ($average_capacity <= 0) {
            return 0;
        }
        
        $demand_ratio = $recent_bookings / $average_capacity;
        $adjustment = 0;
        
        if ($demand_ratio > 0.8) {
            $adjustment = 15; // High demand: +15€
        } elseif ($demand_ratio > 0.6) {
            $adjustment = 8;  // Medium demand: +8€
        } elseif ($demand_ratio < 0.2) {
            $adjustment = -3; // Low demand: -3€
        }
        
        return (float) apply_filters('wcefp_demand_price_adjustment', $adjustment, $product_id, $ticket_type, $demand_ratio);
    }
    
    /**
     * Count recent bookings for a product
     * 
     * @param int $product_id Product ID
     * @param int $days Number of days to look back
     * @return int Number of recent bookings
     */
    private function count_recent_bookings($product_id, $days = 7) {
        global $wpdb;
        
        $date_from = date('Y-m-d', strtotime("-{$days} days"));
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(oi.order_item_id)
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->prefix}posts p ON oi.order_id = p.ID
            WHERE oi.order_item_type = 'line_item'
            AND oim.meta_key = '_product_id'
            AND oim.meta_value = %d
            AND p.post_status IN ('wc-processing', 'wc-completed')
            AND p.post_date >= %s
        ", $product_id, $date_from));
        
        return (int) $count;
    }
    
    /**
     * Get average capacity for a product
     * 
     * @param int $product_id Product ID
     * @return int Average capacity
     */
    private function get_average_capacity($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return 0;
        }
        
        $capacity = (int) $product->get_meta('_wcefp_capacity', true) ?: 10;
        $time_slots = $product->get_meta('_wcefp_time_slots', true) ?: '';
        $weekdays = $product->get_meta('_wcefp_weekdays', true) ?: [];
        
        // Calculate slots per week
        $slots_per_day = count(explode(',', $time_slots));
        $days_per_week = count($weekdays);
        
        return $capacity * $slots_per_day * $days_per_week;
    }
    
    /**
     * Check if a ticket type is available
     * 
     * @param int $product_id Product ID
     * @param string $ticket_type Ticket type
     * @return bool Availability
     */
    private function is_ticket_type_available($product_id, $ticket_type) {
        // Check if product is available
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_purchasable()) {
            return false;
        }
        
        // Check ticket-specific availability conditions
        $ticket_types = $product->get_meta('_wcefp_ticket_types', true) ?: [];
        
        foreach ($ticket_types as $type_config) {
            if ($type_config['type'] === $ticket_type) {
                return !empty($type_config['enabled']);
            }
        }
        
        // Default to available if not specifically configured
        return true;
    }
    
    /**
     * Calculate total price for selected tickets
     * 
     * @param int $product_id Product ID
     * @param array $selected_tickets Array of ticket type => quantity
     * @param array $context Additional context (date, extras, etc.)
     * @return array Price breakdown
     */
    public function calculate_ticket_prices($product_id, $selected_tickets, $context = []) {
        $ticket_types = $this->get_ticket_types($product_id);
        $price_breakdown = [
            'subtotal' => 0,
            'total' => 0,
            'tickets' => [],
            'discounts' => [],
            'adjustments' => []
        ];
        
        foreach ($selected_tickets as $ticket_type => $quantity) {
            if ($quantity <= 0) continue;
            
            $type_config = $this->find_ticket_type($ticket_types, $ticket_type);
            if (!$type_config || !$type_config['available']) {
                continue;
            }
            
            $unit_price = $type_config['price'];
            $line_total = $unit_price * $quantity;
            
            $price_breakdown['tickets'][] = [
                'type' => $ticket_type,
                'label' => $type_config['label'],
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'line_total' => $line_total
            ];
            
            $price_breakdown['subtotal'] += $line_total;
        }
        
        // Apply group discounts
        $total_quantity = array_sum($selected_tickets);
        if ($total_quantity >= 5) {
            $group_discount = $price_breakdown['subtotal'] * 0.1; // 10% group discount
            $price_breakdown['discounts'][] = [
                'type' => 'group',
                'label' => __('Group discount (5+ people)', 'wceventsfp'),
                'amount' => -$group_discount
            ];
            $price_breakdown['subtotal'] -= $group_discount;
        }
        
        $price_breakdown['total'] = max(0, $price_breakdown['subtotal']);
        
        return apply_filters('wcefp_ticket_prices_calculated', $price_breakdown, $product_id, $selected_tickets, $context);
    }
    
    /**
     * Find a specific ticket type configuration
     * 
     * @param array $ticket_types All ticket types
     * @param string $ticket_type Target ticket type
     * @return array|null Ticket type configuration
     */
    private function find_ticket_type($ticket_types, $ticket_type) {
        foreach ($ticket_types as $type_config) {
            if ($type_config['type'] === $ticket_type) {
                return $type_config;
            }
        }
        return null;
    }
    
    /**
     * Validate ticket selection
     * 
     * @param int $product_id Product ID
     * @param array $selected_tickets Selected tickets
     * @return array Validation result
     */
    public function validate_ticket_selection($product_id, $selected_tickets) {
        $ticket_types = $this->get_ticket_types($product_id);
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        $total_quantity = array_sum($selected_tickets);
        if ($total_quantity === 0) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Please select at least one ticket.', 'wceventsfp');
            return $validation;
        }
        
        foreach ($selected_tickets as $ticket_type => $quantity) {
            if ($quantity <= 0) continue;
            
            $type_config = $this->find_ticket_type($ticket_types, $ticket_type);
            if (!$type_config) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(__('Invalid ticket type: %s', 'wceventsfp'), $ticket_type);
                continue;
            }
            
            if (!$type_config['available']) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(__('Ticket type "%s" is not available.', 'wceventsfp'), $type_config['label']);
                continue;
            }
            
            if ($quantity < $type_config['min_quantity']) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(
                    __('Minimum quantity for "%s" tickets is %d.', 'wceventsfp'),
                    $type_config['label'],
                    $type_config['min_quantity']
                );
            }
            
            if ($quantity > $type_config['max_quantity']) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(
                    __('Maximum quantity for "%s" tickets is %d.', 'wceventsfp'),
                    $type_config['label'],
                    $type_config['max_quantity']
                );
            }
        }
        
        // Check for ticket-specific conditions (e.g., children require adults)
        if (!empty($selected_tickets[self::TICKET_TYPE_CHILD]) && empty($selected_tickets[self::TICKET_TYPE_ADULT])) {
            $validation['warnings'][] = __('Children tickets typically require at least one adult ticket.', 'wceventsfp');
        }
        
        return apply_filters('wcefp_ticket_selection_validated', $validation, $product_id, $selected_tickets);
    }
}