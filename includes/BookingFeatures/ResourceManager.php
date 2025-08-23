<?php
/**
 * Resource Management System
 * 
 * Handles resource availability, conflict detection, and scheduling
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage BookingFeatures
 * @since 2.2.0
 */

namespace WCEFP\BookingFeatures;

use WCEFP\Core\Database\BookingRepository;
use WCEFP\Utils\DateTimeHelper;

class ResourceManager {
    
    private $booking_repository;
    private $datetime_helper;
    
    public function __construct() {
        $this->booking_repository = new BookingRepository();
        $this->datetime_helper = new DateTimeHelper();
        
        add_action('wp_ajax_wcefp_check_availability', [$this, 'check_availability_ajax']);
        add_action('wp_ajax_wcefp_get_resource_calendar', [$this, 'get_resource_calendar_ajax']);
        add_action('wcefp_before_booking_save', [$this, 'validate_resource_availability'], 10, 2);
        
        // Register shortcodes
        add_shortcode('wcefp_availability_calendar', [$this, 'render_availability_calendar']);
        add_shortcode('wcefp_resource_status', [$this, 'render_resource_status']);
    }
    
    /**
     * Check resource availability for given date/time
     *
     * @param int $event_id Event ID
     * @param string $date_time Event date/time
     * @param array $resources Required resources
     * @return array|WP_Error Availability data or error
     */
    public function check_availability($event_id, $date_time, $resources = []) {
        $availability = [
            'available' => true,
            'conflicts' => [],
            'resource_status' => [],
            'recommendations' => []
        ];
        
        // Get event details
        $event = get_post($event_id);
        if (!$event) {
            return new \WP_Error('invalid_event', __('Event not found', 'wceventsfp'));
        }
        
        // Parse datetime
        $event_datetime = new \DateTime($date_time);
        $event_duration = get_post_meta($event_id, '_event_duration', true) ?: 60; // Default 60 minutes
        $event_end = clone $event_datetime;
        $event_end->add(new \DateInterval('PT' . $event_duration . 'M'));
        
        // Get event resources if not provided
        if (empty($resources)) {
            $resources = $this->get_event_resources($event_id);
        }
        
        // Check each resource
        foreach ($resources as $resource) {
            $resource_availability = $this->check_resource_availability(
                $resource['id'], 
                $event_datetime, 
                $event_end,
                $event_id
            );
            
            $availability['resource_status'][$resource['id']] = $resource_availability;
            
            if (!$resource_availability['available']) {
                $availability['available'] = false;
                $availability['conflicts'] = array_merge(
                    $availability['conflicts'], 
                    $resource_availability['conflicts']
                );
            }
        }
        
        // Generate recommendations if not available
        if (!$availability['available']) {
            $availability['recommendations'] = $this->generate_availability_recommendations(
                $event_id, 
                $event_datetime, 
                $resources
            );
        }
        
        // Check capacity limits
        $capacity_check = $this->check_capacity_limits($event_id, $date_time);
        if (!$capacity_check['available']) {
            $availability['available'] = false;
            $availability['capacity_exceeded'] = true;
            $availability['capacity_data'] = $capacity_check;
        }
        
        return $availability;
    }
    
    /**
     * Check availability for a specific resource
     *
     * @param int $resource_id Resource ID
     * @param DateTime $start_time Start time
     * @param DateTime $end_time End time
     * @param int $exclude_booking_id Booking to exclude from check
     * @return array Availability data
     */
    private function check_resource_availability($resource_id, $start_time, $end_time, $exclude_booking_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wcefp_resource_bookings';
        
        // Query for conflicting bookings
        $sql = $wpdb->prepare("
            SELECT rb.*, p.post_title as event_title, p.post_status
            FROM {$table_name} rb
            LEFT JOIN {$wpdb->posts} p ON rb.event_id = p.ID
            WHERE rb.resource_id = %d
            AND rb.status = 'active'
            AND (
                (rb.start_time <= %s AND rb.end_time > %s)
                OR (rb.start_time < %s AND rb.end_time >= %s)
                OR (rb.start_time >= %s AND rb.start_time < %s)
            )",
            $resource_id,
            $start_time->format('Y-m-d H:i:s'),
            $start_time->format('Y-m-d H:i:s'),
            $end_time->format('Y-m-d H:i:s'),
            $end_time->format('Y-m-d H:i:s'),
            $start_time->format('Y-m-d H:i:s'),
            $end_time->format('Y-m-d H:i:s')
        );
        
        if ($exclude_booking_id) {
            $sql .= $wpdb->prepare(" AND rb.booking_id != %d", $exclude_booking_id);
        }
        
        $conflicts = $wpdb->get_results($sql, ARRAY_A);
        
        // Get resource details
        $resource = $this->get_resource($resource_id);
        
        return [
            'available' => empty($conflicts),
            'resource_id' => $resource_id,
            'resource_name' => $resource['name'] ?? '',
            'conflicts' => $conflicts,
            'utilization' => $this->calculate_resource_utilization($resource_id, $start_time)
        ];
    }
    
    /**
     * Check capacity limits for event/date
     *
     * @param int $event_id Event ID
     * @param string $date_time Event date/time
     * @return array Capacity data
     */
    private function check_capacity_limits($event_id, $date_time) {
        global $wpdb;
        
        // Get event capacity limits
        $max_capacity = get_post_meta($event_id, '_max_capacity', true) ?: 999;
        $min_capacity = get_post_meta($event_id, '_min_capacity', true) ?: 1;
        
        // Count existing bookings for this date/time
        $existing_bookings = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'booking'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_event_id'
            AND pm.meta_value = %d
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID 
                AND pm2.meta_key = '_event_date_time'
                AND pm2.meta_value = %s
            )",
            $event_id,
            $date_time
        ));
        
        // Get pending bookings (in cart but not completed)
        $pending_bookings = $this->get_pending_booking_count($event_id, $date_time);
        
        $total_bookings = $existing_bookings + $pending_bookings;
        $available_spots = max(0, $max_capacity - $total_bookings);
        
        return [
            'available' => $available_spots > 0,
            'max_capacity' => $max_capacity,
            'min_capacity' => $min_capacity,
            'current_bookings' => $existing_bookings,
            'pending_bookings' => $pending_bookings,
            'available_spots' => $available_spots,
            'utilization_percentage' => ($total_bookings / $max_capacity) * 100
        ];
    }
    
    /**
     * Generate availability recommendations
     *
     * @param int $event_id Event ID
     * @param DateTime $requested_time Requested time
     * @param array $resources Required resources
     * @return array Recommendations
     */
    private function generate_availability_recommendations($event_id, $requested_time, $resources) {
        $recommendations = [];
        
        // Find alternative time slots within same day
        $same_day_alternatives = $this->find_alternative_slots(
            $event_id, 
            $requested_time, 
            $resources,
            'same_day'
        );
        
        if (!empty($same_day_alternatives)) {
            $recommendations[] = [
                'type' => 'same_day_alternative',
                'title' => __('Alternative times today', 'wceventsfp'),
                'options' => $same_day_alternatives
            ];
        }
        
        // Find alternative dates within same week
        $same_week_alternatives = $this->find_alternative_slots(
            $event_id, 
            $requested_time, 
            $resources,
            'same_week'
        );
        
        if (!empty($same_week_alternatives)) {
            $recommendations[] = [
                'type' => 'same_week_alternative',
                'title' => __('Alternative dates this week', 'wceventsfp'),
                'options' => $same_week_alternatives
            ];
        }
        
        // Suggest similar events
        $similar_events = $this->find_similar_available_events($event_id, $requested_time);
        if (!empty($similar_events)) {
            $recommendations[] = [
                'type' => 'similar_events',
                'title' => __('Similar available events', 'wceventsfp'),
                'options' => $similar_events
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Find alternative time slots
     *
     * @param int $event_id Event ID
     * @param DateTime $requested_time Requested time
     * @param array $resources Required resources
     * @param string $scope Search scope (same_day, same_week, etc.)
     * @return array Alternative slots
     */
    private function find_alternative_slots($event_id, $requested_time, $resources, $scope = 'same_day') {
        $alternatives = [];
        $event_duration = get_post_meta($event_id, '_event_duration', true) ?: 60;
        
        // Define search range based on scope
        $search_start = clone $requested_time;
        $search_end = clone $requested_time;
        
        switch ($scope) {
            case 'same_day':
                $search_start->setTime(9, 0); // 9 AM
                $search_end->setTime(18, 0); // 6 PM
                $increment = new \DateInterval('PT1H'); // 1 hour increments
                break;
                
            case 'same_week':
                $search_start->modify('monday this week');
                $search_end->modify('sunday this week');
                $increment = new \DateInterval('P1D'); // 1 day increments
                break;
                
            default:
                return [];
        }
        
        $current_time = clone $search_start;
        
        while ($current_time <= $search_end) {
            // Skip the originally requested time
            if ($current_time == $requested_time) {
                $current_time->add($increment);
                continue;
            }
            
            $availability = $this->check_availability(
                $event_id, 
                $current_time->format('Y-m-d H:i:s'), 
                $resources
            );
            
            if (is_array($availability) && $availability['available']) {
                $alternatives[] = [
                    'datetime' => $current_time->format('Y-m-d H:i:s'),
                    'formatted_date' => $current_time->format('M j, Y'),
                    'formatted_time' => $current_time->format('g:i A'),
                    'availability_score' => $this->calculate_availability_score($availability)
                ];
                
                // Limit results
                if (count($alternatives) >= 5) {
                    break;
                }
            }
            
            $current_time->add($increment);
        }
        
        // Sort by availability score (best first)
        usort($alternatives, function($a, $b) {
            return $b['availability_score'] <=> $a['availability_score'];
        });
        
        return $alternatives;
    }
    
    /**
     * Calculate availability score for ranking alternatives
     *
     * @param array $availability Availability data
     * @return float Score (0-100)
     */
    private function calculate_availability_score($availability) {
        $score = 100;
        
        // Reduce score for each conflict
        $score -= count($availability['conflicts']) * 10;
        
        // Consider resource utilization
        foreach ($availability['resource_status'] as $resource_status) {
            $utilization = $resource_status['utilization'] ?? 0;
            $score -= ($utilization / 100) * 20; // Reduce score for high utilization
        }
        
        // Consider capacity utilization if available
        if (isset($availability['capacity_data'])) {
            $capacity_utilization = $availability['capacity_data']['utilization_percentage'] ?? 0;
            $score -= ($capacity_utilization / 100) * 10;
        }
        
        return max(0, $score);
    }
    
    /**
     * Get event resources
     *
     * @param int $event_id Event ID
     * @return array Resources
     */
    private function get_event_resources($event_id) {
        $resources = get_post_meta($event_id, '_required_resources', true);
        
        if (empty($resources)) {
            return [];
        }
        
        return is_array($resources) ? $resources : (json_decode($resources, true) ?: []);
    }
    
    /**
     * Get resource details
     *
     * @param int $resource_id Resource ID
     * @return array Resource details
     */
    private function get_resource($resource_id) {
        global $wpdb;
        
        $resource = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wcefp_resources WHERE id = %d",
            $resource_id
        ), ARRAY_A);
        
        return $resource ?: [];
    }
    
    /**
     * Calculate resource utilization for a given time period
     *
     * @param int $resource_id Resource ID
     * @param DateTime $date_time Reference date/time
     * @return float Utilization percentage
     */
    private function calculate_resource_utilization($resource_id, $date_time) {
        global $wpdb;
        
        // Calculate utilization for the day
        $day_start = clone $date_time;
        $day_start->setTime(0, 0, 0);
        $day_end = clone $day_start;
        $day_end->add(new \DateInterval('P1D'));
        
        $table_name = $wpdb->prefix . 'wcefp_resource_bookings';
        
        $total_booked_time = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(TIMESTAMPDIFF(MINUTE, start_time, end_time))
            FROM {$table_name}
            WHERE resource_id = %d
            AND status = 'active'
            AND start_time >= %s
            AND end_time <= %s",
            $resource_id,
            $day_start->format('Y-m-d H:i:s'),
            $day_end->format('Y-m-d H:i:s')
        ));
        
        // Assume 8 hours working day (480 minutes)
        $working_minutes = 480;
        $utilization = ($total_booked_time / $working_minutes) * 100;
        
        return min(100, max(0, $utilization));
    }
    
    /**
     * Get pending booking count (cart items not yet completed)
     *
     * @param int $event_id Event ID
     * @param string $date_time Event date/time
     * @return int Pending count
     */
    private function get_pending_booking_count($event_id, $date_time) {
        // This would integrate with WooCommerce cart or session data
        // For now, return 0 but in production this should check:
        // - Items in WooCommerce cart
        // - Temporary reservations (expired after X minutes)
        // - Bookings in "pending" status
        
        global $wpdb;
        
        $pending_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'booking'
            AND p.post_status = 'pending'
            AND pm.meta_key = '_event_id'
            AND pm.meta_value = %d
            AND EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID 
                AND pm2.meta_key = '_event_date_time'
                AND pm2.meta_value = %s
            )
            AND p.post_date > DATE_SUB(NOW(), INTERVAL 15 MINUTE)", // Only recent pending bookings
            $event_id,
            $date_time
        ));
        
        return intval($pending_count);
    }
    
    /**
     * Find similar available events
     *
     * @param int $event_id Original event ID
     * @param DateTime $requested_time Requested time
     * @return array Similar events
     */
    private function find_similar_available_events($event_id, $requested_time) {
        // Get event categories/tags
        $event_categories = wp_get_post_terms($event_id, 'event_category', ['fields' => 'ids']);
        $event_tags = wp_get_post_terms($event_id, 'event_tag', ['fields' => 'ids']);
        
        $args = [
            'post_type' => 'event',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'post__not_in' => [$event_id],
            'meta_query' => [
                [
                    'key' => '_event_availability',
                    'value' => 'available',
                    'compare' => '='
                ]
            ]
        ];
        
        // Add taxonomy query if categories/tags exist
        if (!empty($event_categories) || !empty($event_tags)) {
            $tax_query = ['relation' => 'OR'];
            
            if (!empty($event_categories)) {
                $tax_query[] = [
                    'taxonomy' => 'event_category',
                    'field' => 'term_id',
                    'terms' => $event_categories
                ];
            }
            
            if (!empty($event_tags)) {
                $tax_query[] = [
                    'taxonomy' => 'event_tag',
                    'field' => 'term_id',
                    'terms' => $event_tags
                ];
            }
            
            $args['tax_query'] = $tax_query;
        }
        
        $similar_events = get_posts($args);
        $results = [];
        
        foreach ($similar_events as $event) {
            // Check if this event has availability around the requested time
            $event_availability = $this->check_availability(
                $event->ID, 
                $requested_time->format('Y-m-d H:i:s')
            );
            
            if (is_array($event_availability) && $event_availability['available']) {
                $results[] = [
                    'event_id' => $event->ID,
                    'title' => $event->post_title,
                    'permalink' => get_permalink($event->ID),
                    'availability_score' => $this->calculate_availability_score($event_availability)
                ];
            }
        }
        
        // Sort by availability score
        usort($results, function($a, $b) {
            return $b['availability_score'] <=> $a['availability_score'];
        });
        
        return array_slice($results, 0, 3); // Return top 3
    }
    
    /**
     * AJAX handler for availability check
     */
    public function check_availability_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcefp_availability_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed', 'wceventsfp')]);
        }
        
        $event_id = absint($_POST['event_id']);
        $date_time = sanitize_text_field($_POST['date_time']);
        
        if (!$event_id || !$date_time) {
            wp_send_json_error(['message' => __('Missing required parameters', 'wceventsfp')]);
        }
        
        $availability = $this->check_availability($event_id, $date_time);
        
        if (is_wp_error($availability)) {
            wp_send_json_error(['message' => $availability->get_error_message()]);
        }
        
        wp_send_json_success($availability);
    }
    
    /**
     * Render availability calendar shortcode
     */
    public function render_availability_calendar($atts) {
        $atts = shortcode_atts([
            'event_id' => 0,
            'view' => 'month', // month, week, day
            'theme' => 'default'
        ], $atts);
        
        if (!$atts['event_id']) {
            return '<p>' . __('Event ID is required', 'wceventsfp') . '</p>';
        }
        
        // Enqueue calendar assets
        wp_enqueue_script('wcefp-availability-calendar', 
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/availability-calendar.js', 
            ['jquery'], '2.2.0', true
        );
        wp_enqueue_style('wcefp-availability-calendar', 
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/availability-calendar.css', 
            [], '2.2.0'
        );
        
        // Localize script
        wp_localize_script('wcefp-availability-calendar', 'wcefp_calendar', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_availability_nonce'),
            'event_id' => $atts['event_id'],
            'strings' => [
                'loading' => __('Loading availability...', 'wceventsfp'),
                'available' => __('Available', 'wceventsfp'),
                'unavailable' => __('Unavailable', 'wceventsfp'),
                'limited' => __('Limited availability', 'wceventsfp')
            ]
        ]);
        
        ob_start();
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/availability-calendar.php';
        return ob_get_clean();
    }
    
    /**
     * Validate resource availability before booking save
     */
    public function validate_resource_availability($booking_id, $booking_data) {
        $event_id = $booking_data['event_id'] ?? 0;
        $date_time = $booking_data['date_time'] ?? '';
        
        if (!$event_id || !$date_time) {
            return; // Skip validation if data is incomplete
        }
        
        $availability = $this->check_availability($event_id, $date_time);
        
        if (is_wp_error($availability)) {
            wp_die($availability->get_error_message());
        }
        
        if (!$availability['available']) {
            $message = __('This time slot is no longer available. Please select a different time.', 'wceventsfp');
            
            if (!empty($availability['recommendations'])) {
                $message .= ' ' . __('Alternative times are available.', 'wceventsfp');
            }
            
            wp_die($message);
        }
    }
    
    /**
     * Create resource management database tables
     */
    public static function create_resource_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Resources table
        $resources_table = $wpdb->prefix . 'wcefp_resources';
        $resources_sql = "CREATE TABLE $resources_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type varchar(100) NOT NULL,
            description text,
            capacity int(11) DEFAULT 1,
            cost_per_hour decimal(10,2) DEFAULT 0.00,
            availability_schedule longtext,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY status (status)
        ) $charset_collate;";
        
        // Resource bookings table
        $bookings_table = $wpdb->prefix . 'wcefp_resource_bookings';
        $bookings_sql = "CREATE TABLE $bookings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            resource_id bigint(20) unsigned NOT NULL,
            booking_id bigint(20) unsigned NOT NULL,
            event_id bigint(20) unsigned NOT NULL,
            start_time datetime NOT NULL,
            end_time datetime NOT NULL,
            status varchar(20) DEFAULT 'active',
            notes text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY resource_id (resource_id),
            KEY booking_id (booking_id),
            KEY event_id (event_id),
            KEY start_time (start_time),
            KEY status (status),
            UNIQUE KEY unique_resource_time (resource_id, start_time, end_time)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($resources_sql);
        dbDelta($bookings_sql);
    }
}