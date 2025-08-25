<?php
/**
 * Booking Engine Coordinator
 * 
 * Orchestrates the complete booking flow integrating all domain services
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
 * Booking Engine Coordinator Class
 * 
 * Provides a unified interface for the complete booking process
 */
class BookingEngineCoordinator {
    
    /**
     * Domain services
     */
    private $scheduling_service;
    private $capacity_service;
    private $tickets_service;
    private $extras_service;
    private $hold_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->scheduling_service = new SchedulingService();
        $this->capacity_service = new CapacityService();
        $this->tickets_service = new TicketsService();
        $this->extras_service = new ExtrasService();
        $this->hold_manager = new StockHoldManager();
    }
    
    /**
     * Get complete booking availability data
     * 
     * @param int $product_id Product ID
     * @param string $date Date (Y-m-d)
     * @param array $context Additional context
     * @return array Comprehensive availability data
     */
    public function get_booking_availability($product_id, $date, $context = []) {
        // Get available slots
        $slots = $this->scheduling_service->get_available_slots($product_id, $date);
        
        // Get ticket types with dynamic pricing
        $ticket_types = $this->tickets_service->get_ticket_types($product_id);
        
        // Get available extras
        $extras = $this->extras_service->get_available_extras($product_id, array_merge($context, [
            'date' => $date
        ]));
        
        // Get capacity information
        $capacity_config = $this->capacity_service->get_capacity_config($product_id);
        $utilization = $this->capacity_service->get_capacity_utilization($product_id, $date);
        
        // Enhance slots with capacity and hold information
        foreach ($slots as &$slot) {
            $slot['capacity_status'] = $this->get_slot_capacity_status($slot);
            $slot['pricing_info'] = $this->get_slot_pricing_info($product_id, $slot, $ticket_types);
            $slot['demand_indicator'] = $this->calculate_demand_indicator($slot);
        }
        
        return [
            'date' => $date,
            'product_id' => $product_id,
            'slots' => $slots,
            'ticket_types' => $ticket_types,
            'extras' => $extras,
            'capacity_config' => $capacity_config,
            'utilization' => $utilization,
            'booking_constraints' => $this->get_booking_constraints($product_id, $date),
            'pricing_context' => $this->get_pricing_context($product_id, $date)
        ];
    }
    
    /**
     * Create a complete booking hold
     * 
     * @param array $booking_request Booking request data
     * @return array Hold result
     */
    public function create_booking_hold($booking_request) {
        // Validate booking request
        $validation = $this->validate_booking_request($booking_request);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => 'validation_failed',
                'validation' => $validation
            ];
        }
        
        $product_id = $booking_request['product_id'];
        $slot_datetime = $booking_request['slot_datetime'];
        $selected_tickets = $booking_request['tickets'];
        $selected_extras = $booking_request['extras'] ?? [];
        $session_id = $booking_request['session_id'] ?? null;
        
        // Calculate total participants for capacity check
        $total_participants = array_sum($selected_tickets);
        
        // Start coordinated hold process
        $hold_results = [];
        $successful_holds = [];
        
        try {
            // Create holds for each ticket type
            foreach ($selected_tickets as $ticket_type => $quantity) {
                if ($quantity <= 0) continue;
                
                $hold_result = $this->hold_manager->create_hold(
                    $this->get_occurrence_id($product_id, $slot_datetime),
                    $ticket_type,
                    $quantity,
                    $session_id
                );
                
                $hold_results[$ticket_type] = $hold_result;
                
                if ($hold_result['success']) {
                    $successful_holds[] = $hold_result['hold_id'];
                } else {
                    // If any ticket type fails, release all successful holds
                    $this->release_booking_holds($successful_holds, $session_id, 'partial_failure');
                    
                    return [
                        'success' => false,
                        'error' => 'capacity_unavailable',
                        'failed_ticket_type' => $ticket_type,
                        'hold_results' => $hold_results
                    ];
                }
            }
            
            // Reserve extra items if selected
            $extras_reservation = null;
            if (!empty($selected_extras)) {
                $extras_reservation = $this->extras_service->reserve_extras_stock(
                    $product_id,
                    $selected_extras,
                    0 // Temporary order ID, will be updated during checkout
                );
            }
            
            // Calculate final pricing
            $pricing = $this->calculate_comprehensive_pricing(
                $product_id,
                $selected_tickets,
                $selected_extras,
                ['date' => date('Y-m-d', strtotime($slot_datetime))]
            );
            
            Logger::log('info', 'Booking hold created successfully', [
                'product_id' => $product_id,
                'slot_datetime' => $slot_datetime,
                'tickets' => $selected_tickets,
                'extras' => $selected_extras,
                'hold_ids' => $successful_holds,
                'total_price' => $pricing['total'],
                'session_id' => $session_id
            ]);
            
            return [
                'success' => true,
                'hold_ids' => $successful_holds,
                'hold_results' => $hold_results,
                'extras_reservation' => $extras_reservation,
                'pricing' => $pricing,
                'expires_at' => $hold_results[array_key_first($hold_results)]['expires_at'] ?? null
            ];
            
        } catch (\Exception $e) {
            // Clean up any successful holds on error
            $this->release_booking_holds($successful_holds, $session_id, 'exception');
            
            Logger::log('error', 'Booking hold creation failed: ' . $e->getMessage(), [
                'product_id' => $product_id,
                'slot_datetime' => $slot_datetime,
                'tickets' => $selected_tickets,
                'session_id' => $session_id,
                'exception' => $e->getTrace()
            ]);
            
            return [
                'success' => false,
                'error' => 'hold_creation_failed',
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Convert holds to confirmed booking
     * 
     * @param string $session_id Session ID
     * @param int $order_id WooCommerce order ID
     * @return array Conversion result
     */
    public function convert_holds_to_booking($session_id, $order_id) {
        // Convert stock holds to bookings
        $hold_conversion = $this->hold_manager->convert_holds_to_bookings($session_id, $order_id);
        
        if (!$hold_conversion) {
            Logger::log('error', 'Failed to convert holds to bookings', [
                'session_id' => $session_id,
                'order_id' => $order_id
            ]);
            
            return [
                'success' => false,
                'error' => 'hold_conversion_failed'
            ];
        }
        
        // Update any extra reservations with the actual order ID
        $this->update_extras_order_references($session_id, $order_id);
        
        // Send booking confirmation notifications
        do_action('wcefp_booking_confirmed', $order_id, $session_id);
        
        Logger::log('info', 'Booking confirmed successfully', [
            'session_id' => $session_id,
            'order_id' => $order_id
        ]);
        
        return [
            'success' => true,
            'order_id' => $order_id
        ];
    }
    
    /**
     * Calculate comprehensive pricing including all components
     * 
     * @param int $product_id Product ID
     * @param array $selected_tickets Selected tickets
     * @param array $selected_extras Selected extras
     * @param array $context Pricing context
     * @return array Complete pricing breakdown
     */
    public function calculate_comprehensive_pricing($product_id, $selected_tickets, $selected_extras, $context = []) {
        // Calculate ticket prices
        $ticket_pricing = $this->tickets_service->calculate_ticket_prices(
            $product_id,
            $selected_tickets,
            $context
        );
        
        // Calculate extras prices
        $extras_pricing = $this->extras_service->calculate_extras_price(
            $product_id,
            $selected_extras,
            array_merge($context, ['tickets' => $selected_tickets])
        );
        
        // Combine pricing
        $combined_pricing = [
            'tickets' => $ticket_pricing,
            'extras' => $extras_pricing,
            'subtotal' => $ticket_pricing['total'] + $extras_pricing['total'],
            'total' => $ticket_pricing['total'] + $extras_pricing['total'],
            'currency' => get_woocommerce_currency(),
            'all_discounts' => array_merge(
                $ticket_pricing['discounts'] ?? [],
                $extras_pricing['discounts'] ?? []
            ),
            'all_badges' => array_merge(
                $ticket_pricing['badges'] ?? [],
                []
            )
        ];
        
        // Apply any order-level adjustments
        $order_adjustments = $this->apply_order_level_adjustments($combined_pricing, $product_id, $context);
        $combined_pricing['adjustments'] = $order_adjustments;
        
        foreach ($order_adjustments as $adjustment) {
            $combined_pricing['total'] += $adjustment['amount'];
        }
        
        $combined_pricing['total'] = max(0, $combined_pricing['total']);
        
        return apply_filters('wcefp_comprehensive_pricing_calculated', $combined_pricing, $product_id, $selected_tickets, $selected_extras, $context);
    }
    
    /**
     * Validate complete booking request
     * 
     * @param array $booking_request Booking request data
     * @return array Validation result
     */
    private function validate_booking_request($booking_request) {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        // Required fields validation
        $required_fields = ['product_id', 'slot_datetime', 'tickets'];
        foreach ($required_fields as $field) {
            if (empty($booking_request[$field])) {
                $validation['valid'] = false;
                $validation['errors'][] = sprintf(__('Missing required field: %s', 'wceventsfp'), $field);
            }
        }
        
        if (!$validation['valid']) {
            return $validation;
        }
        
        $product_id = $booking_request['product_id'];
        $selected_tickets = $booking_request['tickets'];
        $selected_extras = $booking_request['extras'] ?? [];
        
        // Validate product exists and is bookable
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Invalid or non-bookable product.', 'wceventsfp');
            return $validation;
        }
        
        // Validate ticket selection
        $ticket_validation = $this->tickets_service->validate_ticket_selection($product_id, $selected_tickets);
        if (!$ticket_validation['valid']) {
            $validation['valid'] = false;
            $validation['errors'] = array_merge($validation['errors'], $ticket_validation['errors']);
        }
        $validation['warnings'] = array_merge($validation['warnings'], $ticket_validation['warnings'] ?? []);
        
        // Validate extras selection if provided
        if (!empty($selected_extras)) {
            $extras_validation = $this->extras_service->validate_extras_selection(
                $product_id,
                $selected_extras,
                ['tickets' => $selected_tickets]
            );
            
            if (!$extras_validation['valid']) {
                $validation['valid'] = false;
                $validation['errors'] = array_merge($validation['errors'], $extras_validation['errors']);
            }
            $validation['warnings'] = array_merge($validation['warnings'], $extras_validation['warnings'] ?? []);
        }
        
        // Validate slot availability
        $slot_datetime = $booking_request['slot_datetime'];
        $date = date('Y-m-d', strtotime($slot_datetime));
        $total_participants = array_sum($selected_tickets);
        
        if (!$this->scheduling_service->is_slot_available($product_id, $slot_datetime, $total_participants)) {
            $validation['valid'] = false;
            $validation['errors'][] = __('Selected time slot is no longer available.', 'wceventsfp');
        }
        
        return $validation;
    }
    
    /**
     * Get slot capacity status for display
     * 
     * @param array $slot Slot data
     * @return array Capacity status
     */
    private function get_slot_capacity_status($slot) {
        $utilization_pct = $slot['capacity'] > 0 ? ($slot['booked'] / $slot['capacity']) * 100 : 0;
        
        if ($utilization_pct >= 100) {
            $status = 'full';
            $class = 'wcefp-status-full';
            $label = __('Full', 'wceventsfp');
        } elseif ($utilization_pct >= 90) {
            $status = 'nearly_full';
            $class = 'wcefp-status-nearly-full';
            $label = __('Nearly Full', 'wceventsfp');
        } elseif ($utilization_pct >= 70) {
            $status = 'filling_up';
            $class = 'wcefp-status-filling-up';
            $label = __('Filling Up', 'wceventsfp');
        } elseif ($utilization_pct >= 30) {
            $status = 'available';
            $class = 'wcefp-status-available';
            $label = __('Available', 'wceventsfp');
        } else {
            $status = 'plenty_available';
            $class = 'wcefp-status-plenty';
            $label = __('Plenty Available', 'wceventsfp');
        }
        
        return [
            'status' => $status,
            'class' => $class,
            'label' => $label,
            'utilization_percentage' => round($utilization_pct, 1)
        ];
    }
    
    /**
     * Get slot pricing information
     * 
     * @param int $product_id Product ID
     * @param array $slot Slot data
     * @param array $ticket_types Ticket types
     * @return array Pricing info
     */
    private function get_slot_pricing_info($product_id, $slot, $ticket_types) {
        $pricing_info = [
            'from_price' => null,
            'price_range' => [],
            'has_dynamic_pricing' => false,
            'pricing_badges' => []
        ];
        
        if (empty($ticket_types)) {
            return $pricing_info;
        }
        
        $prices = [];
        $all_badges = [];
        
        foreach ($ticket_types as $ticket_type) {
            if (!$ticket_type['available']) continue;
            
            $prices[] = $ticket_type['price'];
            
            // Check for dynamic pricing
            if (isset($ticket_type['original_price']) && $ticket_type['original_price'] != $ticket_type['price']) {
                $pricing_info['has_dynamic_pricing'] = true;
            }
            
            // Collect pricing badges
            if (!empty($ticket_type['pricing_badges'])) {
                $all_badges = array_merge($all_badges, $ticket_type['pricing_badges']);
            }
        }
        
        if (!empty($prices)) {
            $pricing_info['from_price'] = min($prices);
            $pricing_info['price_range'] = [min($prices), max($prices)];
            $pricing_info['pricing_badges'] = array_unique($all_badges, SORT_REGULAR);
        }
        
        return $pricing_info;
    }
    
    /**
     * Calculate demand indicator for a slot
     * 
     * @param array $slot Slot data
     * @return array Demand indicator
     */
    private function calculate_demand_indicator($slot) {
        $utilization_pct = $slot['capacity'] > 0 ? ($slot['booked'] / $slot['capacity']) * 100 : 0;
        
        if ($utilization_pct >= 80) {
            return ['level' => 'high', 'class' => 'wcefp-demand-high', 'label' => __('High Demand', 'wceventsfp')];
        } elseif ($utilization_pct >= 50) {
            return ['level' => 'medium', 'class' => 'wcefp-demand-medium', 'label' => __('Moderate Demand', 'wceventsfp')];
        } else {
            return ['level' => 'low', 'class' => 'wcefp-demand-low', 'label' => __('Good Availability', 'wceventsfp')];
        }
    }
    
    /**
     * Get booking constraints for a product/date
     * 
     * @param int $product_id Product ID
     * @param string $date Date
     * @return array Constraints
     */
    private function get_booking_constraints($product_id, $date) {
        $product = wc_get_product($product_id);
        
        return [
            'min_advance_hours' => (int) $product->get_meta('_wcefp_min_advance_hours', true) ?: 2,
            'max_advance_days' => (int) $product->get_meta('_wcefp_advance_booking_days', true) ?: 365,
            'min_participants' => (int) $product->get_meta('_wcefp_min_participants', true) ?: 1,
            'max_participants' => (int) $product->get_meta('_wcefp_capacity', true) ?: 10,
            'requires_approval' => $product->get_meta('_wcefp_requires_approval', true) === 'yes',
            'cancellation_policy' => $product->get_meta('_wcefp_cancellation_policy', true),
            'age_restrictions' => $product->get_meta('_wcefp_age_restrictions', true)
        ];
    }
    
    /**
     * Get pricing context for dynamic pricing
     * 
     * @param int $product_id Product ID
     * @param string $date Date
     * @return array Pricing context
     */
    private function get_pricing_context($product_id, $date) {
        return [
            'booking_date' => $date,
            'days_in_advance' => (strtotime($date) - time()) / 86400,
            'is_weekend' => in_array(date('w', strtotime($date)), [0, 6]),
            'is_holiday' => $this->is_holiday_date($date),
            'season' => $this->get_season($date),
            'demand_level' => $this->get_current_demand_level($product_id)
        ];
    }
    
    /**
     * Apply order-level pricing adjustments
     * 
     * @param array $pricing Current pricing
     * @param int $product_id Product ID
     * @param array $context Context
     * @return array Adjustments
     */
    private function apply_order_level_adjustments($pricing, $product_id, $context) {
        $adjustments = [];
        
        // Platform fee
        $platform_fee_pct = get_option('wcefp_platform_fee_percentage', 0);
        if ($platform_fee_pct > 0) {
            $fee_amount = $pricing['subtotal'] * ($platform_fee_pct / 100);
            $adjustments[] = [
                'type' => 'platform_fee',
                'label' => sprintf(__('Platform fee (%s%%)', 'wceventsfp'), $platform_fee_pct),
                'amount' => $fee_amount
            ];
        }
        
        // Payment processing fee
        $payment_method = $context['payment_method'] ?? '';
        if ($payment_method === 'credit_card') {
            $processing_fee = $pricing['subtotal'] * 0.029; // 2.9%
            $adjustments[] = [
                'type' => 'processing_fee',
                'label' => __('Payment processing fee', 'wceventsfp'),
                'amount' => $processing_fee
            ];
        }
        
        return $adjustments;
    }
    
    /**
     * Release multiple booking holds
     * 
     * @param array $hold_ids Hold IDs to release
     * @param string $session_id Session ID
     * @param string $reason Release reason
     * @return void
     */
    private function release_booking_holds($hold_ids, $session_id, $reason) {
        foreach ($hold_ids as $hold_id) {
            $this->hold_manager->release_hold($hold_id, $session_id, $reason);
        }
    }
    
    /**
     * Get occurrence ID for a slot datetime
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @return int Occurrence ID
     */
    private function get_occurrence_id($product_id, $slot_datetime) {
        global $wpdb;
        
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        
        // Try to find exact match first
        $occurrence_id = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$occurrences_table}
            WHERE product_id = %d
            AND start_local = %s
            AND status = 'active'
        ", $product_id, $slot_datetime));
        
        if ($occurrence_id) {
            return (int) $occurrence_id;
        }
        
        // If no exact match, create a new occurrence
        return $this->create_occurrence_for_slot($product_id, $slot_datetime);
    }
    
    /**
     * Create occurrence for a slot
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @return int Occurrence ID
     */
    private function create_occurrence_for_slot($product_id, $slot_datetime) {
        global $wpdb;
        
        $product = wc_get_product($product_id);
        $capacity = (int) $product->get_meta('_wcefp_capacity', true) ?: 10;
        $duration = (int) $product->get_meta('_wcefp_duration', true) ?: 60;
        
        // Calculate end time
        $end_datetime = date('Y-m-d H:i:s', strtotime($slot_datetime) + ($duration * 60));
        
        // Convert to UTC for storage
        $start_utc = gmdate('Y-m-d H:i:s', strtotime($slot_datetime));
        $end_utc = gmdate('Y-m-d H:i:s', strtotime($end_datetime));
        
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        
        $result = $wpdb->insert(
            $occurrences_table,
            [
                'product_id' => $product_id,
                'start_utc' => $start_utc,
                'end_utc' => $end_utc,
                'start_local' => $slot_datetime,
                'end_local' => $end_datetime,
                'timezone_string' => wp_timezone_string(),
                'capacity' => $capacity,
                'booked' => 0,
                'held' => 0,
                'status' => 'active'
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
        );
        
        if ($result) {
            $occurrence_id = $wpdb->insert_id;
            Logger::log('info', 'Created occurrence for booking slot', [
                'occurrence_id' => $occurrence_id,
                'product_id' => $product_id,
                'slot_datetime' => $slot_datetime
            ]);
            return $occurrence_id;
        }
        
        return 0;
    }
    
    /**
     * Update extra reservations with actual order ID
     * 
     * @param string $session_id Session ID
     * @param int $order_id Order ID
     * @return void
     */
    private function update_extras_order_references($session_id, $order_id) {
        global $wpdb;
        
        $reservations_table = $wpdb->prefix . 'wcefp_extra_reservations';
        
        $wpdb->update(
            $reservations_table,
            ['order_id' => $order_id],
            ['order_id' => 0], // Temporary reservations
            ['%d'],
            ['%d']
        );
    }
    
    /**
     * Check if date is a holiday
     * 
     * @param string $date Date string
     * @return bool Holiday status
     */
    private function is_holiday_date($date) {
        // This could be enhanced with holiday APIs or custom holiday lists
        $holidays = ['12-25', '12-26', '01-01'];
        return in_array(date('m-d', strtotime($date)), $holidays);
    }
    
    /**
     * Get season for a date
     * 
     * @param string $date Date string
     * @return string Season
     */
    private function get_season($date) {
        $month = (int) date('n', strtotime($date));
        
        if (in_array($month, [12, 1, 2])) return 'winter';
        if (in_array($month, [3, 4, 5])) return 'spring';
        if (in_array($month, [6, 7, 8])) return 'summer';
        return 'autumn';
    }
    
    /**
     * Get current demand level for a product
     * 
     * @param int $product_id Product ID
     * @return string Demand level
     */
    private function get_current_demand_level($product_id) {
        $utilization = $this->capacity_service->get_capacity_utilization($product_id);
        $avg_utilization = $utilization['next_7_days']['utilization_percentage'] ?? 0;
        
        if ($avg_utilization >= 80) return 'high';
        if ($avg_utilization >= 50) return 'medium';
        return 'low';
    }
}