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
     * Get available time slots for a product on a specific date
     * 
     * @param int $product_id Product ID
     * @param string $date Date in Y-m-d format
     * @return array Available time slots with capacity info
     */
    public function get_available_slots($product_id, $date) {
        if (!SecurityManager::can_user('manage_wcefp_events')) {
            return [];
        }
        
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
     * Get recurring schedule for a product
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
        
        $weekdays = $product->get_meta('_wcefp_weekdays', true) ?: [];
        $time_slots = $product->get_meta('_wcefp_time_slots', true) ?: '';
        
        $schedule = [];
        $current_date = \DateTime::createFromFormat('Y-m-d', $start_date);
        $end_date_obj = \DateTime::createFromFormat('Y-m-d', $end_date);
        
        while ($current_date <= $end_date_obj) {
            $day_of_week = (int) $current_date->format('w');
            
            if (in_array($day_of_week, $weekdays)) {
                $date_str = $current_date->format('Y-m-d');
                $slots = $this->get_available_slots($product_id, $date_str);
                
                if (!empty($slots)) {
                    $schedule[$date_str] = $slots;
                }
            }
            
            $current_date->add(new \DateInterval('P1D'));
        }
        
        return $schedule;
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