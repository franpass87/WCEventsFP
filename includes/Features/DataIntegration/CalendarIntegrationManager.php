<?php
/**
 * Calendar Integration Manager
 * 
 * Handles Google Calendar "Add Event" buttons and authenticated admin calendar feeds
 * 
 * @package WCEFP\Features\DataIntegration
 * @since 2.2.0
 */

namespace WCEFP\Features\DataIntegration;

class CalendarIntegrationManager {
    
    /**
     * Initialize the calendar integration manager
     */
    public function init() {
        // Frontend calendar integration
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_shortcode('wcefp_add_to_calendar', [$this, 'add_to_calendar_shortcode']);
        add_filter('wcefp_booking_details_buttons', [$this, 'add_calendar_buttons'], 10, 2);
        
        // Admin calendar synchronization - removed problematic hook, moved to register()
        add_action('wp_ajax_wcefp_generate_admin_calendar_feed', [$this, 'generate_admin_calendar_feed']);
        add_action('wp_ajax_wcefp_refresh_calendar_token', [$this, 'refresh_calendar_token']);
        
        // Calendar feed endpoints
        add_action('init', [$this, 'add_authenticated_endpoints']);
        add_action('template_redirect', [$this, 'handle_authenticated_feed']);
    }
    
    /**
     * Register AJAX hooks for admin calendar synchronization
     */
    public function register(): void {
        // AJAX admin autenticato
        add_action('wp_ajax_wcefp_admin_calendar_sync', [$this, 'handle_admin_calendar_sync']);
        // Fallback per form POST admin
        add_action('admin_post_wcefp_admin_calendar_sync', [$this, 'handle_admin_calendar_sync']);
    }
    
    /**
     * Gestisce la sincronizzazione calendario dal pannello admin.
     * Supporta sia AJAX (admin-ajax.php) sia admin-post.php con redirect.
     */
    public function handle_admin_calendar_sync(): void {
        // Capability: usa quella del plugin se presente, altrimenti fallback
        $cap = function_exists('wcefp_capability') ? wcefp_capability('manage') : 'manage_woocommerce';
        if (!current_user_can($cap)) {
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => 'Forbidden'], 403);
            } else {
                wp_die(__('Non hai i permessi necessari.', 'wcefp'), 403);
            }
            return;
        }

        // Nonce
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'wcefp_calendar_sync')) {
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => 'Bad nonce'], 400);
            } else {
                wp_die(__('Nonce non valido.', 'wcefp'), 400);
            }
            return;
        }

        // TODO: implementa qui la logica di sync (ICS/Google/etc.)
        // Mantieni idempotenza e gestisci eccezioni
        try {
            $result = [
                'synced'   => 0,
                'skipped'  => 0,
                'messages' => ['Stub: sincronizzazione eseguita (implementare logica reale).'],
            ];
        } catch (\Throwable $e) {
            error_log('WCEFP calendar sync error: ' . $e->getMessage());
            if (wp_doing_ajax()) {
                wp_send_json_error(['message' => 'Sync failed', 'error' => $e->getMessage()], 500);
            }
            wp_die(__('Errore durante la sincronizzazione.', 'wcefp'), 500);
        }

        if (wp_doing_ajax()) {
            wp_send_json_success($result);
        } else {
            // Redirect di cortesia
            $url = add_query_arg(['wcefp_calendar_sync' => 'done'], admin_url('admin.php?page=wcefp-calendar'));
            wp_safe_redirect($url);
            exit;
        }
    }
    
    /**
     * Enqueue frontend calendar integration scripts
     */
    public function enqueue_frontend_scripts() {
        // Only enqueue on pages with WCEFP content
        if (!$this->has_wcefp_content()) {
            return;
        }
        
        wp_enqueue_script(
            'wcefp-calendar-integration',
            WCEFP_PLUGIN_URL . 'assets/js/calendar-integration.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_localize_script('wcefp-calendar-integration', 'wcefpCalendar', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_calendar'),
            'strings' => [
                'addToGoogle' => __('Add to Google Calendar', 'wceventsfp'),
                'addToOutlook' => __('Add to Outlook', 'wceventsfp'),
                'downloadICS' => __('Download Calendar File', 'wceventsfp'),
                'error' => __('Error generating calendar event', 'wceventsfp')
            ]
        ]);
        
        wp_enqueue_style(
            'wcefp-calendar-integration',
            WCEFP_PLUGIN_URL . 'assets/css/calendar-integration.css',
            [],
            WCEFP_VERSION
        );
    }
    
    /**
     * Add calendar buttons to booking details
     */
    public function add_calendar_buttons($buttons, $booking_data) {
        if (empty($booking_data)) {
            return $buttons;
        }
        
        $calendar_buttons = $this->generate_calendar_buttons($booking_data);
        
        return $buttons . $calendar_buttons;
    }
    
    /**
     * Generate calendar integration buttons
     */
    public function generate_calendar_buttons($booking_data) {
        $event_title = $booking_data['event_title'] ?? __('Event', 'wceventsfp');
        $event_date = $booking_data['event_date'] ?? '';
        $event_time = $booking_data['event_time'] ?? '';
        $location = $booking_data['location'] ?? '';
        $description = $booking_data['description'] ?? '';
        
        if (empty($event_date)) {
            return '';
        }
        
        // Format datetime for URLs
        $start_datetime = $this->format_calendar_datetime($event_date, $event_time);
        $end_datetime = $this->format_calendar_datetime($event_date, $event_time, '+2 hours');
        
        // Generate calendar URLs
        $google_url = $this->generate_google_calendar_url([
            'title' => $event_title,
            'start' => $start_datetime,
            'end' => $end_datetime,
            'location' => $location,
            'description' => $description
        ]);
        
        $outlook_url = $this->generate_outlook_calendar_url([
            'title' => $event_title,
            'start' => $start_datetime,
            'end' => $end_datetime,
            'location' => $location,
            'description' => $description
        ]);
        
        $ics_url = $this->generate_ics_download_url($booking_data);
        
        ob_start();
        ?>
        <div class="wcefp-calendar-buttons">
            <h4><?php esc_html_e('Add to Calendar', 'wceventsfp'); ?></h4>
            <div class="wcefp-calendar-button-group">
                <a href="<?php echo esc_url($google_url); ?>" 
                   class="wcefp-calendar-btn wcefp-google-calendar"
                   target="_blank"
                   rel="noopener noreferrer">
                    <span class="wcefp-calendar-icon wcefp-google-icon"></span>
                    <?php esc_html_e('Google Calendar', 'wceventsfp'); ?>
                </a>
                
                <a href="<?php echo esc_url($outlook_url); ?>" 
                   class="wcefp-calendar-btn wcefp-outlook-calendar"
                   target="_blank"
                   rel="noopener noreferrer">
                    <span class="wcefp-calendar-icon wcefp-outlook-icon"></span>
                    <?php esc_html_e('Outlook', 'wceventsfp'); ?>
                </a>
                
                <a href="<?php echo esc_url($ics_url); ?>" 
                   class="wcefp-calendar-btn wcefp-ics-download"
                   download>
                    <span class="wcefp-calendar-icon wcefp-download-icon"></span>
                    <?php esc_html_e('Download ICS', 'wceventsfp'); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate Google Calendar URL
     */
    private function generate_google_calendar_url($event) {
        $params = [
            'action' => 'TEMPLATE',
            'text' => $event['title'],
            'dates' => $event['start'] . '/' . $event['end'],
            'details' => $event['description'],
            'location' => $event['location'],
            'trp' => 'false'
        ];
        
        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }
    
    /**
     * Generate Outlook Calendar URL
     */
    private function generate_outlook_calendar_url($event) {
        $params = [
            'subject' => $event['title'],
            'startdt' => $event['start'],
            'enddt' => $event['end'],
            'body' => $event['description'],
            'location' => $event['location']
        ];
        
        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($params);
    }
    
    /**
     * Generate ICS download URL
     */
    private function generate_ics_download_url($booking_data) {
        $params = [
            'action' => 'wcefp_download_event_ics',
            'booking_id' => $booking_data['booking_id'] ?? '',
            'nonce' => wp_create_nonce('wcefp_calendar_download')
        ];
        
        return admin_url('admin-ajax.php?' . http_build_query($params));
    }
    
    /**
     * Handle ICS download request
     */
    public function handle_ics_download() {
        check_ajax_referer('wcefp_calendar_download', 'nonce');
        
        $booking_id = absint($_GET['booking_id'] ?? 0);
        
        if (!$booking_id) {
            wp_die(__('Invalid booking ID', 'wceventsfp'));
        }
        
        // Get booking data
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare("
            SELECT 
                o.data_evento as event_date,
                o.ora_evento as event_time,
                p.post_title as event_title,
                p.post_content as event_description,
                o.meetingpoint as location
            FROM {$wpdb->prefix}wcefp_occorrenze o
            LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
            WHERE o.id = %d
        ", $booking_id));
        
        if (!$booking) {
            wp_die(__('Booking not found', 'wceventsfp'));
        }
        
        // Generate ICS content
        $ics_content = $this->generate_single_event_ics($booking);
        
        // Output ICS file
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="event.ics"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        echo $ics_content;
        exit;
    }
    
    /**
     * Add authenticated calendar endpoints for admin
     */
    public function add_authenticated_endpoints() {
        add_rewrite_rule(
            '^wcefp-admin-calendar/([^/]+)/?$',
            'index.php?wcefp_admin_calendar=$matches[1]',
            'top'
        );
        
        add_rewrite_tag('%wcefp_admin_calendar%', '([^&]+)');
    }
    
    /**
     * Handle authenticated admin calendar feed
     */
    public function handle_authenticated_feed() {
        $token = get_query_var('wcefp_admin_calendar');
        
        if (empty($token)) {
            return;
        }
        
        // Validate admin calendar token
        $valid_token = get_option('wcefp_admin_calendar_token');
        $token_expiry = get_option('wcefp_admin_calendar_token_expiry');
        
        if (!$valid_token || $token !== $valid_token || time() > $token_expiry) {
            status_header(403);
            wp_die(__('Invalid or expired calendar token', 'wceventsfp'));
        }
        
        // Generate admin calendar feed
        $this->generate_admin_calendar_feed_response();
    }
    
    /**
     * Generate admin calendar feed
     */
    public function generate_admin_calendar_feed() {
        check_ajax_referer('wcefp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'wceventsfp')]);
        }
        
        // Generate or refresh token
        $token = wp_generate_password(32, false);
        $expiry = time() + (30 * DAY_IN_SECONDS); // 30 days
        
        update_option('wcefp_admin_calendar_token', $token, false);
        update_option('wcefp_admin_calendar_token_expiry', $expiry, false);
        
        // Generate feed URL
        $feed_url = home_url("/wcefp-admin-calendar/{$token}/");
        
        wp_send_json_success([
            'feed_url' => $feed_url,
            'expires' => date_i18n(get_option('date_format'), $expiry),
            'token' => $token
        ]);
    }
    
    /**
     * Generate admin calendar feed response
     */
    private function generate_admin_calendar_feed_response() {
        global $wpdb;
        
        // Get all confirmed bookings for the next 90 days
        $events = $wpdb->get_results($wpdb->prepare("
            SELECT 
                o.id,
                o.data_evento as event_date,
                o.ora_evento as event_time,
                p.post_title as event_title,
                p.post_content as event_description,
                o.meetingpoint as location,
                o.nome as customer_name,
                o.email as customer_email,
                o.adults,
                o.children,
                o.note as notes
            FROM {$wpdb->prefix}wcefp_occorrenze o
            LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
            WHERE o.stato = 'confirmed'
            AND o.data_evento >= %s
            AND o.data_evento <= %s
            ORDER BY o.data_evento ASC, o.ora_evento ASC
        ", 
            date('Y-m-d'),
            date('Y-m-d', strtotime('+90 days'))
        ));
        
        // Generate ICS content with enhanced admin information
        $ics_content = $this->generate_admin_calendar_ics($events);
        
        // Output with proper headers
        header('Content-Type: text/calendar; charset=utf-8');
        header('Cache-Control: public, max-age=3600'); // Cache for 1 hour
        header('X-WR-CALNAME: ' . get_bloginfo('name') . ' - Admin Events');
        
        echo $ics_content;
        exit;
    }
    
    /**
     * Generate admin-specific ICS calendar
     */
    private function generate_admin_calendar_ics($events) {
        $ics_lines = [];
        
        // Calendar header
        $ics_lines[] = 'BEGIN:VCALENDAR';
        $ics_lines[] = 'VERSION:2.0';
        $ics_lines[] = 'PRODID:-//WCEventsFP//NONSGML Admin Calendar//EN';
        $ics_lines[] = 'CALSCALE:GREGORIAN';
        $ics_lines[] = 'METHOD:PUBLISH';
        $ics_lines[] = 'X-WR-CALNAME:' . get_bloginfo('name') . ' - Admin Events';
        $ics_lines[] = 'X-WR-CALDESC:Administrative event calendar with booking details';
        $ics_lines[] = 'X-WR-TIMEZONE:' . wp_timezone_string();
        
        // Events with enhanced admin information
        foreach ($events as $event) {
            $start_datetime = $this->format_ics_datetime($event->event_date, $event->event_time);
            $end_datetime = $this->format_ics_datetime($event->event_date, $event->event_time, '+2 hours');
            
            // Enhanced admin description
            $admin_description = sprintf(
                "%s\n\nBooking Details:\n- Customer: %s <%s>\n- Participants: %d adults, %d children\n- Booking ID: %d",
                wp_strip_all_tags($event->event_description ?: ''),
                $event->customer_name,
                $event->customer_email,
                $event->adults,
                $event->children,
                $event->id
            );
            
            if (!empty($event->notes)) {
                $admin_description .= "\n- Notes: " . $event->notes;
            }
            
            $ics_lines[] = 'BEGIN:VEVENT';
            $ics_lines[] = 'UID:wcefp-admin-' . $event->id . '@' . parse_url(home_url(), PHP_URL_HOST);
            $ics_lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
            $ics_lines[] = 'DTSTART:' . $start_datetime;
            $ics_lines[] = 'DTEND:' . $end_datetime;
            $ics_lines[] = 'SUMMARY:' . $this->escape_ics_field($event->event_title . ' - ' . $event->customer_name);
            $ics_lines[] = 'DESCRIPTION:' . $this->escape_ics_field($admin_description);
            
            if (!empty($event->location)) {
                $ics_lines[] = 'LOCATION:' . $this->escape_ics_field($event->location);
            }
            
            $ics_lines[] = 'STATUS:CONFIRMED';
            $ics_lines[] = 'TRANSP:OPAQUE';
            $ics_lines[] = 'END:VEVENT';
        }
        
        $ics_lines[] = 'END:VCALENDAR';
        
        return implode("\r\n", $ics_lines);
    }
    
    /**
     * Add to calendar shortcode
     */
    public function add_to_calendar_shortcode($atts) {
        $atts = shortcode_atts([
            'event_id' => 0,
            'booking_id' => 0,
            'style' => 'buttons' // buttons, dropdown, minimal
        ], $atts);
        
        if (!$atts['event_id'] && !$atts['booking_id']) {
            return __('Event or booking ID required', 'wceventsfp');
        }
        
        // Get event/booking data
        $data = $this->get_calendar_data($atts['event_id'], $atts['booking_id']);
        
        if (!$data) {
            return __('Event not found', 'wceventsfp');
        }
        
        return $this->generate_calendar_buttons($data);
    }
    
    /**
     * Helper methods
     */
    
    private function has_wcefp_content() {
        global $post;
        
        if (!$post) {
            return false;
        }
        
        // Check for shortcodes or blocks
        return has_shortcode($post->post_content, 'wcefp_booking_form') ||
               has_shortcode($post->post_content, 'wcefp_event_list') ||
               has_shortcode($post->post_content, 'wcefp_add_to_calendar') ||
               has_block('wcefp/booking-form', $post) ||
               has_block('wcefp/event-list', $post);
    }
    
    private function format_calendar_datetime($date, $time, $modifier = '') {
        $datetime = $date . ' ' . $time;
        if ($modifier) {
            $datetime = date('Y-m-d H:i:s', strtotime($datetime . ' ' . $modifier));
        }
        return gmdate('Ymd\THis\Z', strtotime($datetime));
    }
    
    private function format_ics_datetime($date, $time, $modifier = '') {
        $datetime = $date . ' ' . $time;
        if ($modifier) {
            $datetime = date('Y-m-d H:i:s', strtotime($datetime . ' ' . $modifier));
        }
        return gmdate('Ymd\THis\Z', strtotime($datetime));
    }
    
    private function escape_ics_field($field) {
        return str_replace(["\n", "\r", ",", ";", "\\"], ["\\n", "\\r", "\\,", "\\;", "\\\\"], $field);
    }
    
    private function generate_single_event_ics($event) {
        $ics_lines = [];
        
        $ics_lines[] = 'BEGIN:VCALENDAR';
        $ics_lines[] = 'VERSION:2.0';
        $ics_lines[] = 'PRODID:-//WCEventsFP//NONSGML Event Calendar//EN';
        $ics_lines[] = 'CALSCALE:GREGORIAN';
        $ics_lines[] = 'METHOD:PUBLISH';
        
        $start_datetime = $this->format_ics_datetime($event->event_date, $event->event_time);
        $end_datetime = $this->format_ics_datetime($event->event_date, $event->event_time, '+2 hours');
        
        $ics_lines[] = 'BEGIN:VEVENT';
        $ics_lines[] = 'UID:wcefp-' . md5($event->event_title . $event->event_date) . '@' . parse_url(home_url(), PHP_URL_HOST);
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
        
        $ics_lines[] = 'END:VEVENT';
        $ics_lines[] = 'END:VCALENDAR';
        
        return implode("\r\n", $ics_lines);
    }
    
    private function get_calendar_data($event_id, $booking_id) {
        global $wpdb;
        
        if ($booking_id) {
            return $wpdb->get_row($wpdb->prepare("
                SELECT 
                    o.id as booking_id,
                    o.data_evento as event_date,
                    o.ora_evento as event_time,
                    p.post_title as event_title,
                    p.post_content as description,
                    o.meetingpoint as location
                FROM {$wpdb->prefix}wcefp_occorrenze o
                LEFT JOIN {$wpdb->posts} p ON o.product_id = p.ID
                WHERE o.id = %d
            ", $booking_id), ARRAY_A);
        } elseif ($event_id) {
            return $wpdb->get_row($wpdb->prepare("
                SELECT 
                    p.ID as event_id,
                    p.post_title as event_title,
                    p.post_content as description
                FROM {$wpdb->posts} p
                WHERE p.ID = %d AND p.post_type = 'product'
            ", $event_id), ARRAY_A);
        }
        
        return null;
    }
}