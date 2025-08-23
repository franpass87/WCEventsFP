<?php
/**
 * Booking Repository
 * 
 * Handles database operations for bookings
 * Part of Core Database system
 *
 * @package WCEFP
 * @subpackage Core\Database
 * @since 2.2.0
 */

namespace WCEFP\Core\Database;

class BookingRepository {
    
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'posts';
    }
    
    /**
     * Get booking by ID
     *
     * @param int $booking_id Booking ID
     * @return object|null Booking object or null
     */
    public function get_booking($booking_id) {
        $booking = get_post($booking_id);
        
        if (!$booking || $booking->post_type !== 'booking') {
            return null;
        }
        
        return $booking;
    }
    
    /**
     * Get bookings by event ID
     *
     * @param int $event_id Event ID
     * @param array $args Additional arguments
     * @return array Bookings array
     */
    public function get_bookings_by_event($event_id, $args = []) {
        $defaults = [
            'post_type' => 'booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_event_id',
                    'value' => $event_id,
                    'compare' => '='
                ]
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        return get_posts($args);
    }
    
    /**
     * Get bookings by date range
     *
     * @param string $start_date Start date
     * @param string $end_date End date
     * @param array $args Additional arguments
     * @return array Bookings array
     */
    public function get_bookings_by_date_range($start_date, $end_date, $args = []) {
        $defaults = [
            'post_type' => 'booking',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_event_date_time',
                    'value' => [$start_date, $end_date],
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME'
                ]
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        return get_posts($args);
    }
    
    /**
     * Create new booking
     *
     * @param array $booking_data Booking data
     * @return int|WP_Error Booking ID or error
     */
    public function create_booking($booking_data) {
        $defaults = [
            'post_type' => 'booking',
            'post_status' => 'publish',
            'post_title' => '',
            'post_content' => '',
            'meta_input' => []
        ];
        
        $booking_data = wp_parse_args($booking_data, $defaults);
        
        $booking_id = wp_insert_post($booking_data, true);
        
        if (is_wp_error($booking_id)) {
            return $booking_id;
        }
        
        // Trigger action for booking created
        do_action('wcefp_booking_created', $booking_id, $booking_data);
        
        return $booking_id;
    }
    
    /**
     * Update booking
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Updated booking data
     * @return int|WP_Error Booking ID or error
     */
    public function update_booking($booking_id, $booking_data) {
        $booking_data['ID'] = $booking_id;
        
        $result = wp_update_post($booking_data, true);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Trigger action for booking updated
        do_action('wcefp_booking_updated', $booking_id, $booking_data);
        
        return $result;
    }
    
    /**
     * Delete booking
     *
     * @param int $booking_id Booking ID
     * @param bool $force_delete Whether to force delete or just trash
     * @return WP_Post|false|null Post object or false/null on failure
     */
    public function delete_booking($booking_id, $force_delete = false) {
        // Trigger action before booking deleted
        do_action('wcefp_before_booking_deleted', $booking_id);
        
        $result = wp_delete_post($booking_id, $force_delete);
        
        if ($result) {
            // Trigger action after booking deleted
            do_action('wcefp_booking_deleted', $booking_id);
        }
        
        return $result;
    }
    
    /**
     * Get booking statistics
     *
     * @param array $filters Filter criteria
     * @return array Statistics
     */
    public function get_booking_statistics($filters = []) {
        global $wpdb;
        
        $where_clauses = ["p.post_type = 'booking'"];
        $join_clauses = [];
        
        // Apply date filters
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $join_clauses[] = "INNER JOIN {$wpdb->postmeta} pm_date ON p.ID = pm_date.post_id AND pm_date.meta_key = '_event_date_time'";
            $where_clauses[] = $wpdb->prepare(
                "pm_date.meta_value BETWEEN %s AND %s",
                $filters['start_date'],
                $filters['end_date']
            );
        }
        
        // Apply event filter
        if (!empty($filters['event_id'])) {
            $join_clauses[] = "INNER JOIN {$wpdb->postmeta} pm_event ON p.ID = pm_event.post_id AND pm_event.meta_key = '_event_id'";
            $where_clauses[] = $wpdb->prepare(
                "pm_event.meta_value = %d",
                $filters['event_id']
            );
        }
        
        // Apply status filter
        if (!empty($filters['status'])) {
            $where_clauses[] = $wpdb->prepare(
                "p.post_status = %s",
                $filters['status']
            );
        }
        
        $join_sql = implode(' ', $join_clauses);
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "
            SELECT 
                COUNT(*) as total_bookings,
                COUNT(CASE WHEN p.post_status = 'publish' THEN 1 END) as confirmed_bookings,
                COUNT(CASE WHEN p.post_status = 'pending' THEN 1 END) as pending_bookings,
                COUNT(CASE WHEN p.post_status = 'cancelled' THEN 1 END) as cancelled_bookings
            FROM {$wpdb->posts} p
            {$join_sql}
            WHERE {$where_sql}
        ";
        
        $results = $wpdb->get_row($sql, ARRAY_A);
        
        return $results ?: [
            'total_bookings' => 0,
            'confirmed_bookings' => 0,
            'pending_bookings' => 0,
            'cancelled_bookings' => 0
        ];
    }
    
    /**
     * Search bookings
     *
     * @param string $search_term Search term
     * @param array $args Additional arguments
     * @return array Search results
     */
    public function search_bookings($search_term, $args = []) {
        $defaults = [
            'post_type' => 'booking',
            'post_status' => 'any',
            'posts_per_page' => 50,
            's' => $search_term,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_customer_email',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => '_customer_name',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => '_booking_reference',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ]
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        return get_posts($args);
    }
}