<?php
/**
 * Digital Check-in Manager
 * 
 * Handles QR code generation, mobile check-in interface, and real-time status tracking
 * Part of Phase 5: Advanced Booking Features
 *
 * @package WCEFP
 * @subpackage BookingFeatures
 * @since 2.2.0
 */

namespace WCEFP\BookingFeatures;

use WCEFP\Core\Database\BookingRepository;
use WCEFP\Utils\QRCodeGenerator;
use WCEFP\Utils\SecurityValidator;

class DigitalCheckinManager {
    
    private $booking_repository;
    private $qr_generator;
    private $security_validator;
    
    public function __construct() {
        $this->booking_repository = new BookingRepository();
        $this->qr_generator = new QRCodeGenerator();
        $this->security_validator = new SecurityValidator();
        
        add_action('wp_ajax_wcefp_check_in_booking', [$this, 'handle_checkin_ajax']);
        add_action('wp_ajax_nopriv_wcefp_check_in_booking', [$this, 'handle_checkin_ajax']);
        add_action('wp_ajax_wcefp_get_checkin_status', [$this, 'get_checkin_status_ajax']);
        add_action('wcefp_booking_confirmed', [$this, 'generate_checkin_qr_code'], 10, 1);
        
        // Register mobile check-in shortcode
        add_shortcode('wcefp_mobile_checkin', [$this, 'render_mobile_checkin_interface']);
    }
    
    /**
     * Generate QR code for booking check-in
     *
     * @param int $booking_id Booking ID
     * @return string|WP_Error QR code data URL or error
     */
    public function generate_checkin_qr_code($booking_id) {
        $booking = $this->booking_repository->get_booking($booking_id);
        if (!$booking) {
            return new \WP_Error('booking_not_found', __('Booking not found', 'wceventsfp'));
        }
        
        // Generate secure token for check-in
        $checkin_token = wp_generate_password(32, false);
        $expiry_date = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Store token in database
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_checkin_tokens';
        
        $wpdb->replace(
            $table_name,
            [
                'booking_id' => $booking_id,
                'token' => $checkin_token,
                'expiry_date' => $expiry_date,
                'status' => 'active',
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
        
        // Generate check-in URL
        $checkin_url = add_query_arg([
            'wcefp_checkin' => 1,
            'token' => $checkin_token,
            'booking' => $booking_id
        ], home_url('/wcefp-checkin/'));
        
        // Generate QR code
        $qr_code_data = $this->qr_generator->generate($checkin_url, [
            'size' => 300,
            'margin' => 10,
            'format' => 'png'
        ]);
        
        if (is_wp_error($qr_code_data)) {
            return $qr_code_data;
        }
        
        // Store QR code reference
        update_post_meta($booking_id, '_wcefp_checkin_qr_code', $qr_code_data);
        update_post_meta($booking_id, '_wcefp_checkin_token', $checkin_token);
        
        return $qr_code_data;
    }
    
    /**
     * Handle check-in via AJAX
     */
    public function handle_checkin_ajax() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wcefp_checkin_nonce')) {
            wp_send_json_error(['message' => __('Security verification failed', 'wceventsfp')]);
        }
        
        $token = sanitize_text_field($_POST['token']);
        $booking_id = absint($_POST['booking_id']);
        $location = sanitize_text_field($_POST['location'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        
        // Validate token
        $token_data = $this->validate_checkin_token($token, $booking_id);
        if (is_wp_error($token_data)) {
            wp_send_json_error(['message' => $token_data->get_error_message()]);
        }
        
        // Perform check-in
        $result = $this->process_checkin($booking_id, [
            'location' => $location,
            'notes' => $notes,
            'staff_user_id' => get_current_user_id(),
            'ip_address' => $this->security_validator->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => __('Check-in successful!', 'wceventsfp'),
            'booking_id' => $booking_id,
            'checkin_time' => current_time('mysql'),
            'status' => 'checked_in'
        ]);
    }
    
    /**
     * Process booking check-in
     *
     * @param int $booking_id Booking ID
     * @param array $checkin_data Check-in data
     * @return true|WP_Error Success or error
     */
    public function process_checkin($booking_id, $checkin_data = []) {
        // Check if already checked in
        $existing_checkin = get_post_meta($booking_id, '_wcefp_checkin_status', true);
        if ($existing_checkin === 'checked_in') {
            return new \WP_Error('already_checked_in', __('This booking is already checked in', 'wceventsfp'));
        }
        
        // Update booking status
        update_post_meta($booking_id, '_wcefp_checkin_status', 'checked_in');
        update_post_meta($booking_id, '_wcefp_checkin_time', current_time('mysql'));
        update_post_meta($booking_id, '_wcefp_checkin_data', $checkin_data);
        
        // Log check-in event
        $this->log_checkin_event($booking_id, $checkin_data);
        
        // Trigger action for integrations
        do_action('wcefp_booking_checked_in', $booking_id, $checkin_data);
        
        // Send notification (if enabled)
        $this->send_checkin_notification($booking_id);
        
        return true;
    }
    
    /**
     * Validate check-in token
     *
     * @param string $token Check-in token
     * @param int $booking_id Booking ID
     * @return array|WP_Error Token data or error
     */
    private function validate_checkin_token($token, $booking_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_checkin_tokens';
        
        $token_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE token = %s AND booking_id = %d AND status = 'active'",
            $token, $booking_id
        ), ARRAY_A);
        
        if (!$token_data) {
            return new \WP_Error('invalid_token', __('Invalid or expired check-in token', 'wceventsfp'));
        }
        
        // Check expiry
        if (strtotime($token_data['expiry_date']) < time()) {
            return new \WP_Error('token_expired', __('Check-in token has expired', 'wceventsfp'));
        }
        
        return $token_data;
    }
    
    /**
     * Render mobile check-in interface
     */
    public function render_mobile_checkin_interface($atts) {
        $atts = shortcode_atts([
            'theme' => 'default',
            'show_location' => 'yes',
            'show_notes' => 'yes'
        ], $atts);
        
        // Enqueue mobile check-in assets
        wp_enqueue_script('wcefp-mobile-checkin', 
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/js/mobile-checkin.js', 
            ['jquery'], '2.2.0', true
        );
        wp_enqueue_style('wcefp-mobile-checkin', 
            plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/css/mobile-checkin.css', 
            [], '2.2.0'
        );
        
        // Localize script
        wp_localize_script('wcefp-mobile-checkin', 'wcefp_checkin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcefp_checkin_nonce'),
            'strings' => [
                'checking_in' => __('Processing check-in...', 'wceventsfp'),
                'success' => __('Check-in successful!', 'wceventsfp'),
                'error' => __('Check-in failed. Please try again.', 'wceventsfp'),
                'scan_qr' => __('Scan QR Code', 'wceventsfp'),
                'manual_entry' => __('Manual Entry', 'wceventsfp')
            ]
        ]);
        
        ob_start();
        include plugin_dir_path(dirname(dirname(__FILE__))) . 'templates/mobile-checkin-interface.php';
        return ob_get_clean();
    }
    
    /**
     * Get check-in status via AJAX
     */
    public function get_checkin_status_ajax() {
        $booking_id = absint($_GET['booking_id']);
        
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID', 'wceventsfp')]);
        }
        
        $status = get_post_meta($booking_id, '_wcefp_checkin_status', true);
        $checkin_time = get_post_meta($booking_id, '_wcefp_checkin_time', true);
        
        wp_send_json_success([
            'status' => $status ?: 'pending',
            'checkin_time' => $checkin_time,
            'can_checkin' => $status !== 'checked_in'
        ]);
    }
    
    /**
     * Log check-in event for audit trail
     */
    private function log_checkin_event($booking_id, $checkin_data) {
        $log_entry = [
            'booking_id' => $booking_id,
            'event_type' => 'check_in',
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'data' => json_encode($checkin_data),
            'ip_address' => $checkin_data['ip_address'] ?? '',
            'user_agent' => $checkin_data['user_agent'] ?? ''
        ];
        
        // Store in events log table (will be created by database migration)
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_event_logs';
        $wpdb->insert($table_name, $log_entry);
    }
    
    /**
     * Send check-in notification
     */
    private function send_checkin_notification($booking_id) {
        $notification_enabled = get_option('wcefp_checkin_notifications', 'yes');
        if ($notification_enabled !== 'yes') {
            return;
        }
        
        // Get booking details
        $booking = get_post($booking_id);
        $customer_email = get_post_meta($booking_id, '_customer_email', true);
        $event_title = get_the_title(get_post_meta($booking_id, '_event_id', true));
        
        // Send email notification
        $subject = sprintf(__('Check-in Confirmation - %s', 'wceventsfp'), $event_title);
        $message = sprintf(
            __('Your check-in for %s has been confirmed at %s.', 'wceventsfp'),
            $event_title,
            current_time('Y-m-d H:i:s')
        );
        
        wp_mail($customer_email, $subject, $message);
    }
    
    /**
     * Create database tables for check-in system
     */
    public static function create_checkin_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Check-in tokens table
        $tokens_table = $wpdb->prefix . 'wcefp_checkin_tokens';
        $tokens_sql = "CREATE TABLE $tokens_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            token varchar(255) NOT NULL,
            expiry_date datetime NOT NULL,
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY booking_id (booking_id),
            KEY expiry_date (expiry_date)
        ) $charset_collate;";
        
        // Event logs table
        $logs_table = $wpdb->prefix . 'wcefp_event_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            booking_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20) unsigned DEFAULT NULL,
            data longtext,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY event_type (event_type),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($tokens_sql);
        dbDelta($logs_sql);
    }
}