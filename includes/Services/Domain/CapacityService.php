<?php
/**
 * Capacity Service
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
 * Capacity and stock management service for events and experiences
 */
class CapacityService {
    
    /**
     * Capacity types
     */
    const CAPACITY_TYPE_TOTAL = 'total';
    const CAPACITY_TYPE_PER_SLOT = 'per_slot';
    const CAPACITY_TYPE_DAILY = 'daily';
    const CAPACITY_TYPE_WEEKLY = 'weekly';
    
    /**
     * Get capacity configuration for a product
     * 
     * @param int $product_id Product ID
     * @return array Capacity configuration
     */
    public function get_capacity_config($product_id) {
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
            return [];
        }
        
        // Get stored capacity config or create default
        $capacity_config = $product->get_meta('_wcefp_capacity_config', true);
        
        if (empty($capacity_config) || !is_array($capacity_config)) {
            $capacity_config = $this->get_default_capacity_config($product);
        }
        
        // Add current utilization data
        $capacity_config['current_utilization'] = $this->get_capacity_utilization($product_id);
        
        return $capacity_config;
    }
    
    /**
     * Get default capacity configuration
     * 
     * @param \WC_Product $product Product object
     * @return array Default capacity configuration
     */
    private function get_default_capacity_config($product) {
        $base_capacity = (int) $product->get_meta('_wcefp_capacity', true) ?: 10;
        
        return [
            'type' => self::CAPACITY_TYPE_PER_SLOT,
            'base_capacity' => $base_capacity,
            'max_capacity' => $base_capacity * 2, // Overbooking protection
            'min_capacity' => 1, // Minimum required for event to run
            'buffer_percentage' => 10, // 10% buffer for last-minute bookings
            'overbooking_enabled' => false,
            'overbooking_percentage' => 110, // Allow 110% of capacity
            'waitlist_enabled' => true,
            'auto_release_minutes' => 15, // Auto-release reserved spots after 15 min
            'capacity_alerts' => [
                'low_availability' => 80, // Alert when 80% full
                'nearly_full' => 95, // Alert when 95% full
                'waitlist_threshold' => 100 // Start waitlist when 100% full
            ]
        ];
    }
    
    /**
     * Save capacity configuration for a product
     * 
     * @param int $product_id Product ID
     * @param array $capacity_config Capacity configuration
     * @return bool Success
     */
    public function save_capacity_config($product_id, $capacity_config) {
        if (!SecurityManager::can_user('manage_wcefp_events')) {
            return false;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return false;
        }
        
        // Validate and sanitize configuration
        $sanitized_config = $this->sanitize_capacity_config($capacity_config);
        
        // Save to product meta
        $product->update_meta_data('_wcefp_capacity_config', $sanitized_config);
        $product->save();
        
        Logger::info("Capacity configuration updated for product {$product_id}", $sanitized_config);
        
        // Trigger action for integrations
        do_action('wcefp_capacity_config_updated', $product_id, $sanitized_config);
        
        return true;
    }
    
    /**
     * Sanitize capacity configuration
     * 
     * @param array $config Raw configuration
     * @return array Sanitized configuration
     */
    private function sanitize_capacity_config($config) {
        return [
            'type' => in_array($config['type'] ?? '', [
                self::CAPACITY_TYPE_TOTAL,
                self::CAPACITY_TYPE_PER_SLOT,
                self::CAPACITY_TYPE_DAILY,
                self::CAPACITY_TYPE_WEEKLY
            ]) ? $config['type'] : self::CAPACITY_TYPE_PER_SLOT,
            'base_capacity' => max(1, (int) ($config['base_capacity'] ?? 10)),
            'max_capacity' => max(1, (int) ($config['max_capacity'] ?? 20)),
            'min_capacity' => max(1, (int) ($config['min_capacity'] ?? 1)),
            'buffer_percentage' => max(0, min(50, (int) ($config['buffer_percentage'] ?? 10))),
            'overbooking_enabled' => !empty($config['overbooking_enabled']),
            'overbooking_percentage' => max(100, min(200, (int) ($config['overbooking_percentage'] ?? 110))),
            'waitlist_enabled' => !empty($config['waitlist_enabled']),
            'auto_release_minutes' => max(5, min(120, (int) ($config['auto_release_minutes'] ?? 15))),
            'capacity_alerts' => [
                'low_availability' => max(50, min(100, (int) ($config['capacity_alerts']['low_availability'] ?? 80))),
                'nearly_full' => max(80, min(100, (int) ($config['capacity_alerts']['nearly_full'] ?? 95))),
                'waitlist_threshold' => max(90, min(100, (int) ($config['capacity_alerts']['waitlist_threshold'] ?? 100)))
            ]
        ];
    }
    
    /**
     * Get capacity utilization for a product
     * 
     * @param int $product_id Product ID
     * @param string|null $date_filter Optional date filter (Y-m-d)
     * @return array Utilization data
     */
    public function get_capacity_utilization($product_id, $date_filter = null) {
        $capacity_config = $product = wc_get_product($product_id);
        if (!$product) {
            return [];
        }
        
        $config = $this->get_capacity_config($product_id);
        $base_capacity = $config['base_capacity'];
        
        // Get utilization for different time periods
        $utilization = [
            'today' => $this->calculate_utilization($product_id, date('Y-m-d'), $base_capacity),
            'tomorrow' => $this->calculate_utilization($product_id, date('Y-m-d', strtotime('+1 day')), $base_capacity),
            'next_7_days' => $this->calculate_period_utilization($product_id, 7, $base_capacity),
            'next_30_days' => $this->calculate_period_utilization($product_id, 30, $base_capacity)
        ];
        
        if ($date_filter) {
            $utilization['filtered_date'] = $this->calculate_utilization($product_id, $date_filter, $base_capacity);
        }
        
        return $utilization;
    }
    
    /**
     * Calculate utilization for a specific date
     * 
     * @param int $product_id Product ID
     * @param string $date Date (Y-m-d format)
     * @param int $base_capacity Base capacity per slot
     * @return array Utilization data for the date
     */
    private function calculate_utilization($product_id, $date, $base_capacity) {
        // Get scheduled slots for this date
        $scheduling_service = new SchedulingService();
        $available_slots = $scheduling_service->get_available_slots($product_id, $date);
        
        if (empty($available_slots)) {
            return [
                'date' => $date,
                'total_capacity' => 0,
                'total_booked' => 0,
                'utilization_percentage' => 0,
                'status' => 'no_slots',
                'slots' => []
            ];
        }
        
        $total_capacity = 0;
        $total_booked = 0;
        $slot_details = [];
        
        foreach ($available_slots as $slot) {
            $total_capacity += $slot['capacity'];
            $total_booked += $slot['booked'];
            
            $slot_utilization = $slot['capacity'] > 0 ? ($slot['booked'] / $slot['capacity']) * 100 : 0;
            
            $slot_details[] = [
                'time' => $slot['time'],
                'capacity' => $slot['capacity'],
                'booked' => $slot['booked'],
                'available' => $slot['available'],
                'utilization_percentage' => round($slot_utilization, 2),
                'status' => $this->get_slot_status($slot_utilization)
            ];
        }
        
        $overall_utilization = $total_capacity > 0 ? ($total_booked / $total_capacity) * 100 : 0;
        
        return [
            'date' => $date,
            'total_capacity' => $total_capacity,
            'total_booked' => $total_booked,
            'total_available' => $total_capacity - $total_booked,
            'utilization_percentage' => round($overall_utilization, 2),
            'status' => $this->get_capacity_status($overall_utilization),
            'slots' => $slot_details
        ];
    }
    
    /**
     * Calculate utilization for a period
     * 
     * @param int $product_id Product ID
     * @param int $days Number of days to look ahead
     * @param int $base_capacity Base capacity per slot
     * @return array Period utilization data
     */
    private function calculate_period_utilization($product_id, $days, $base_capacity) {
        $total_capacity = 0;
        $total_booked = 0;
        $daily_data = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("+{$i} days"));
            $day_utilization = $this->calculate_utilization($product_id, $date, $base_capacity);
            
            $total_capacity += $day_utilization['total_capacity'];
            $total_booked += $day_utilization['total_booked'];
            $daily_data[] = $day_utilization;
        }
        
        $period_utilization = $total_capacity > 0 ? ($total_booked / $total_capacity) * 100 : 0;
        
        return [
            'period_days' => $days,
            'total_capacity' => $total_capacity,
            'total_booked' => $total_booked,
            'total_available' => $total_capacity - $total_booked,
            'utilization_percentage' => round($period_utilization, 2),
            'status' => $this->get_capacity_status($period_utilization),
            'daily_data' => $daily_data
        ];
    }
    
    /**
     * Get capacity status based on utilization percentage
     * 
     * @param float $utilization_percentage Utilization percentage
     * @return string Status
     */
    private function get_capacity_status($utilization_percentage) {
        if ($utilization_percentage >= 95) {
            return 'nearly_full';
        } elseif ($utilization_percentage >= 80) {
            return 'high_demand';
        } elseif ($utilization_percentage >= 50) {
            return 'moderate_demand';
        } elseif ($utilization_percentage >= 20) {
            return 'low_demand';
        } else {
            return 'very_low_demand';
        }
    }
    
    /**
     * Get slot status based on utilization percentage
     * 
     * @param float $utilization_percentage Slot utilization percentage
     * @return string Status
     */
    private function get_slot_status($utilization_percentage) {
        if ($utilization_percentage >= 100) {
            return 'full';
        } elseif ($utilization_percentage >= 90) {
            return 'nearly_full';
        } elseif ($utilization_percentage >= 70) {
            return 'filling_up';
        } elseif ($utilization_percentage >= 30) {
            return 'available';
        } else {
            return 'plenty_available';
        }
    }
    
    /**
     * Check if capacity is available for booking
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @param int $requested_quantity Requested quantity
     * @param array $options Additional options
     * @return array Availability result
     */
    public function check_availability($product_id, $slot_datetime, $requested_quantity = 1, $options = []) {
        $config = $this->get_capacity_config($product_id);
        
        // Get current slot capacity
        $scheduling_service = new SchedulingService();
        $date = date('Y-m-d', strtotime($slot_datetime));
        $slots = $scheduling_service->get_available_slots($product_id, $date);
        
        $target_slot = null;
        foreach ($slots as $slot) {
            if ($slot['datetime'] === $slot_datetime) {
                $target_slot = $slot;
                break;
            }
        }
        
        if (!$target_slot) {
            return [
                'available' => false,
                'reason' => 'slot_not_found',
                'message' => __('The requested time slot is not available.', 'wceventsfp')
            ];
        }
        
        // Check basic availability
        if ($target_slot['available'] < $requested_quantity) {
            // Check if overbooking is enabled
            if ($config['overbooking_enabled']) {
                $max_with_overbooking = ceil($target_slot['capacity'] * ($config['overbooking_percentage'] / 100));
                $total_would_be = $target_slot['booked'] + $requested_quantity;
                
                if ($total_would_be <= $max_with_overbooking) {
                    return [
                        'available' => true,
                        'overbooking' => true,
                        'message' => __('Available with overbooking.', 'wceventsfp'),
                        'capacity_info' => $target_slot
                    ];
                }
            }
            
            // Check if waitlist is enabled
            if ($config['waitlist_enabled']) {
                return [
                    'available' => false,
                    'waitlist_available' => true,
                    'reason' => 'capacity_full',
                    'message' => __('Fully booked. Join the waitlist?', 'wceventsfp'),
                    'capacity_info' => $target_slot
                ];
            }
            
            return [
                'available' => false,
                'reason' => 'capacity_full',
                'message' => __('Sorry, this time slot is fully booked.', 'wceventsfp'),
                'capacity_info' => $target_slot
            ];
        }
        
        // Check minimum capacity requirements
        if (isset($options['check_minimum']) && $options['check_minimum']) {
            $total_after_booking = $target_slot['booked'] + $requested_quantity;
            if ($total_after_booking < $config['min_capacity']) {
                return [
                    'available' => true,
                    'warning' => true,
                    'message' => sprintf(
                        __('This experience requires a minimum of %d participants to run.', 'wceventsfp'),
                        $config['min_capacity']
                    ),
                    'capacity_info' => $target_slot
                ];
            }
        }
        
        return [
            'available' => true,
            'message' => __('Available for booking.', 'wceventsfp'),
            'capacity_info' => $target_slot
        ];
    }
    
    /**
     * Reserve capacity for a booking
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @param int $quantity Quantity to reserve
     * @param array $booking_data Booking data
     * @return array Reservation result
     */
    public function reserve_capacity($product_id, $slot_datetime, $quantity, $booking_data = []) {
        $availability = $this->check_availability($product_id, $slot_datetime, $quantity);
        
        if (!$availability['available'] && empty($availability['overbooking'])) {
            return [
                'success' => false,
                'message' => $availability['message']
            ];
        }
        
        // Create reservation using SchedulingService
        $scheduling_service = new SchedulingService();
        $order_id = $booking_data['order_id'] ?? 0;
        
        $reserved = $scheduling_service->reserve_slot($product_id, $slot_datetime, $quantity, $order_id);
        
        if ($reserved) {
            // Check if we need to send capacity alerts
            $this->check_and_send_capacity_alerts($product_id, $slot_datetime, $availability['capacity_info']);
            
            return [
                'success' => true,
                'message' => __('Capacity reserved successfully.', 'wceventsfp'),
                'reservation_expires' => time() + (15 * MINUTE_IN_SECONDS),
                'overbooking' => !empty($availability['overbooking'])
            ];
        }
        
        return [
            'success' => false,
            'message' => __('Failed to reserve capacity. Please try again.', 'wceventsfp')
        ];
    }
    
    /**
     * Check and send capacity alerts if thresholds are reached
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @param array $capacity_info Current capacity info
     * @return void
     */
    private function check_and_send_capacity_alerts($product_id, $slot_datetime, $capacity_info) {
        $config = $this->get_capacity_config($product_id);
        $alerts = $config['capacity_alerts'];
        
        $utilization_percentage = ($capacity_info['booked'] / $capacity_info['capacity']) * 100;
        
        // Check for low availability alert
        if ($utilization_percentage >= $alerts['low_availability'] && 
            $utilization_percentage < $alerts['nearly_full']) {
            
            do_action('wcefp_capacity_low_availability', $product_id, $slot_datetime, $capacity_info);
        }
        
        // Check for nearly full alert
        if ($utilization_percentage >= $alerts['nearly_full']) {
            do_action('wcefp_capacity_nearly_full', $product_id, $slot_datetime, $capacity_info);
        }
        
        // Check for waitlist threshold
        if ($utilization_percentage >= $alerts['waitlist_threshold']) {
            do_action('wcefp_capacity_waitlist_threshold', $product_id, $slot_datetime, $capacity_info);
        }
    }
    
    /**
     * Get capacity analytics for reporting
     * 
     * @param int $product_id Product ID
     * @param string $period Period ('week', 'month', 'quarter')
     * @return array Analytics data
     */
    public function get_capacity_analytics($product_id, $period = 'month') {
        $days = [
            'week' => 7,
            'month' => 30,
            'quarter' => 90
        ];
        
        $period_days = $days[$period] ?? 30;
        $utilization = $this->calculate_period_utilization($product_id, $period_days, 0);
        
        // Calculate additional analytics
        $analytics = [
            'period' => $period,
            'period_days' => $period_days,
            'utilization' => $utilization,
            'trends' => $this->calculate_utilization_trends($product_id, $period_days),
            'performance_indicators' => $this->calculate_performance_indicators($utilization),
            'recommendations' => $this->generate_capacity_recommendations($utilization)
        ];
        
        return apply_filters('wcefp_capacity_analytics', $analytics, $product_id, $period);
    }
    
    /**
     * Calculate utilization trends
     * 
     * @param int $product_id Product ID
     * @param int $period_days Period in days
     * @return array Trend data
     */
    private function calculate_utilization_trends($product_id, $period_days) {
        // This would typically involve more complex trend analysis
        // For now, return basic trend indicators
        return [
            'direction' => 'stable', // 'increasing', 'decreasing', 'stable'
            'velocity' => 0, // Rate of change
            'seasonal_patterns' => [] // Weekly/monthly patterns
        ];
    }
    
    /**
     * Calculate performance indicators
     * 
     * @param array $utilization Utilization data
     * @return array Performance indicators
     */
    private function calculate_performance_indicators($utilization) {
        return [
            'efficiency_score' => min(100, $utilization['utilization_percentage']),
            'revenue_potential' => $utilization['total_available'] > 0 ? 
                ($utilization['total_booked'] / ($utilization['total_booked'] + $utilization['total_available'])) * 100 : 0,
            'demand_health' => $this->get_capacity_status($utilization['utilization_percentage'])
        ];
    }
    
    /**
     * Generate capacity recommendations
     * 
     * @param array $utilization Utilization data
     * @return array Recommendations
     */
    private function generate_capacity_recommendations($utilization) {
        $recommendations = [];
        
        if ($utilization['utilization_percentage'] > 90) {
            $recommendations[] = [
                'type' => 'increase_capacity',
                'priority' => 'high',
                'message' => __('Consider increasing capacity or adding more time slots due to high demand.', 'wceventsfp')
            ];
        } elseif ($utilization['utilization_percentage'] < 30) {
            $recommendations[] = [
                'type' => 'optimize_pricing',
                'priority' => 'medium',
                'message' => __('Consider promotional pricing or marketing to increase bookings.', 'wceventsfp')
            ];
        }
        
        return $recommendations;
    }
}