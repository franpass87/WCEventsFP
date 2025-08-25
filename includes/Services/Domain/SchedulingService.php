<?php
/**
 * Scheduling Service
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
 * Advanced scheduling service for events and experiences
 */
class SchedulingService {
    
    /**
     * Get available time slots for a product on a specific date with caching
     * 
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return array Available time slots with capacity info
     */
    public function get_available_slots($product_id, $date) {
        if (!SecurityManager::can_user('manage_wcefp_events')) {
            return [];
        }
        
        // Use cache for expensive slot queries
        return \WCEFP\Core\Performance\QueryCacheManager::cache_availability_query(
            $product_id,
            $date,
            function() use ($product_id, $date) {
                return $this->get_available_slots_uncached($product_id, $date);
            }
        );
    }
    
    /**
     * Get available time slots without caching (internal method)
     * 
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return array Available time slots with capacity info
     */
    private function get_available_slots_uncached($product_id, $date) {
        
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['evento', 'esperienza'])) {
            return [];
        }
        
        // Get product scheduling configuration
        $weekdays = $product->get_meta('_wcefp_weekdays', true) ?: [];
        $time_slots = $product->get_meta('_wcefp_time_slots', true) ?: '';
        $capacity = (int) $product->get_meta('_wcefp_capacity', true) ?: 10;
        $duration = (int) $product->get_meta('_wcefp_duration', true) ?: 60;
        
        $date_obj = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj) {
            return [];
        }
        
        // Check if date is available (weekday check)
        $day_of_week = (int) $date_obj->format('w'); // 0 = Sunday, 1 = Monday, etc.
        if (!in_array($day_of_week, $weekdays)) {
            return [];
        }
        
        // Parse time slots
        $slots = $this->parse_time_slots($time_slots);
        $available_slots = [];
        
        foreach ($slots as $slot_time) {
            $slot_datetime = $date . ' ' . $slot_time;
            $slot_capacity = $this->get_slot_capacity($product_id, $slot_datetime);
            
            $available_slots[] = [
                'time' => $slot_time,
                'datetime' => $slot_datetime,
                'capacity' => $capacity,
                'booked' => $slot_capacity['booked'],
                'available' => $slot_capacity['available'],
                'duration' => $duration,
                'is_available' => $slot_capacity['available'] > 0
            ];
        }
        
        return $available_slots;
    }
    
    /**
     * Parse time slots string into array
     * 
     * @param string $time_slots Comma-separated time slots
     * @return array Parsed time slots
     */
    private function parse_time_slots($time_slots) {
        if (empty($time_slots)) {
            return ['09:00', '14:00']; // Default slots
        }
        
        $slots = array_map('trim', explode(',', $time_slots));
        $valid_slots = [];
        
        foreach ($slots as $slot) {
            // Validate time format (HH:MM)
            if (preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/', $slot)) {
                $valid_slots[] = $slot;
            }
        }
        
        return $valid_slots ?: ['09:00', '14:00'];
    }
    
    /**
     * Get slot capacity information
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime (Y-m-d H:i format)
     * @return array Capacity information
     */
    private function get_slot_capacity($product_id, $slot_datetime) {
        $product = wc_get_product($product_id);
        $max_capacity = (int) $product->get_meta('_wcefp_capacity', true) ?: 10;
        
        // Count existing bookings for this slot
        $booked = $this->count_slot_bookings($product_id, $slot_datetime);
        
        return [
            'max' => $max_capacity,
            'booked' => $booked,
            'available' => max(0, $max_capacity - $booked)
        ];
    }
    
    /**
     * Count bookings for a specific slot
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @return int Number of bookings
     */
    private function count_slot_bookings($product_id, $slot_datetime) {
        global $wpdb;
        
        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT oi.order_item_id, oi.order_id
            FROM {$wpdb->prefix}woocommerce_order_items oi
            INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
            INNER JOIN {$wpdb->prefix}posts p ON oi.order_id = p.ID
            WHERE oi.order_item_type = 'line_item'
            AND oim.meta_key = '_product_id'
            AND oim.meta_value = %d
            AND p.post_status IN ('wc-processing', 'wc-completed')
        ", $product_id));
        
        $total_bookings = 0;
        
        foreach ($orders as $order_item) {
            // Check if this order item has the specific slot datetime
            $item_slot = $wpdb->get_var($wpdb->prepare("
                SELECT meta_value
                FROM {$wpdb->prefix}woocommerce_order_itemmeta
                WHERE order_item_id = %d
                AND meta_key = '_wcefp_slot_datetime'
            ", $order_item->order_item_id));
            
            if ($item_slot === $slot_datetime) {
                // Get quantity for this order item
                $quantity = $wpdb->get_var($wpdb->prepare("
                    SELECT meta_value
                    FROM {$wpdb->prefix}woocommerce_order_itemmeta
                    WHERE order_item_id = %d
                    AND meta_key = '_qty'
                ", $order_item->order_item_id));
                
                $total_bookings += (int) $quantity;
            }
        }
        
        return $total_bookings;
    }
    
    /**
     * Check if a specific slot is available for booking
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @param int $requested_quantity Requested quantity
     * @return bool Whether slot is available
     */
    public function is_slot_available($product_id, $slot_datetime, $requested_quantity = 1) {
        $capacity_info = $this->get_slot_capacity($product_id, $slot_datetime);
        return $capacity_info['available'] >= $requested_quantity;
    }
    
    /**
     * Reserve a slot for booking
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @param int $quantity Quantity to reserve
     * @param int $order_id Order ID
     * @return bool Success
     */
    public function reserve_slot($product_id, $slot_datetime, $quantity, $order_id) {
        if (!$this->is_slot_available($product_id, $slot_datetime, $quantity)) {
            return false;
        }
        
        // Create reservation record
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'wcefp_slot_reservations',
            [
                'product_id' => $product_id,
                'slot_datetime' => $slot_datetime,
                'quantity' => $quantity,
                'order_id' => $order_id,
                'status' => 'reserved',
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', time() + (15 * MINUTE_IN_SECONDS)) // 15 min expiry
            ],
            ['%d', '%s', '%d', '%d', '%s', '%s', '%s']
        );
        
        if ($result) {
            Logger::info("Slot reserved: Product {$product_id}, Slot {$slot_datetime}, Quantity {$quantity}, Order {$order_id}");
            
            // Trigger action for integrations
            do_action('wcefp_slot_reserved', $product_id, $slot_datetime, $quantity, $order_id);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get recurring schedule for a product with enhanced patterns
     * 
     * @param int $product_id Product ID
     * @param string $start_date Start date
     * @param string $end_date End date
     * @return array Schedule data
     */
    public function get_recurring_schedule($product_id, $start_date, $end_date) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return [];
        }
        
        // Get scheduling configuration
        $schedule_config = $this->get_schedule_configuration($product_id);
        
        $schedule = [];
        $current_date = \DateTime::createFromFormat('Y-m-d', $start_date);
        $end_date_obj = \DateTime::createFromFormat('Y-m-d', $end_date);
        
        // Apply timezone if specified
        if (!empty($schedule_config['timezone'])) {
            try {
                $timezone = new \DateTimeZone($schedule_config['timezone']);
                $current_date->setTimezone($timezone);
                $end_date_obj->setTimezone($timezone);
            } catch (\Exception $e) {
                Logger::log('warning', 'Invalid timezone: ' . $schedule_config['timezone']);
            }
        }
        
        while ($current_date <= $end_date_obj) {
            $date_str = $current_date->format('Y-m-d');
            
            // Check if date matches any recurrence pattern
            if ($this->date_matches_patterns($current_date, $schedule_config)) {
                // Check for exceptions/closures
                if (!$this->is_date_excluded($date_str, $schedule_config)) {
                    $slots = $this->get_available_slots($product_id, $date_str);
                    
                    if (!empty($slots)) {
                        $schedule[$date_str] = $this->enhance_slots_with_timezone($slots, $schedule_config);
                    }
                }
            }
            
            $current_date->add(new \DateInterval('P1D'));
        }
        
        return $schedule;
    }
    
    /**
     * Get occurrences for a product from database with caching
     * 
     * @param int $product_id Product ID
     * @param string $from_date Start date  
     * @param string $to_date End date
     * @param int $limit Result limit
     * @return array Occurrences
     */
    public function get_occurrences($product_id, $from_date, $to_date, $limit = 30) {
        // Use cache for occurrence queries
        $cache_key = "occurrences_{$product_id}_{$from_date}_{$to_date}_{$limit}";
        
        return \WCEFP\Core\Performance\QueryCacheManager::get_cached(
            $cache_key,
            \WCEFP\Core\Performance\QueryCacheManager::CACHE_GROUP_AVAILABILITY,
            function() use ($product_id, $from_date, $to_date, $limit) {
                return $this->get_occurrences_uncached($product_id, $from_date, $to_date, $limit);
            },
            \WCEFP\Core\Performance\QueryCacheManager::CACHE_DURATION_SHORT
        );
    }
    
    /**
     * Get occurrences for a product from database without caching
     * 
     * @param int $product_id Product ID
     * @param string $from_date Start date  
     * @param string $to_date End date
     * @param int $limit Result limit
     * @return array Occurrences
     */
    private function get_occurrences_uncached($product_id, $from_date, $to_date, $limit = 30) {
        global $wpdb;
        
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT o.*,
                   mp.post_title as meeting_point_name,
                   mp.post_content as meeting_point_description
            FROM {$occurrences_table} o
            LEFT JOIN {$wpdb->posts} mp ON o.meeting_point_id = mp.ID
            WHERE o.product_id = %d
            AND o.start_local >= %s
            AND o.start_local <= %s
            AND o.status = 'active'
            ORDER BY o.start_local ASC
            LIMIT %d
        ", $product_id, $from_date . ' 00:00:00', $to_date . ' 23:59:59', $limit), ARRAY_A);
        
        $occurrences = [];
        foreach ($results as $row) {
            $occurrences[] = [
                'id' => (int) $row['id'],
                'start_utc' => $row['start_utc'],
                'end_utc' => $row['end_utc'], 
                'start_local' => $row['start_local'],
                'end_local' => $row['end_local'],
                'capacity' => (int) $row['capacity'],
                'booked' => (int) $row['booked'],
                'held' => (int) $row['held'],
                'status' => $row['status'],
                'meeting_point_data' => [
                    'id' => $row['meeting_point_id'],
                    'name' => $row['meeting_point_name'],
                    'description' => $row['meeting_point_description']
                ]
            ];
        }
        
        return $occurrences;
    }
    
    /**
     * Get comprehensive schedule configuration
     * 
     * @param int $product_id Product ID
     * @return array Configuration array
     */
    private function get_schedule_configuration($product_id) {
        $product = wc_get_product($product_id);
        
        return [
            // Basic patterns
            'weekdays' => $product->get_meta('_wcefp_weekdays', true) ?: [],
            'time_slots' => $product->get_meta('_wcefp_time_slots', true) ?: '',
            'capacity' => (int) $product->get_meta('_wcefp_capacity', true) ?: 10,
            'duration' => (int) $product->get_meta('_wcefp_duration', true) ?: 60,
            'buffer_time' => (int) $product->get_meta('_wcefp_buffer_time', true) ?: 0,
            
            // Advanced patterns
            'recurrence_type' => $product->get_meta('_wcefp_recurrence_type', true) ?: 'weekly',
            'recurrence_interval' => (int) $product->get_meta('_wcefp_recurrence_interval', true) ?: 1,
            'specific_dates' => $product->get_meta('_wcefp_specific_dates', true) ?: [],
            'date_ranges' => $product->get_meta('_wcefp_date_ranges', true) ?: [],
            
            // Exceptions and closures
            'excluded_dates' => $product->get_meta('_wcefp_excluded_dates', true) ?: [],
            'closure_periods' => $this->get_closure_periods($product_id),
            'holiday_exclusions' => $product->get_meta('_wcefp_holiday_exclusions', true) ?: false,
            
            // Timezone and scheduling
            'timezone' => $product->get_meta('_wcefp_timezone', true) ?: wp_timezone_string(),
            'advance_booking_days' => (int) $product->get_meta('_wcefp_advance_booking_days', true) ?: 365,
            'min_advance_hours' => (int) $product->get_meta('_wcefp_min_advance_hours', true) ?: 2,
            'auto_release_hours' => (int) $product->get_meta('_wcefp_auto_release_hours', true) ?: 24,
            
            // Multi-day events
            'is_multi_day' => $product->get_meta('_wcefp_is_multi_day', true) === 'yes',
            'event_duration_days' => (int) $product->get_meta('_wcefp_event_duration_days', true) ?: 1,
            
            // Seasonal patterns
            'seasonal_patterns' => $product->get_meta('_wcefp_seasonal_patterns', true) ?: [],
            'weather_dependent' => $product->get_meta('_wcefp_weather_dependent', true) === 'yes'
        ];
    }
    
    /**
     * Check if date matches any recurrence patterns
     * 
     * @param \DateTime $date Date to check
     * @param array $config Schedule configuration
     * @return bool Whether date matches
     */
    private function date_matches_patterns($date, $config) {
        $recurrence_type = $config['recurrence_type'];
        $interval = $config['recurrence_interval'];
        
        switch ($recurrence_type) {
            case 'weekly':
                return $this->matches_weekly_pattern($date, $config);
                
            case 'daily':
                return $this->matches_daily_pattern($date, $config);
                
            case 'monthly':
                return $this->matches_monthly_pattern($date, $config);
                
            case 'specific':
                return $this->matches_specific_dates($date, $config);
                
            case 'seasonal':
                return $this->matches_seasonal_pattern($date, $config);
                
            default:
                return $this->matches_weekly_pattern($date, $config); // Fallback
        }
    }
    
    /**
     * Check if date matches weekly recurrence pattern
     * 
     * @param \DateTime $date Date to check
     * @param array $config Configuration
     * @return bool Match status
     */
    private function matches_weekly_pattern($date, $config) {
        $weekdays = $config['weekdays'];
        if (empty($weekdays)) {
            return false;
        }
        
        $day_of_week = (int) $date->format('w'); // 0 = Sunday
        return in_array($day_of_week, $weekdays);
    }
    
    /**
     * Check if date matches daily recurrence pattern
     * 
     * @param \DateTime $date Date to check
     * @param array $config Configuration
     * @return bool Match status
     */
    private function matches_daily_pattern($date, $config) {
        $interval = $config['recurrence_interval'];
        
        // For daily patterns, check if it's within allowed date ranges
        foreach ($config['date_ranges'] as $range) {
            $start = \DateTime::createFromFormat('Y-m-d', $range['start']);
            $end = \DateTime::createFromFormat('Y-m-d', $range['end']);
            
            if ($start && $end && $date >= $start && $date <= $end) {
                // Check interval (every N days)
                $diff_days = $start->diff($date)->days;
                return ($diff_days % $interval) === 0;
            }
        }
        
        return false;
    }
    
    /**
     * Check if date matches monthly recurrence pattern
     * 
     * @param \DateTime $date Date to check
     * @param array $config Configuration
     * @return bool Match status
     */
    private function matches_monthly_pattern($date, $config) {
        $monthly_patterns = $config['monthly_patterns'] ?? [];
        
        if (empty($monthly_patterns)) {
            return false;
        }
        
        $day_of_month = (int) $date->format('j');
        $day_of_week = (int) $date->format('w');
        $week_of_month = ceil($day_of_month / 7);
        
        foreach ($monthly_patterns as $pattern) {
            if ($pattern['type'] === 'day_of_month' && $pattern['day'] === $day_of_month) {
                return true;
            }
            
            if ($pattern['type'] === 'week_of_month' && 
                $pattern['week'] === $week_of_month && 
                $pattern['day_of_week'] === $day_of_week) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if date matches specific dates list
     * 
     * @param \DateTime $date Date to check
     * @param array $config Configuration
     * @return bool Match status
     */
    private function matches_specific_dates($date, $config) {
        $specific_dates = $config['specific_dates'];
        $date_str = $date->format('Y-m-d');
        
        return in_array($date_str, $specific_dates);
    }
    
    /**
     * Check if date matches seasonal patterns
     * 
     * @param \DateTime $date Date to check
     * @param array $config Configuration
     * @return bool Match status
     */
    private function matches_seasonal_pattern($date, $config) {
        $seasonal_patterns = $config['seasonal_patterns'];
        if (empty($seasonal_patterns)) {
            return false;
        }
        
        $month_day = $date->format('m-d');
        $day_of_year = (int) $date->format('z');
        
        foreach ($seasonal_patterns as $pattern) {
            switch ($pattern['type']) {
                case 'date_range':
                    $start_day = \DateTime::createFromFormat('m-d', $pattern['start'])->format('z');
                    $end_day = \DateTime::createFromFormat('m-d', $pattern['end'])->format('z');
                    
                    // Handle year wrap (e.g., Dec-Jan)
                    if ($start_day <= $end_day) {
                        if ($day_of_year >= $start_day && $day_of_year <= $end_day) {
                            return true;
                        }
                    } else {
                        if ($day_of_year >= $start_day || $day_of_year <= $end_day) {
                            return true;
                        }
                    }
                    break;
                    
                case 'months':
                    $current_month = (int) $date->format('n');
                    if (in_array($current_month, $pattern['months'])) {
                        return true;
                    }
                    break;
            }
        }
        
        return false;
    }
    
    /**
     * Check if date is excluded due to closures or exceptions
     * 
     * @param string $date_str Date string (Y-m-d)
     * @param array $config Configuration
     * @return bool Whether date is excluded
     */
    private function is_date_excluded($date_str, $config) {
        // Check explicit excluded dates
        if (in_array($date_str, $config['excluded_dates'])) {
            return true;
        }
        
        // Check closure periods
        $date_obj = \DateTime::createFromFormat('Y-m-d', $date_str);
        foreach ($config['closure_periods'] as $period) {
            $start = \DateTime::createFromFormat('Y-m-d', $period['start']);
            $end = \DateTime::createFromFormat('Y-m-d', $period['end']);
            
            if ($start && $end && $date_obj >= $start && $date_obj <= $end) {
                return true;
            }
        }
        
        // Check holiday exclusions
        if ($config['holiday_exclusions']) {
            if ($this->is_holiday_date($date_str)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get closure periods from settings or integrations
     * 
     * @param int $product_id Product ID
     * @return array Closure periods
     */
    private function get_closure_periods($product_id) {
        // Get global closures from WCEFP settings
        $global_closures = get_option('wcefp_global_closures', []);
        
        // Get product-specific closures
        $product_closures = get_post_meta($product_id, '_wcefp_closures', true) ?: [];
        
        // Merge and return
        return array_merge($global_closures, $product_closures);
    }
    
    /**
     * Check if date is a holiday (basic implementation)
     * 
     * @param string $date_str Date string
     * @return bool Holiday status
     */
    private function is_holiday_date($date_str) {
        // Basic holiday check - can be enhanced with holiday APIs
        $holidays = [
            '01-01', // New Year
            '12-25', // Christmas
            '12-26'  // Boxing Day
        ];
        
        $month_day = substr($date_str, 5); // Get MM-DD part
        return in_array($month_day, $holidays);
    }
    
    /**
     * Enhance slots with timezone information
     * 
     * @param array $slots Slot data
     * @param array $config Configuration
     * @return array Enhanced slots
     */
    private function enhance_slots_with_timezone($slots, $config) {
        if (empty($config['timezone']) || $config['timezone'] === wp_timezone_string()) {
            return $slots; // No conversion needed
        }
        
        try {
            $product_timezone = new \DateTimeZone($config['timezone']);
            $site_timezone = new \DateTimeZone(wp_timezone_string());
            
            foreach ($slots as &$slot) {
                if (isset($slot['datetime'])) {
                    $dt = \DateTime::createFromFormat('Y-m-d H:i', $slot['datetime'], $product_timezone);
                    if ($dt) {
                        $dt->setTimezone($site_timezone);
                        $slot['datetime_local'] = $dt->format('Y-m-d H:i');
                        $slot['timezone'] = $config['timezone'];
                    }
                }
            }
        } catch (\Exception $e) {
            Logger::log('warning', 'Timezone conversion failed: ' . $e->getMessage());
        }
        
        return $slots;
    }
    
    /**
     * Generate occurrence slots with enhanced patterns
     * 
     * @param int $product_id Product ID
     * @param string $from_date Start date
     * @param string $to_date End date
     * @param int $limit Maximum number of occurrences
     * @return array Generated occurrences
     */
    public function generate_occurrence_slots($product_id, $from_date, $to_date, $limit = 100) {
        global $wpdb;
        
        $config = $this->get_schedule_configuration($product_id);
        $occurrences_table = $wpdb->prefix . 'wcefp_occurrences';
        
        $current_date = \DateTime::createFromFormat('Y-m-d', $from_date);
        $end_date = \DateTime::createFromFormat('Y-m-d', $to_date);
        $generated = 0;
        $occurrences = [];
        
        // Set timezone
        try {
            $timezone = new \DateTimeZone($config['timezone']);
            $current_date->setTimezone($timezone);
            $end_date->setTimezone($timezone);
        } catch (\Exception $e) {
            $timezone = new \DateTimeZone(wp_timezone_string());
        }
        
        while ($current_date <= $end_date && $generated < $limit) {
            $date_str = $current_date->format('Y-m-d');
            
            // Check if date matches patterns and is not excluded
            if ($this->date_matches_patterns($current_date, $config) && 
                !$this->is_date_excluded($date_str, $config)) {
                
                // Generate slots for this date
                $slots = $this->parse_time_slots($config['time_slots']);
                
                foreach ($slots as $slot_time) {
                    if ($generated >= $limit) break;
                    
                    $start_datetime = $current_date->format('Y-m-d') . ' ' . $slot_time;
                    $start_utc = $this->convert_to_utc($start_datetime, $timezone);
                    $end_utc = $this->calculate_end_time($start_utc, $config['duration']);
                    
                    // Check if occurrence already exists
                    $exists = $wpdb->get_var($wpdb->prepare("
                        SELECT id FROM {$occurrences_table}
                        WHERE product_id = %d
                        AND start_utc = %s
                    ", $product_id, $start_utc));
                    
                    if (!$exists) {
                        // Create new occurrence
                        $occurrence_data = [
                            'product_id' => $product_id,
                            'start_utc' => $start_utc,
                            'end_utc' => $end_utc,
                            'start_local' => $start_datetime,
                            'end_local' => $this->calculate_end_time($start_datetime, $config['duration']),
                            'timezone_string' => $config['timezone'],
                            'capacity' => $config['capacity'],
                            'booked' => 0,
                            'held' => 0,
                            'status' => 'active',
                            'meeting_point_id' => get_post_meta($product_id, '_wcefp_meeting_point_id', true) ?: null,
                            'meta' => maybe_serialize(['buffer_time' => $config['buffer_time']])
                        ];
                        
                        $result = $wpdb->insert($occurrences_table, $occurrence_data);
                        
                        if ($result) {
                            $occurrence_data['id'] = $wpdb->insert_id;
                            $occurrences[] = $occurrence_data;
                            $generated++;
                            
                            Logger::log('debug', "Generated occurrence: {$start_datetime} for product {$product_id}");
                        }
                    }
                }
            }
            
            $current_date->add(new \DateInterval('P1D'));
        }
        
        Logger::log('info', "Generated {$generated} occurrences for product {$product_id}");
        
        return $occurrences;
    }
    
    /**
     * Convert local datetime to UTC
     * 
     * @param string $datetime Local datetime string
     * @param \DateTimeZone $timezone Timezone object
     * @return string UTC datetime string
     */
    private function convert_to_utc($datetime, $timezone) {
        try {
            $dt = \DateTime::createFromFormat('Y-m-d H:i', $datetime, $timezone);
            $dt->setTimezone(new \DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Logger::log('warning', 'UTC conversion failed: ' . $e->getMessage());
            return $datetime;
        }
    }
    
    /**
     * Calculate end time based on start time and duration
     * 
     * @param string $start_datetime Start datetime
     * @param int $duration_minutes Duration in minutes
     * @return string End datetime
     */
    private function calculate_end_time($start_datetime, $duration_minutes) {
        try {
            $dt = new \DateTime($start_datetime);
            $dt->add(new \DateInterval('PT' . $duration_minutes . 'M'));
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            Logger::log('warning', 'End time calculation failed: ' . $e->getMessage());
            return $start_datetime;
        }
    }
    
    /**
     * Create database table for slot reservations
     * 
     * @return void
     */
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_slot_reservations';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            product_id bigint(20) UNSIGNED NOT NULL,
            slot_datetime datetime NOT NULL,
            quantity int(11) NOT NULL DEFAULT 1,
            order_id bigint(20) UNSIGNED NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'reserved',
            created_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY product_slot (product_id, slot_datetime),
            KEY order_id (order_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Clean up expired reservations
     * 
     * @return int Number of cleaned up reservations
     */
    public function cleanup_expired_reservations() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_slot_reservations';
        
        $result = $wpdb->query("
            DELETE FROM {$table_name}
            WHERE status = 'reserved' 
            AND expires_at < NOW()
        ");
        
        if ($result) {
            Logger::info("Cleaned up {$result} expired slot reservations");
        }
        
        return (int) $result;
    }
}