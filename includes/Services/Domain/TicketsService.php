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
     * Apply dynamic pricing to a ticket type with advanced rules
     * 
     * @param array $ticket_type Ticket type configuration
     * @param int $product_id Product ID
     * @return array Ticket type with updated pricing
     */
    private function apply_dynamic_pricing($ticket_type, $product_id) {
        $original_price = $ticket_type['price'];
        $dynamic_price = $original_price;
        $adjustments = [];
        
        // Early Bird Pricing
        $early_bird_adjustment = $this->get_early_bird_adjustment($product_id, $ticket_type['type']);
        if ($early_bird_adjustment !== 0) {
            $dynamic_price += $early_bird_adjustment;
            $adjustments['early_bird'] = $early_bird_adjustment;
        }
        
        // Last Minute Pricing
        $last_minute_adjustment = $this->get_last_minute_adjustment($product_id, $ticket_type['type']);
        if ($last_minute_adjustment !== 0) {
            $dynamic_price += $last_minute_adjustment;
            $adjustments['last_minute'] = $last_minute_adjustment;
        }
        
        // Weekend/Weekday Pricing
        $day_of_week_adjustment = $this->get_day_of_week_adjustment($product_id, $ticket_type['type']);
        if ($day_of_week_adjustment !== 0) {
            $dynamic_price += $day_of_week_adjustment;
            $adjustments['day_of_week'] = $day_of_week_adjustment;
        }
        
        // Seasonal pricing if enabled
        if (!empty($ticket_type['conditions']['seasonal_pricing'])) {
            $seasonal_adjustment = $this->get_seasonal_price_adjustment($product_id, $ticket_type['type']);
            if ($seasonal_adjustment !== 0) {
                $dynamic_price += $seasonal_adjustment;
                $adjustments['seasonal'] = $seasonal_adjustment;
            }
        }
        
        // Demand-based pricing
        $demand_adjustment = $this->get_demand_price_adjustment($product_id, $ticket_type['type']);
        if ($demand_adjustment !== 0) {
            $dynamic_price += $demand_adjustment;
            $adjustments['demand'] = $demand_adjustment;
        }
        
        // Weather-dependent pricing (for outdoor activities)
        $weather_adjustment = $this->get_weather_price_adjustment($product_id);
        if ($weather_adjustment !== 0) {
            $dynamic_price += $weather_adjustment;
            $adjustments['weather'] = $weather_adjustment;
        }
        
        // Apply minimum price threshold (never go below 30% of original)
        $min_price = $original_price * 0.3;
        $max_price = $original_price * 2.5; // Maximum 250% of original
        $dynamic_price = max($min_price, min($max_price, $dynamic_price));
        
        $ticket_type['price'] = round($dynamic_price, 2);
        $ticket_type['original_price'] = $original_price;
        $ticket_type['price_adjustments'] = $adjustments;
        $ticket_type['savings'] = $original_price - $dynamic_price;
        
        // Add pricing badges/labels
        $ticket_type['pricing_badges'] = $this->generate_pricing_badges($adjustments, $ticket_type['savings']);
        
        return $ticket_type;
    }
    
    /**
     * Calculate early bird discount/surcharge
     * 
     * @param int $product_id Product ID
     * @param string $ticket_type Ticket type
     * @return float Price adjustment
     */
    private function get_early_bird_adjustment($product_id, $ticket_type) {
        $product = wc_get_product($product_id);
        
        // Check if early bird pricing is enabled
        $early_bird_enabled = $product->get_meta('_wcefp_early_bird_enabled', true) === 'yes';
        if (!$early_bird_enabled) {
            return 0;
        }
        
        $early_bird_days = (int) $product->get_meta('_wcefp_early_bird_days', true) ?: 30;
        $early_bird_discount_percent = (float) $product->get_meta('_wcefp_early_bird_discount', true) ?: 15;
        
        // Get the earliest available booking date for this product
        $earliest_date = $this->get_earliest_booking_date($product_id);
        if (!$earliest_date) {
            return 0;
        }
        
        $booking_date = new \DateTime($earliest_date);
        $cutoff_date = clone $booking_date;
        $cutoff_date->sub(new \DateInterval('P' . $early_bird_days . 'D'));
        
        $now = new \DateTime();
        
        if ($now <= $cutoff_date) {
            $base_price = (float) $product->get_meta('_wcefp_price_' . $ticket_type, true) ?: $product->get_price();
            return -($base_price * ($early_bird_discount_percent / 100));
        }
        
        return 0;
    }
    
    /**
     * Calculate last minute pricing adjustment
     * 
     * @param int $product_id Product ID  
     * @param string $ticket_type Ticket type
     * @return float Price adjustment
     */
    private function get_last_minute_adjustment($product_id, $ticket_type) {
        $product = wc_get_product($product_id);
        
        $last_minute_enabled = $product->get_meta('_wcefp_last_minute_enabled', true) === 'yes';
        if (!$last_minute_enabled) {
            return 0;
        }
        
        $last_minute_hours = (int) $product->get_meta('_wcefp_last_minute_hours', true) ?: 24;
        $last_minute_discount_percent = (float) $product->get_meta('_wcefp_last_minute_discount', true) ?: 20;
        
        // Get the next available booking
        $next_booking_date = $this->get_next_booking_date($product_id);
        if (!$next_booking_date) {
            return 0;
        }
        
        $booking_datetime = new \DateTime($next_booking_date);
        $cutoff_datetime = new \DateTime();
        $cutoff_datetime->add(new \DateInterval('PT' . $last_minute_hours . 'H'));
        
        if ($booking_datetime <= $cutoff_datetime) {
            $base_price = (float) $product->get_meta('_wcefp_price_' . $ticket_type, true) ?: $product->get_price();
            return -($base_price * ($last_minute_discount_percent / 100));
        }
        
        return 0;
    }
    
    /**
     * Calculate weekend/weekday pricing adjustment
     * 
     * @param int $product_id Product ID
     * @param string $ticket_type Ticket type
     * @return float Price adjustment
     */
    private function get_day_of_week_adjustment($product_id, $ticket_type) {
        $product = wc_get_product($product_id);
        
        $weekend_surcharge = (float) $product->get_meta('_wcefp_weekend_surcharge', true) ?: 0;
        $weekday_discount = (float) $product->get_meta('_wcefp_weekday_discount', true) ?: 0;
        
        if ($weekend_surcharge == 0 && $weekday_discount == 0) {
            return 0;
        }
        
        // For current pricing calculation, use current day
        // In actual booking, this would use the selected booking date
        $day_of_week = (int) date('w'); // 0 = Sunday, 6 = Saturday
        
        // Weekend (Saturday, Sunday)
        if ($day_of_week === 0 || $day_of_week === 6) {
            return $weekend_surcharge;
        } else {
            return -$weekday_discount; // Discount for weekdays
        }
    }
    
    /**
     * Enhanced seasonal price adjustment with detailed rules
     * 
     * @param int $product_id Product ID
     * @param string $ticket_type Ticket type
     * @return float Price adjustment
     */
    private function get_seasonal_price_adjustment($product_id, $ticket_type) {
        $product = wc_get_product($product_id);
        $seasonal_rules = $product->get_meta('_wcefp_seasonal_rules', true) ?: [];
        
        if (empty($seasonal_rules)) {
            // Fallback to basic seasonal rules
            return $this->get_basic_seasonal_adjustment($product_id, $ticket_type);
        }
        
        $current_date = new \DateTime();
        $current_month_day = $current_date->format('m-d');
        
        foreach ($seasonal_rules as $rule) {
            if ($this->date_in_seasonal_range($current_date, $rule)) {
                $adjustment_type = $rule['adjustment_type'] ?? 'percentage';
                $adjustment_value = (float) ($rule['adjustment_value'] ?? 0);
                
                if ($adjustment_type === 'percentage') {
                    $base_price = (float) $product->get_meta('_wcefp_price_' . $ticket_type, true) ?: $product->get_price();
                    return $base_price * ($adjustment_value / 100);
                } else {
                    return $adjustment_value; // Fixed amount
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Weather-dependent pricing adjustment
     * 
     * @param int $product_id Product ID
     * @return float Price adjustment  
     */
    private function get_weather_price_adjustment($product_id) {
        $product = wc_get_product($product_id);
        
        $weather_dependent = $product->get_meta('_wcefp_weather_dependent', true) === 'yes';
        if (!$weather_dependent) {
            return 0;
        }
        
        // This would integrate with weather APIs in a real implementation
        // For now, return a placeholder based on season
        $current_month = (int) date('n');
        
        // Simulate weather-based pricing
        if (in_array($current_month, [12, 1, 2])) { // Winter
            return -5; // Indoor activities might be more popular, reduce outdoor pricing
        } elseif (in_array($current_month, [6, 7, 8])) { // Summer
            return 5; // Outdoor activities premium
        }
        
        return 0;
    }
    
    /**
     * Calculate enhanced group discounts with tiered pricing
     * 
     * @param array $selected_tickets Selected tickets
     * @param int $product_id Product ID
     * @return array Discount information
     */
    private function calculate_group_discounts($selected_tickets, $product_id) {
        $product = wc_get_product($product_id);
        $group_rules = $product->get_meta('_wcefp_group_discount_rules', true) ?: [];
        
        if (empty($group_rules)) {
            // Fallback to simple group discount
            return $this->calculate_simple_group_discount($selected_tickets);
        }
        
        $total_quantity = array_sum($selected_tickets);
        $applicable_rule = null;
        
        // Find the highest applicable discount tier
        foreach ($group_rules as $rule) {
            if ($total_quantity >= $rule['min_quantity']) {
                if (!$applicable_rule || $rule['min_quantity'] > $applicable_rule['min_quantity']) {
                    $applicable_rule = $rule;
                }
            }
        }
        
        if (!$applicable_rule) {
            return ['amount' => 0, 'label' => '', 'type' => 'none'];
        }
        
        $discount_type = $applicable_rule['discount_type'] ?? 'percentage';
        $discount_value = (float) ($applicable_rule['discount_value'] ?? 0);
        $label = $applicable_rule['label'] ?? sprintf(__('Group discount (%d+ people)', 'wceventsfp'), $applicable_rule['min_quantity']);
        
        return [
            'type' => $discount_type,
            'value' => $discount_value,
            'label' => $label,
            'min_quantity' => $applicable_rule['min_quantity']
        ];
    }
    
    /**
     * Generate pricing badges for display
     * 
     * @param array $adjustments Price adjustments
     * @param float $savings Total savings amount
     * @return array Pricing badges
     */
    private function generate_pricing_badges($adjustments, $savings) {
        $badges = [];
        
        if (isset($adjustments['early_bird']) && $adjustments['early_bird'] < 0) {
            $badges[] = [
                'type' => 'early_bird',
                'label' => __('Early Bird', 'wceventsfp'),
                'class' => 'wcefp-badge-success',
                'savings' => abs($adjustments['early_bird'])
            ];
        }
        
        if (isset($adjustments['last_minute']) && $adjustments['last_minute'] < 0) {
            $badges[] = [
                'type' => 'last_minute',
                'label' => __('Last Minute Deal', 'wceventsfp'),
                'class' => 'wcefp-badge-warning',
                'savings' => abs($adjustments['last_minute'])
            ];
        }
        
        if (isset($adjustments['seasonal']) && $adjustments['seasonal'] > 0) {
            $badges[] = [
                'type' => 'peak_season',
                'label' => __('Peak Season', 'wceventsfp'),
                'class' => 'wcefp-badge-info',
                'surcharge' => $adjustments['seasonal']
            ];
        }
        
        if (isset($adjustments['demand']) && $adjustments['demand'] > 0) {
            $badges[] = [
                'type' => 'high_demand',
                'label' => __('High Demand', 'wceventsfp'),
                'class' => 'wcefp-badge-danger',
                'surcharge' => $adjustments['demand']
            ];
        }
        
        if ($savings > 0) {
            $badges[] = [
                'type' => 'total_savings',
                'label' => sprintf(__('Save €%.2f', 'wceventsfp'), $savings),
                'class' => 'wcefp-badge-primary',
                'savings' => $savings
            ];
        }
        
        return $badges;
    }
    
    /**
     * Get earliest booking date for a product
     * 
     * @param int $product_id Product ID
     * @return string|null Earliest date or null
     */
    private function get_earliest_booking_date($product_id) {
        global $wpdb;
        
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT start_local
            FROM {$occurrences_table}
            WHERE product_id = %d
            AND status = 'active'
            AND start_local > NOW()
            ORDER BY start_local ASC
            LIMIT 1
        ", $product_id));
    }
    
    /**
     * Get next available booking date
     * 
     * @param int $product_id Product ID
     * @return string|null Next booking date or null
     */
    private function get_next_booking_date($product_id) {
        global $wpdb;
        
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT start_local
            FROM {$occurrences_table}
            WHERE product_id = %d
            AND status = 'active'
            AND start_local > NOW()
            AND (capacity - booked - held) > 0
            ORDER BY start_local ASC
            LIMIT 1
        ", $product_id));
    }
    
    /**
     * Check if date falls within seasonal range
     * 
     * @param \DateTime $date Date to check
     * @param array $rule Seasonal rule
     * @return bool Whether date is in range
     */
    private function date_in_seasonal_range($date, $rule) {
        $range_type = $rule['range_type'] ?? 'date_range';
        
        switch ($range_type) {
            case 'date_range':
                $start_date = \DateTime::createFromFormat('m-d', $rule['start_date']);
                $end_date = \DateTime::createFromFormat('m-d', $rule['end_date']);
                $current_md = $date->format('m-d');
                
                if ($start_date && $end_date) {
                    $start_md = $start_date->format('m-d');
                    $end_md = $end_date->format('m-d');
                    
                    // Handle year wrap (e.g., Dec-Jan)
                    if ($start_md <= $end_md) {
                        return $current_md >= $start_md && $current_md <= $end_md;
                    } else {
                        return $current_md >= $start_md || $current_md <= $end_md;
                    }
                }
                break;
                
            case 'months':
                $current_month = (int) $date->format('n');
                return in_array($current_month, $rule['months'] ?? []);
                
            case 'specific_dates':
                $current_date = $date->format('Y-m-d');
                return in_array($current_date, $rule['dates'] ?? []);
        }
        
        return false;
    }
    
    /**
     * Get basic seasonal adjustment (fallback)
     * 
     * @param int $product_id Product ID
     * @param string $ticket_type Ticket type
     * @return float Price adjustment
     */
    private function get_basic_seasonal_adjustment($product_id, $ticket_type) {
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
     * Calculate total price for selected tickets with enhanced pricing
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
            'adjustments' => [],
            'badges' => []
        ];
        
        foreach ($selected_tickets as $ticket_type => $quantity) {
            if ($quantity <= 0) continue;
            
            $type_config = $this->find_ticket_type($ticket_types, $ticket_type);
            if (!$type_config || !$type_config['available']) {
                continue;
            }
            
            $unit_price = $type_config['price'];
            $original_unit_price = $type_config['original_price'] ?? $unit_price;
            $line_total = $unit_price * $quantity;
            
            $price_breakdown['tickets'][] = [
                'type' => $ticket_type,
                'label' => $type_config['label'],
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'original_unit_price' => $original_unit_price,
                'line_total' => $line_total,
                'savings_per_ticket' => max(0, $original_unit_price - $unit_price),
                'badges' => $type_config['pricing_badges'] ?? []
            ];
            
            $price_breakdown['subtotal'] += $line_total;
            
            // Collect all badges
            if (!empty($type_config['pricing_badges'])) {
                $price_breakdown['badges'] = array_merge($price_breakdown['badges'], $type_config['pricing_badges']);
            }
        }
        
        // Apply enhanced group discounts
        $total_quantity = array_sum($selected_tickets);
        $group_discount_info = $this->calculate_group_discounts($selected_tickets, $product_id);
        
        if ($group_discount_info['value'] > 0) {
            $discount_amount = 0;
            
            if ($group_discount_info['type'] === 'percentage') {
                $discount_amount = $price_breakdown['subtotal'] * ($group_discount_info['value'] / 100);
            } else {
                $discount_amount = $group_discount_info['value'];
            }
            
            $price_breakdown['discounts'][] = [
                'type' => 'group',
                'label' => $group_discount_info['label'],
                'amount' => -$discount_amount,
                'min_quantity' => $group_discount_info['min_quantity']
            ];
            
            $price_breakdown['subtotal'] -= $discount_amount;
        }
        
        // Apply date-specific adjustments if date is provided
        if (!empty($context['date'])) {
            $date_adjustments = $this->get_date_specific_adjustments($product_id, $context['date']);
            foreach ($date_adjustments as $adjustment) {
                $price_breakdown['adjustments'][] = $adjustment;
                $price_breakdown['subtotal'] += $adjustment['amount'];
            }
        }
        
        // Apply minimum total threshold
        $min_total = (float) get_post_meta($product_id, '_wcefp_minimum_total', true) ?: 0;
        if ($min_total > 0 && $price_breakdown['subtotal'] < $min_total) {
            $surcharge = $min_total - $price_breakdown['subtotal'];
            $price_breakdown['adjustments'][] = [
                'type' => 'minimum_order',
                'label' => sprintf(__('Minimum order surcharge (€%.2f minimum)', 'wceventsfp'), $min_total),
                'amount' => $surcharge
            ];
            $price_breakdown['subtotal'] = $min_total;
        }
        
        $price_breakdown['total'] = max(0, $price_breakdown['subtotal']);
        
        // Remove duplicate badges
        $price_breakdown['badges'] = array_values(array_unique($price_breakdown['badges'], SORT_REGULAR));
        
        return apply_filters('wcefp_ticket_prices_calculated', $price_breakdown, $product_id, $selected_tickets, $context);
    }
    
    /**
     * Get date-specific pricing adjustments
     * 
     * @param int $product_id Product ID
     * @param string $date Selected date (Y-m-d)
     * @return array Adjustments
     */
    private function get_date_specific_adjustments($product_id, $date) {
        $adjustments = [];
        
        try {
            $date_obj = new \DateTime($date);
            $day_of_week = (int) $date_obj->format('w');
            
            // Weekend surcharge
            if ($day_of_week === 0 || $day_of_week === 6) {
                $weekend_surcharge = (float) get_post_meta($product_id, '_wcefp_weekend_surcharge', true);
                if ($weekend_surcharge > 0) {
                    $adjustments[] = [
                        'type' => 'weekend',
                        'label' => __('Weekend surcharge', 'wceventsfp'),
                        'amount' => $weekend_surcharge
                    ];
                }
            }
            
            // Holiday surcharge
            if ($this->is_holiday_date($date)) {
                $holiday_surcharge = (float) get_post_meta($product_id, '_wcefp_holiday_surcharge', true);
                if ($holiday_surcharge > 0) {
                    $adjustments[] = [
                        'type' => 'holiday',
                        'label' => __('Holiday surcharge', 'wceventsfp'),
                        'amount' => $holiday_surcharge
                    ];
                }
            }
            
        } catch (\Exception $e) {
            Logger::log('warning', 'Date-specific adjustment calculation failed: ' . $e->getMessage());
        }
        
        return $adjustments;
    }
    
    /**
     * Simple group discount fallback
     * 
     * @param array $selected_tickets Selected tickets
     * @return array Discount info
     */
    private function calculate_simple_group_discount($selected_tickets) {
        $total_quantity = array_sum($selected_tickets);
        
        if ($total_quantity >= 10) {
            return [
                'type' => 'percentage',
                'value' => 15, // 15% discount for 10+ people
                'label' => __('Large group discount (10+ people)', 'wceventsfp'),
                'min_quantity' => 10
            ];
        } elseif ($total_quantity >= 5) {
            return [
                'type' => 'percentage',
                'value' => 10, // 10% discount for 5+ people
                'label' => __('Group discount (5+ people)', 'wceventsfp'),
                'min_quantity' => 5
            ];
        }
        
        return ['value' => 0, 'label' => '', 'type' => 'none'];
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