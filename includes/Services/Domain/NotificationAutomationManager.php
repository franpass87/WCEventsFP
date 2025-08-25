<?php
/**
 * Email & Notification Automation Manager
 * 
 * Handles automated scheduling and triggering of notifications
 * based on booking events and time-based triggers.
 * 
 * @package WCEFP\Services\Domain
 * @since 2.2.0
 */

namespace WCEFP\Services\Domain;

use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Automation Manager for notifications and emails
 */
class NotificationAutomationManager {
    
    /**
     * NotificationService instance
     *
     * @var NotificationService
     */
    private $notification_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->notification_service = new NotificationService();
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Booking lifecycle hooks
        add_action('wcefp_booking_confirmed', [$this, 'handle_booking_confirmed'], 10, 2);
        add_action('wcefp_booking_cancelled', [$this, 'handle_booking_cancelled'], 10, 2);
        add_action('wcefp_booking_rescheduled', [$this, 'handle_booking_rescheduled'], 10, 3);
        
        // Capacity monitoring hooks
        add_action('wcefp_capacity_threshold_reached', [$this, 'handle_capacity_threshold'], 10, 2);
        add_action('wcefp_event_sold_out', [$this, 'handle_event_sold_out'], 10, 2);
        
        // Scheduled notification hooks
        add_action('wcefp_send_scheduled_notification', [$this, 'send_scheduled_notification'], 10, 1);
        
        // Daily automation cron
        add_action('wcefp_daily_automation', [$this, 'run_daily_automation']);
        
        // Admin notification hooks
        add_action('wcefp_admin_notification', [$this, 'handle_admin_notification'], 10, 3);
        
        // Schedule daily cron if not exists
        if (!wp_next_scheduled('wcefp_daily_automation')) {
            wp_schedule_event(time(), 'daily', 'wcefp_daily_automation');
        }
    }
    
    /**
     * Handle booking confirmed event
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    public function handle_booking_confirmed($booking_id, $booking_data) {
        Logger::info("Processing booking confirmation automation for booking {$booking_id}");
        
        // Send immediate confirmation email
        $recipients = [
            [
                'email' => $booking_data['customer_email'] ?? '',
                'name' => $booking_data['customer_name'] ?? '',
                'type' => 'customer'
            ]
        ];
        
        $data = array_merge($booking_data, [
            'booking_id' => $booking_id,
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'view_booking_url' => $this->get_booking_view_url($booking_id),
            'contact_phone' => get_option('wcefp_contact_phone', ''),
            'contact_email' => get_option('wcefp_contact_email', get_option('admin_email'))
        ]);
        
        // Send confirmation
        $confirmation_result = $this->notification_service->send_notification(
            NotificationService::TYPE_BOOKING_CONFIRMED,
            $recipients,
            $data
        );
        
        Logger::info("Booking confirmation sent", [
            'booking_id' => $booking_id,
            'success' => $confirmation_result['sent']
        ]);
        
        // Schedule reminder notifications
        $reminders_scheduled = $this->notification_service->schedule_booking_reminders($booking_id, $booking_data);
        
        Logger::info("Booking reminders scheduled", [
            'booking_id' => $booking_id,
            'reminders' => $reminders_scheduled
        ]);
        
        // Send admin notification for new bookings
        $this->send_admin_booking_notification($booking_id, $booking_data, 'new_booking');
        
        // Generate calendar file (ICS) for customer
        $this->generate_calendar_attachment($booking_id, $booking_data);
    }
    
    /**
     * Handle booking cancelled event
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    public function handle_booking_cancelled($booking_id, $booking_data) {
        Logger::info("Processing booking cancellation automation for booking {$booking_id}");
        
        // Cancel any scheduled reminders
        $this->notification_service->cancel_booking_notifications($booking_id);
        
        // Send cancellation confirmation
        $recipients = [
            [
                'email' => $booking_data['customer_email'] ?? '',
                'name' => $booking_data['customer_name'] ?? '',
                'type' => 'customer'
            ]
        ];
        
        $data = array_merge($booking_data, [
            'booking_id' => $booking_id,
            'site_name' => get_bloginfo('name'),
            'cancellation_reason' => $booking_data['cancellation_reason'] ?? '',
            'refund_amount' => $booking_data['refund_amount'] ?? '',
            'contact_email' => get_option('wcefp_contact_email', get_option('admin_email'))
        ]);
        
        $result = $this->notification_service->send_notification(
            NotificationService::TYPE_BOOKING_CANCELLED,
            $recipients,
            $data
        );
        
        Logger::info("Booking cancellation notification sent", [
            'booking_id' => $booking_id,
            'success' => $result['sent']
        ]);
        
        // Send admin notification
        $this->send_admin_booking_notification($booking_id, $booking_data, 'cancellation');
        
        // Check if we should notify waitlisted customers
        $this->check_waitlist_notifications($booking_data['product_id'] ?? 0, $booking_data['occurrence_id'] ?? 0);
    }
    
    /**
     * Handle booking rescheduled event
     *
     * @param int $booking_id Booking ID
     * @param array $old_data Old booking data
     * @param array $new_data New booking data
     */
    public function handle_booking_rescheduled($booking_id, $old_data, $new_data) {
        Logger::info("Processing booking reschedule automation for booking {$booking_id}");
        
        // Cancel old reminders
        $this->notification_service->cancel_booking_notifications($booking_id);
        
        // Send reschedule notification
        $recipients = [
            [
                'email' => $new_data['customer_email'] ?? '',
                'name' => $new_data['customer_name'] ?? '',
                'type' => 'customer'
            ]
        ];
        
        $data = array_merge($new_data, [
            'booking_id' => $booking_id,
            'old_booking_date' => $old_data['booking_date'] ?? '',
            'old_booking_time' => $old_data['booking_time'] ?? '',
            'site_name' => get_bloginfo('name'),
            'view_booking_url' => $this->get_booking_view_url($booking_id)
        ]);
        
        $result = $this->notification_service->send_notification(
            NotificationService::TYPE_BOOKING_RESCHEDULED,
            $recipients,
            $data
        );
        
        // Schedule new reminders
        $this->notification_service->schedule_booking_reminders($booking_id, $new_data);
        
        Logger::info("Booking reschedule notification sent and new reminders scheduled", [
            'booking_id' => $booking_id,
            'success' => $result['sent']
        ]);
    }
    
    /**
     * Handle capacity threshold reached
     *
     * @param int $product_id Product ID
     * @param array $capacity_data Capacity data
     */
    public function handle_capacity_threshold($product_id, $capacity_data) {
        Logger::info("Processing capacity threshold automation for product {$product_id}");
        
        // Send admin notification
        $admin_recipients = $this->get_admin_recipients();
        
        $data = array_merge($capacity_data, [
            'product_id' => $product_id,
            'site_name' => get_bloginfo('name'),
            'admin_event_url' => admin_url("post.php?post={$product_id}&action=edit"),
            'threshold_percentage' => get_option('wcefp_capacity_alert_threshold', 80)
        ]);
        
        $this->notification_service->send_notification(
            NotificationService::TYPE_CAPACITY_LOW,
            $admin_recipients,
            $data
        );
        
        // Optionally send marketing notification to increase bookings
        do_action('wcefp_capacity_marketing_trigger', $product_id, $capacity_data);
    }
    
    /**
     * Handle event sold out
     *
     * @param int $product_id Product ID
     * @param array $event_data Event data
     */
    public function handle_event_sold_out($product_id, $event_data) {
        Logger::info("Processing sold out automation for product {$product_id}");
        
        // Send admin notification
        $admin_recipients = $this->get_admin_recipients();
        
        $data = array_merge($event_data, [
            'product_id' => $product_id,
            'site_name' => get_bloginfo('name'),
            'admin_event_url' => admin_url("post.php?post={$product_id}&action=edit")
        ]);
        
        $this->notification_service->send_notification(
            NotificationService::TYPE_CAPACITY_FULL,
            $admin_recipients,
            $data
        );
        
        // Process waitlist if exists
        do_action('wcefp_process_waitlist', $product_id, $event_data);
    }
    
    /**
     * Send scheduled notification
     *
     * @param array $args Notification arguments
     */
    public function send_scheduled_notification($args) {
        $result = $this->notification_service->send_notification(
            $args['type'] ?? '',
            $args['recipients'] ?? [],
            $args['data'] ?? [],
            $args['options'] ?? []
        );
        
        Logger::info("Scheduled notification processed", [
            'type' => $args['type'] ?? 'unknown',
            'success' => $result['sent']
        ]);
    }
    
    /**
     * Run daily automation tasks
     */
    public function run_daily_automation() {
        Logger::info("Running daily notification automation");
        
        // Send post-event followup emails for events that finished yesterday
        $this->send_post_event_followups();
        
        // Clean up expired notifications
        $this->cleanup_expired_notifications();
        
        // Send daily admin summary if enabled
        $this->send_daily_admin_summary();
        
        // Check for upcoming events needing promotion
        $this->check_events_needing_promotion();
        
        Logger::info("Daily automation completed");
    }
    
    /**
     * Send post-event followup emails
     */
    private function send_post_event_followups() {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Query completed bookings from yesterday
        global $wpdb;
        $table_name = $wpdb->prefix . 'wcefp_booking_items';
        
        $completed_bookings = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT bi.booking_id, bi.product_id, o.customer_email, o.customer_name, 
                   p.post_title as event_title, occ.start_datetime
            FROM {$table_name} bi
            JOIN {$wpdb->prefix}wcefp_occurrences occ ON bi.occurrence_id = occ.id
            JOIN {$wpdb->posts} p ON bi.product_id = p.ID
            JOIN {$wpdb->prefix}wc_orders o ON bi.booking_id = o.id
            WHERE DATE(occ.start_datetime) = %s
            AND occ.start_datetime < NOW()
            AND bi.status = 'completed'
        ", $yesterday));
        
        foreach ($completed_bookings as $booking) {
            $recipients = [
                [
                    'email' => $booking->customer_email,
                    'name' => $booking->customer_name,
                    'type' => 'customer'
                ]
            ];
            
            $data = [
                'booking_id' => $booking->booking_id,
                'customer_name' => $booking->customer_name,
                'event_title' => $booking->event_title,
                'event_date' => date('F j, Y', strtotime($booking->start_datetime)),
                'site_name' => get_bloginfo('name'),
                'site_url' => home_url(),
                'review_url' => $this->get_review_url($booking->product_id, $booking->booking_id),
                'browse_experiences_url' => $this->get_experiences_page_url()
            ];
            
            $result = $this->notification_service->send_notification(
                NotificationService::TYPE_POST_EVENT_FOLLOWUP,
                $recipients,
                $data
            );
            
            if ($result['sent']) {
                Logger::info("Post-event followup sent", [
                    'booking_id' => $booking->booking_id,
                    'customer_email' => $booking->customer_email
                ]);
            }
        }
    }
    
    /**
     * Send daily admin summary
     */
    private function send_daily_admin_summary() {
        if (!get_option('wcefp_daily_admin_summary_enabled', false)) {
            return;
        }
        
        $admin_recipients = $this->get_admin_recipients();
        
        // Collect daily stats
        $stats = $this->get_daily_statistics();
        
        $data = array_merge($stats, [
            'site_name' => get_bloginfo('name'),
            'dashboard_url' => admin_url('admin.php?page=wcefp-dashboard'),
            'date' => date('F j, Y')
        ]);
        
        $this->notification_service->send_notification(
            'daily_admin_summary',
            $admin_recipients,
            $data
        );
    }
    
    /**
     * Check waitlist notifications
     *
     * @param int $product_id Product ID
     * @param int $occurrence_id Occurrence ID
     */
    private function check_waitlist_notifications($product_id, $occurrence_id) {
        // This would check if there are waitlisted customers for this event
        // and notify them that a spot is available
        
        do_action('wcefp_check_waitlist', $product_id, $occurrence_id);
    }
    
    /**
     * Send admin booking notification
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     * @param string $type Notification type
     */
    private function send_admin_booking_notification($booking_id, $booking_data, $type) {
        if (!get_option('wcefp_admin_booking_notifications_enabled', true)) {
            return;
        }
        
        $admin_recipients = $this->get_admin_recipients();
        
        $data = array_merge($booking_data, [
            'booking_id' => $booking_id,
            'notification_type' => $type,
            'site_name' => get_bloginfo('name'),
            'admin_booking_url' => admin_url("admin.php?page=wcefp-bookings&booking_id={$booking_id}"),
            'admin_dashboard_url' => admin_url('admin.php?page=wcefp-dashboard')
        ]);
        
        $this->notification_service->send_notification(
            'admin_booking_notification',
            $admin_recipients,
            $data
        );
    }
    
    /**
     * Get admin recipients
     *
     * @return array Admin recipients
     */
    private function get_admin_recipients() {
        $admin_emails = get_option('wcefp_admin_notification_emails', [get_option('admin_email')]);
        
        $recipients = [];
        foreach ($admin_emails as $email) {
            $recipients[] = [
                'email' => $email,
                'name' => 'Admin',
                'type' => 'admin'
            ];
        }
        
        return $recipients;
    }
    
    /**
     * Get booking view URL
     *
     * @param int $booking_id Booking ID
     * @return string URL
     */
    private function get_booking_view_url($booking_id) {
        return add_query_arg(['view-booking' => $booking_id], home_url('/my-account/'));
    }
    
    /**
     * Get review URL
     *
     * @param int $product_id Product ID
     * @param int $booking_id Booking ID
     * @return string URL
     */
    private function get_review_url($product_id, $booking_id) {
        return add_query_arg([
            'product_id' => $product_id,
            'booking_id' => $booking_id
        ], home_url('/write-review/'));
    }
    
    /**
     * Get experiences page URL
     *
     * @return string URL
     */
    private function get_experiences_page_url() {
        return get_permalink(wc_get_page_id('shop'));
    }
    
    /**
     * Generate calendar attachment (ICS file)
     *
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     */
    private function generate_calendar_attachment($booking_id, $booking_data) {
        // ICS file generation would go here
        // This is a placeholder for the calendar attachment functionality
        
        do_action('wcefp_generate_calendar_attachment', $booking_id, $booking_data);
    }
    
    /**
     * Get daily statistics
     *
     * @return array Statistics
     */
    private function get_daily_statistics() {
        global $wpdb;
        
        $today = date('Y-m-d');
        
        return [
            'todays_bookings' => 0, // Would query actual bookings
            'todays_revenue' => 0,  // Would query actual revenue
            'pending_bookings' => 0, // Would query pending bookings
            'upcoming_events' => 0  // Would query upcoming events
        ];
    }
    
    /**
     * Cleanup expired notifications
     */
    private function cleanup_expired_notifications() {
        // Clean up old notification logs, expired scheduled notifications, etc.
        do_action('wcefp_cleanup_notifications');
    }
    
    /**
     * Check for events needing promotion
     */
    private function check_events_needing_promotion() {
        // Check for events with low booking rates that might need marketing push
        do_action('wcefp_check_promotion_needed');
    }
    
    /**
     * Handle admin notifications
     *
     * @param string $type Notification type
     * @param array $data Notification data
     * @param array $options Options
     */
    public function handle_admin_notification($type, $data, $options = []) {
        $admin_recipients = $this->get_admin_recipients();
        
        $notification_data = array_merge($data, [
            'site_name' => get_bloginfo('name'),
            'admin_dashboard_url' => admin_url('admin.php?page=wcefp-dashboard'),
            'current_timestamp' => current_time('mysql'),
            'server_name' => $_SERVER['HTTP_HOST'] ?? 'Unknown'
        ]);
        
        $this->notification_service->send_notification(
            $type,
            $admin_recipients,
            $notification_data,
            $options
        );
    }
}