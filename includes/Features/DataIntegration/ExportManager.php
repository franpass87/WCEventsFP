<?php
/**
 * Export Manager
 * 
 * Handles CSV and ICS calendar exports with filtering and capability-based access.
 * 
 * @package WCEFP\Features\DataIntegration
 * @since 2.2.0
 */

namespace WCEFP\Features\DataIntegration;

use WCEFP\Utils\StringHelper;

class ExportManager {
    
    /**
     * Initialize the export manager
     */
    public function init() {
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Add public calendar feed endpoints
        add_action('init', [$this, 'add_calendar_endpoints']);
        add_action('template_redirect', [$this, 'handle_calendar_feed']);
    }
    
    /**
     * Enqueue admin scripts for export functionality
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'wcefp_page_wcefp-export') {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-export',
            WCEFP_PLUGIN_URL . 'assets/js/admin-export.js',
            ['jquery', 'wcefp-modals'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-export', 'wcefpExport', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_export'),
            'strings' => [
                'exporting' => __('Exporting...', 'wceventsfp'),
                'success' => __('Export completed successfully!', 'wceventsfp'),
                'error' => __('Export failed. Please try again.', 'wceventsfp'),
                'noData' => __('No data found for the selected criteria.', 'wceventsfp'),
            ]
        ]);
        
        wp_enqueue_style(
            'wcefp-export',
            WCEFP_PLUGIN_URL . 'assets/css/admin-export.css',
            ['wcefp-admin'],
            WCEFP_VERSION
        );
    }
    
    /**
     * Export bookings as CSV
     */
    public function export_bookings_csv($params) {
        global $wpdb;
        
        // Sanitize parameters
        $date_from = sanitize_text_field($params['date_from'] ?? '');
        $date_to = sanitize_text_field($params['date_to'] ?? '');
        $status = sanitize_text_field($params['status'] ?? 'all');
        $event_id = absint($params['event_id'] ?? 0);
        
        // Build query
        $where_conditions = ['1=1'];
        $query_params = [];
        
        if (!empty($date_from)) {
            $where_conditions[] = 'o.data_evento >= %s';
            $query_params[] = $date_from;
        }
        
        if (!empty($date_to)) {
            $where_conditions[] = 'o.data_evento <= %s';
            $query_params[] = $date_to;
        }
        
        if ($status !== 'all') {
            $where_conditions[] = 'o.stato = %s';
            $query_params[] = $status;
        }
        
        if ($event_id > 0) {
            $where_conditions[] = 'o.product_id = %d';
            $query_params[] = $event_id;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT 
                o.id as booking_id,
                o.product_id,
                p.post_title as event_title,
                o.data_evento as event_date,
                o.ora_evento as event_time,
                o.nome as customer_name,
                o.email as customer_email,
                o.telefono as customer_phone,
                o.adults as adults,
                o.children as children,
                o.stato as status,
                o.created_at as booking_date,
                o.meetingpoint as meeting_point,
                o.note as notes,
                o.prezzo_totale as total_price
            FROM {$wpdb->prefix}wcefp_occorrenze o
            LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
            WHERE {$where_clause}
            ORDER BY o.data_evento DESC, o.created_at DESC
        ";
        
        if (!empty($query_params)) {
            $results = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
        } else {
            $results = $wpdb->get_results($query);
        }
        
        if (empty($results)) {
            wp_send_json_error(['message' => __('No bookings found for the selected criteria.', 'wceventsfp')]);
        }
        
        // Generate CSV content
        $csv_content = $this->generate_bookings_csv($results);
        
        // Generate filename
        $filename = sprintf(
            'wcefp-bookings-%s.csv',
            date('Y-m-d-H-i-s')
        );
        
        // Log export action
        $this->log_export_action('bookings_csv', [
            'filename' => $filename,
            'records' => count($results),
            'filters' => compact('date_from', 'date_to', 'status', 'event_id')
        ]);
        
        wp_send_json_success([
            'filename' => $filename,
            'content' => base64_encode($csv_content),
            'count' => count($results)
        ]);
    }
    
    /**
     * Generate CSV content from booking results
     */
    private function generate_bookings_csv($results) {
        $csv_lines = [];
        
        // Add UTF-8 BOM for proper encoding in Excel
        $csv_lines[] = "\xEF\xBB\xBF";
        
        // Headers
        $headers = [
            __('Booking ID', 'wceventsfp'),
            __('Event Title', 'wceventsfp'),
            __('Event Date', 'wceventsfp'),
            __('Event Time', 'wceventsfp'),
            __('Customer Name', 'wceventsfp'),
            __('Customer Email', 'wceventsfp'),
            __('Customer Phone', 'wceventsfp'),
            __('Adults', 'wceventsfp'),
            __('Children', 'wceventsfp'),
            __('Status', 'wceventsfp'),
            __('Booking Date', 'wceventsfp'),
            __('Meeting Point', 'wceventsfp'),
            __('Notes', 'wceventsfp'),
            __('Total Price', 'wceventsfp')
        ];
        
        $csv_lines[] = '"' . implode('","', $headers) . '"';
        
        // Data rows
        foreach ($results as $booking) {
            $row = [
                $booking->booking_id,
                $this->escape_csv_field($booking->event_title),
                $booking->event_date,
                $booking->event_time,
                $this->escape_csv_field($booking->customer_name),
                $booking->customer_email,
                $booking->customer_phone,
                $booking->adults,
                $booking->children,
                $this->get_status_label($booking->status),
                $booking->booking_date,
                $this->escape_csv_field($booking->meeting_point),
                $this->escape_csv_field($booking->note),
                $booking->total_price
            ];
            
            $csv_lines[] = '"' . implode('","', $row) . '"';
        }
        
        return implode("\n", $csv_lines);
    }
    
    /**
     * Export calendar as ICS
     */
    public function export_calendar_ics($params) {
        global $wpdb;
        
        $event_id = absint($params['event_id'] ?? 0);
        $date_range = sanitize_text_field($params['date_range'] ?? '30');
        
        // Build query for events
        $where_conditions = ['o.stato IN ("confirmed", "pending")'];
        $query_params = [];
        
        if ($event_id > 0) {
            $where_conditions[] = 'o.product_id = %d';
            $query_params[] = $event_id;
        }
        
        // Date range filtering
        $where_conditions[] = 'o.data_evento >= %s';
        $query_params[] = date('Y-m-d');
        
        if ($date_range !== 'all') {
            $where_conditions[] = 'o.data_evento <= %s';
            $query_params[] = date('Y-m-d', strtotime("+{$date_range} days"));
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "
            SELECT 
                o.data_evento as event_date,
                o.ora_evento as event_time,
                p.post_title as event_title,
                p.post_content as event_description,
                o.meetingpoint as location,
                o.id as booking_id,
                COUNT(o.id) as booking_count
            FROM {$wpdb->prefix}wcefp_occorrenze o
            LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
            WHERE {$where_clause}
            GROUP BY o.product_id, o.data_evento, o.ora_evento
            ORDER BY o.data_evento ASC, o.ora_evento ASC
        ";
        
        if (!empty($query_params)) {
            $events = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
        } else {
            $events = $wpdb->get_results($query);
        }
        
        if (empty($events)) {
            wp_send_json_error(['message' => __('No events found for the selected criteria.', 'wceventsfp')]);
        }
        
        // Generate ICS content
        $ics_content = $this->generate_calendar_ics($events);
        
        // Generate filename
        $filename = sprintf(
            'wcefp-calendar-%s.ics',
            date('Y-m-d-H-i-s')
        );
        
        // Log export action
        $this->log_export_action('calendar_ics', [
            'filename' => $filename,
            'events' => count($events),
            'event_id' => $event_id,
            'date_range' => $date_range
        ]);
        
        wp_send_json_success([
            'filename' => $filename,
            'content' => base64_encode($ics_content),
            'count' => count($events)
        ]);
    }
    
    /**
     * Generate ICS calendar content
     */
    private function generate_calendar_ics($events) {
        $ics_lines = [];
        
        // Calendar header
        $ics_lines[] = 'BEGIN:VCALENDAR';
        $ics_lines[] = 'VERSION:2.0';
        $ics_lines[] = 'PRODID:-//WCEventsFP//NONSGML Event Calendar//EN';
        $ics_lines[] = 'CALSCALE:GREGORIAN';
        $ics_lines[] = 'METHOD:PUBLISH';
        $ics_lines[] = 'X-WR-CALNAME:' . get_bloginfo('name') . ' - Events';
        $ics_lines[] = 'X-WR-CALDESC:Event calendar exported from ' . get_bloginfo('name');
        
        // Events
        foreach ($events as $event) {
            $start_datetime = $this->format_ics_datetime($event->event_date, $event->event_time);
            $end_datetime = $this->format_ics_datetime($event->event_date, $event->event_time, '+2 hours');
            
            $ics_lines[] = 'BEGIN:VEVENT';
            $ics_lines[] = 'UID:wcefp-' . md5($event->event_title . $event->event_date . $event->event_time) . '@' . parse_url(home_url(), PHP_URL_HOST);
            $ics_lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $ics_lines[] = 'DTSTART:' . $start_datetime;
            $ics_lines[] = 'DTEND:' . $end_datetime;
            $ics_lines[] = 'SUMMARY:' . $this->escape_ics_field($event->event_title);
            
            if (!empty($event->event_description)) {
                $ics_lines[] = 'DESCRIPTION:' . $this->escape_ics_field(wp_strip_all_tags($event->event_description));
            }
            
            if (!empty($event->location)) {
                $ics_lines[] = 'LOCATION:' . $this->escape_ics_field($event->location);
            }
            
            $ics_lines[] = 'STATUS:CONFIRMED';
            $ics_lines[] = 'TRANSP:OPAQUE';
            $ics_lines[] = 'END:VEVENT';
        }
        
        // Calendar footer
        $ics_lines[] = 'END:VCALENDAR';
        
        return implode("\r\n", $ics_lines);
    }
    
    /**
     * Add calendar feed endpoints
     */
    public function add_calendar_endpoints() {
        add_rewrite_rule(
            '^wcefp-calendar/([^/]+)/?$',
            'index.php?wcefp_calendar_feed=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%wcefp_calendar_feed%', '([^&]+)');
    }
    
    /**
     * Handle calendar feed requests
     */
    public function handle_calendar_feed() {
        $feed_id = get_query_var('wcefp_calendar_feed');
        
        if (empty($feed_id)) {
            return;
        }
        
        // Validate feed ID format (should be uuid or token)
        $safe_feed_id = sanitize_text_field($feed_id);
        if (!StringHelper::safe_preg_match('/^[a-f0-9-]{36}$/', $safe_feed_id)) {
            wp_die(__('Invalid calendar feed ID.', 'wceventsfp'), 404);
        }
        
        // TODO: Validate feed permissions and generate calendar content
        // Validate feed permissions
        $feed_data = $this->validate_feed_permissions($feed_id);
        
        if (!$feed_data || $feed_data['status'] !== 'active') {
            wp_die(__('Calendar feed not found or inactive.', 'wceventsfp'), 404);
        }
        
        // Generate calendar content based on permissions
        $this->output_calendar_feed($feed_data);
    }
    
    /**
     * Validate calendar feed permissions
     * 
     * @param string $feed_id Feed UUID
     * @return array|false Feed data or false if invalid
     */
    private function validate_feed_permissions($feed_id) {
        // Check for stored calendar feeds in options table
        $calendar_feeds = get_option('wcefp_calendar_feeds', []);
        
        if (!isset($calendar_feeds[$feed_id])) {
            return false;
        }
        
        $feed_config = $calendar_feeds[$feed_id];
        
        // Validate feed is not expired
        if (isset($feed_config['expires']) && strtotime($feed_config['expires']) < time()) {
            return false;
        }
        
        // Return feed configuration
        return $feed_config;
    }
    
    /**
     * Output calendar feed based on configuration
     * 
     * @param array $feed_data Feed configuration
     */
    private function output_calendar_feed($feed_data) {
        // Determine feed type and filters
        $feed_type = $feed_data['type'] ?? 'public';
        $filters = $feed_data['filters'] ?? [];
        
        switch ($feed_type) {
            case 'public':
                $this->output_public_calendar_feed();
                break;
            case 'private':
                $this->output_private_calendar_feed($filters);
                break;
            case 'category':
                $this->output_category_calendar_feed($filters);
                break;
            default:
                $this->output_public_calendar_feed();
                break;
        }
    }
    
    /**
     * Output public calendar feed
     */
    private function output_public_calendar_feed() {
        global $wpdb;
        
        // Get public events (next 60 days)
        $query = "
            SELECT DISTINCT
                o.data_evento as event_date,
                o.ora_evento as event_time,
                p.post_title as event_title,
                p.post_content as event_description,
                o.meetingpoint as location
            FROM {$wpdb->prefix}wcefp_occorrenze o
            LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
            WHERE o.data_evento >= %s 
            AND o.data_evento <= %s
            AND p.post_status = 'publish'
            GROUP BY o.product_id, o.data_evento, o.ora_evento
            ORDER BY o.data_evento ASC, o.ora_evento ASC
        ";
        
        $events = $wpdb->get_results($wpdb->prepare(
            $query,
            date('Y-m-d'),
            date('Y-m-d', strtotime('+60 days'))
        ));
        
        // Generate and output ICS using unified method
        $this->generate_ics_output($events, 'WCEventsFP Public Calendar');
    }
    
    /**
     * Helper methods
     */
    
    private function escape_csv_field($field) {
        return str_replace('"', '""', $field);
    }
    
    private function escape_ics_field($field) {
        return str_replace(["\n", "\r", ",", ";"], ["\\n", "\\r", "\\,", "\\;"], $field);
    }
    
    private function format_ics_datetime($date, $time, $modifier = '') {
        $datetime = $date . ' ' . $time;
        if ($modifier) {
            $datetime = date('Y-m-d H:i:s', strtotime($datetime . ' ' . $modifier));
        }
        return gmdate('Ymd\THis\Z', strtotime($datetime));
    }
    
    
    /**
     * Output private calendar feed with permissions
     * 
     * @param array $filters Feed filters
     */
    private function output_private_calendar_feed($filters) {
        global $wpdb;
        
        // Similar to public feed but with additional access controls
        // This would typically include user-specific events
        $this->output_public_calendar_feed(); // Fallback for now
    }
    
    /**
     * Output category-based calendar feed
     * 
     * @param array $filters Category filters
     */
    private function output_category_calendar_feed($filters) {
        global $wpdb;
        
        $category_ids = $filters['categories'] ?? [];
        
        if (empty($category_ids)) {
            $this->output_public_calendar_feed();
            return;
        }
        
        // Build category filter for query
        $category_placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
        
        $query = "
            SELECT DISTINCT
                o.data_evento as event_date,
                o.ora_evento as event_time,
                p.post_title as event_title,
                p.post_content as event_description,
                o.meetingpoint as location
            FROM {$wpdb->prefix}wcefp_occorrenze o
            LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
            LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            WHERE o.data_evento >= %s 
            AND o.data_evento <= %s
            AND p.post_status = 'publish'
            AND tr.term_taxonomy_id IN ({$category_placeholders})
            GROUP BY o.product_id, o.data_evento, o.ora_evento
            ORDER BY o.data_evento ASC, o.ora_evento ASC
        ";
        
        $params = array_merge(
            [date('Y-m-d'), date('Y-m-d', strtotime('+60 days'))],
            $category_ids
        );
        
        $events = $wpdb->get_results($wpdb->prepare($query, $params));
        
        // Generate ICS output
        $this->generate_ics_output($events, 'Category Calendar');
    }
    
    /**
     * Generate ICS calendar output
     * 
     * @param array $events Event data
     * @param string $calendar_name Calendar name
     */
    private function generate_ics_output($events, $calendar_name = 'WCEventsFP Calendar') {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($calendar_name) . '.ics"');
        header('Cache-Control: no-cache, must-revalidate');
        
        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//WCEventsFP//Calendar Export//EN\r\n";
        echo "CALSCALE:GREGORIAN\r\n";
        echo "X-WR-CALNAME:" . $calendar_name . "\r\n";
        echo "X-WR-TIMEZONE:Europe/Rome\r\n";
        
        foreach ($events as $event) {
            $start_datetime = $event->event_date . 'T' . ($event->event_time ?: '10:00:00');
            $uid = 'wcefp-' . md5($event->event_title . $start_datetime) . '@' . $_SERVER['HTTP_HOST'];
            
            echo "BEGIN:VEVENT\r\n";
            echo "UID:" . $uid . "\r\n";
            echo "DTSTART:" . $this->format_ics_datetime($start_datetime) . "\r\n";
            echo "SUMMARY:" . $this->escape_ics_text($event->event_title) . "\r\n";
            
            if ($event->event_description) {
                echo "DESCRIPTION:" . $this->escape_ics_text(wp_strip_all_tags($event->event_description)) . "\r\n";
            }
            
            if ($event->location) {
                echo "LOCATION:" . $this->escape_ics_text($event->location) . "\r\n";
            }
            
            echo "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            echo "END:VEVENT\r\n";
        }
        
        echo "END:VCALENDAR\r\n";
        exit;
    }
    
    /**
     * Escape text for ICS format
     * 
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private function escape_ics_text($text) {
        $text = str_replace(["\r\n", "\r", "\n"], '\\n', $text);
        $text = str_replace([',', ';', '\\'], ['\\,', '\\;', '\\\\'], $text);
        return $text;
    }

    private function get_status_label($status) {
        $labels = [
            'pending' => __('Pending', 'wceventsfp'),
            'confirmed' => __('Confirmed', 'wceventsfp'),
            'cancelled' => __('Cancelled', 'wceventsfp'),
            'completed' => __('Completed', 'wceventsfp'),
        ];
        
        return $labels[$status] ?? $status;
    }
    
    private function log_export_action($type, $data) {
        if (function_exists('wcefp_log')) {
            wcefp_log("Export: {$type}", $data);
        }
    }
}