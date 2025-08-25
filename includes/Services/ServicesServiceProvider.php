<?php
/**
 * Services Service Provider
 * 
 * @package WCEFP
 * @subpackage Services
 * @since 2.2.0
 */

namespace WCEFP\Services;

use WCEFP\Core\Container;
use WCEFP\Services\Domain\SchedulingService;
use WCEFP\Services\Domain\TicketsService;
use WCEFP\Services\Domain\CapacityService;
use WCEFP\Services\Domain\ExtrasService;
use WCEFP\Services\Domain\MeetingPointService;
use WCEFP\Services\Domain\PolicyService;
use WCEFP\Services\Domain\NotificationService;
use WCEFP\Services\Domain\StockHoldManager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service provider for domain services
 */
class ServicesServiceProvider {
    
    /**
     * DI Container
     * 
     * @var Container
     */
    private $container;
    
    /**
     * Constructor
     * 
     * @param Container $container DI container
     */
    public function __construct(Container $container) {
        $this->container = $container;
        $this->register();
        $this->init();
    }
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    private function register() {
        // Register domain services as singletons
        $this->container->singleton('scheduling_service', function() {
            return new SchedulingService();
        });
        
        $this->container->singleton('tickets_service', function() {
            return new TicketsService();
        });
        
        $this->container->singleton('capacity_service', function() {
            return new CapacityService();
        });
        
        $this->container->singleton('extras_service', function() {
            return new ExtrasService();
        });
        
        $this->container->singleton('meeting_point_service', function() {
            return new MeetingPointService();
        });
        
        $this->container->singleton('policy_service', function() {
            return new PolicyService();
        });
        
        $this->container->singleton('notification_service', function() {
            return new NotificationService();
        });
        
        $this->container->singleton('stock_hold_manager', function() {
            return new StockHoldManager();
        });
    }
    
    /**
     * Initialize service hooks and functionality
     * 
     * @return void
     */
    private function init() {
        // Register WordPress hooks
        add_action('init', [$this, 'init_services']);
        add_action('wp_loaded', [$this, 'setup_cron_jobs']);
        add_action('wcefp_send_scheduled_notification', [$this, 'handle_scheduled_notification'], 10, 4);
        
        // Capacity alerts hooks
        add_action('wcefp_capacity_low_availability', [$this, 'handle_capacity_low_alert'], 10, 3);
        add_action('wcefp_capacity_nearly_full', [$this, 'handle_capacity_nearly_full_alert'], 10, 3);
        add_action('wcefp_capacity_waitlist_threshold', [$this, 'handle_capacity_waitlist_alert'], 10, 3);
        
        // Booking hooks
        add_action('wcefp_booking_confirmed', [$this, 'handle_booking_confirmed'], 10, 2);
        add_action('wcefp_booking_cancelled', [$this, 'handle_booking_cancelled'], 10, 2);
        add_action('wcefp_booking_rescheduled', [$this, 'handle_booking_rescheduled'], 10, 3);
        
        // Cleanup hooks
        add_action('wcefp_cleanup_expired_reservations', [$this, 'cleanup_expired_reservations']);
        add_action('wcefp_cleanup_expired_holds', [$this, 'cleanup_expired_holds']);
        
        // Stock hold cleanup cron
        add_filter('cron_schedules', [$this, 'add_custom_cron_schedules']);
        
        // AJAX hooks for admin
        add_action('wp_ajax_wcefp_get_available_slots', [$this, 'ajax_get_available_slots']);
        add_action('wp_ajax_wcefp_calculate_booking_price', [$this, 'ajax_calculate_booking_price']);
        add_action('wp_ajax_wcefp_validate_booking_selection', [$this, 'ajax_validate_booking_selection']);
        
        // Frontend AJAX hooks
        add_action('wp_ajax_nopriv_wcefp_get_available_slots', [$this, 'ajax_get_available_slots']);
        add_action('wp_ajax_nopriv_wcefp_calculate_booking_price', [$this, 'ajax_calculate_booking_price']);
    }
    
    /**
     * Initialize services after WordPress is loaded
     * 
     * @return void
     */
    public function init_services() {
        // Create database tables if needed
        if (get_option('wcefp_services_db_version') !== WCEFP_VERSION) {
            $this->create_database_tables();
            update_option('wcefp_services_db_version', WCEFP_VERSION);
        }
        
        // Initialize services that need WordPress context
        do_action('wcefp_services_initialized');
    }
    
    /**
     * Create required database tables
     * 
     * @return void
     */
    private function create_database_tables() {
        SchedulingService::create_tables();
        ExtrasService::create_tables();
    }
    
    /**
     * Setup cron jobs for service maintenance
     * 
     * @return void
     */
    public function setup_cron_jobs() {
        // Schedule cleanup of expired reservations
        if (!wp_next_scheduled('wcefp_cleanup_expired_reservations')) {
            wp_schedule_event(time(), 'hourly', 'wcefp_cleanup_expired_reservations');
        }
    }
    
    /**
     * Handle scheduled notification sending
     * 
     * @param string $type Notification type
     * @param array $recipients Recipients
     * @param array $data Notification data
     * @param array $options Options
     * @return void
     */
    public function handle_scheduled_notification($type, $recipients, $data, $options = []) {
        $notification_service = $this->container->get('notification_service');
        $notification_service->send_notification($type, $recipients, $data, $options);
    }
    
    /**
     * Handle capacity low availability alert
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @param array $capacity_info Capacity info
     * @return void
     */
    public function handle_capacity_low_alert($product_id, $slot_datetime, $capacity_info) {
        $notification_service = $this->container->get('notification_service');
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $recipients = [
            [
                'email' => $admin_email,
                'name' => get_bloginfo('name'),
                'type' => 'admin'
            ]
        ];
        
        $data = [
            'event_title' => $product->get_name(),
            'booking_date' => date('Y-m-d', strtotime($slot_datetime)),
            'booking_time' => date('H:i', strtotime($slot_datetime)),
            'available_spots' => $capacity_info['available'],
            'total_capacity' => $capacity_info['capacity'],
            'product_id' => $product_id
        ];
        
        $notification_service->send_notification(
            NotificationService::TYPE_CAPACITY_LOW,
            $recipients,
            $data
        );
    }
    
    /**
     * Handle capacity nearly full alert
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @param array $capacity_info Capacity info
     * @return void
     */
    public function handle_capacity_nearly_full_alert($product_id, $slot_datetime, $capacity_info) {
        $notification_service = $this->container->get('notification_service');
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        $admin_email = get_option('admin_email');
        $recipients = [
            [
                'email' => $admin_email,
                'name' => get_bloginfo('name'),
                'type' => 'admin'
            ]
        ];
        
        $data = [
            'event_title' => $product->get_name(),
            'booking_date' => date('Y-m-d', strtotime($slot_datetime)),
            'booking_time' => date('H:i', strtotime($slot_datetime)),
            'available_spots' => $capacity_info['available'],
            'total_capacity' => $capacity_info['capacity'],
            'product_id' => $product_id
        ];
        
        $notification_service->send_notification(
            NotificationService::TYPE_CAPACITY_FULL,
            $recipients,
            $data
        );
    }
    
    /**
     * Handle capacity waitlist threshold alert
     * 
     * @param int $product_id Product ID
     * @param string $slot_datetime Slot datetime
     * @param array $capacity_info Capacity info
     * @return void
     */
    public function handle_capacity_waitlist_alert($product_id, $slot_datetime, $capacity_info) {
        // This would trigger waitlist functionality
        // For now, just log the event
        do_action('wcefp_waitlist_threshold_reached', $product_id, $slot_datetime, $capacity_info);
    }
    
    /**
     * Handle booking confirmed event
     * 
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     * @return void
     */
    public function handle_booking_confirmed($booking_id, $booking_data) {
        $notification_service = $this->container->get('notification_service');
        
        // Send confirmation notification
        $recipients = [
            [
                'email' => $booking_data['customer_email'] ?? '',
                'name' => $booking_data['customer_name'] ?? '',
                'type' => 'customer'
            ]
        ];
        
        $notification_service->send_notification(
            NotificationService::TYPE_BOOKING_CONFIRMED,
            $recipients,
            $booking_data
        );
        
        // Schedule reminder notifications
        $notification_service->schedule_booking_reminders($booking_id, $booking_data);
    }
    
    /**
     * Handle booking cancelled event
     * 
     * @param int $booking_id Booking ID
     * @param array $booking_data Booking data
     * @return void
     */
    public function handle_booking_cancelled($booking_id, $booking_data) {
        $notification_service = $this->container->get('notification_service');
        
        // Send cancellation notification
        $recipients = [
            [
                'email' => $booking_data['customer_email'] ?? '',
                'name' => $booking_data['customer_name'] ?? '',
                'type' => 'customer'
            ]
        ];
        
        $notification_service->send_notification(
            NotificationService::TYPE_BOOKING_CANCELLED,
            $recipients,
            $booking_data
        );
        
        // Cancel scheduled reminders
        $notification_service->cancel_booking_notifications($booking_id);
    }
    
    /**
     * Handle booking rescheduled event
     * 
     * @param int $booking_id Booking ID
     * @param array $old_booking_data Old booking data
     * @param array $new_booking_data New booking data
     * @return void
     */
    public function handle_booking_rescheduled($booking_id, $old_booking_data, $new_booking_data) {
        $notification_service = $this->container->get('notification_service');
        
        // Send reschedule notification
        $recipients = [
            [
                'email' => $new_booking_data['customer_email'] ?? '',
                'name' => $new_booking_data['customer_name'] ?? '',
                'type' => 'customer'
            ]
        ];
        
        $notification_service->send_notification(
            NotificationService::TYPE_BOOKING_RESCHEDULED,
            $recipients,
            $new_booking_data
        );
        
        // Cancel old reminders and schedule new ones
        $notification_service->cancel_booking_notifications($booking_id);
        $notification_service->schedule_booking_reminders($booking_id, $new_booking_data);
    }
    
    /**
     * Cleanup expired reservations
     * 
     * @return void
     */
    public function cleanup_expired_reservations() {
        $scheduling_service = $this->container->get('scheduling_service');
        $extras_service = $this->container->get('extras_service');
        
        $scheduling_service->cleanup_expired_reservations();
        $extras_service->cleanup_expired_reservations();
    }
    
    /**
     * Cleanup expired stock holds
     * 
     * @return void
     */
    public function cleanup_expired_holds() {
        $stock_hold_manager = $this->container->get('stock_hold_manager');
        $cleaned = $stock_hold_manager->cleanup_expired_holds();
        
        if ($cleaned > 0) {
            Logger::log('info', "Cleaned up {$cleaned} expired stock holds");
        }
    }
    
    /**
     * Add custom cron schedules
     * 
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function add_custom_cron_schedules($schedules) {
        $schedules['wcefp_5_minutes'] = [
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 Minutes', 'wceventsfp')
        ];
        
        $schedules['wcefp_15_minutes'] = [
            'interval' => 900, // 15 minutes
            'display' => __('Every 15 Minutes', 'wceventsfp')
        ];
        
        return $schedules;
    }
    
    /**
     * AJAX: Get available slots
     * 
     * @return void
     */
    public function ajax_get_available_slots() {
        check_ajax_referer('wcefp_frontend', 'nonce');
        
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        if (!$product_id || !$date) {
            wp_send_json_error('Missing required parameters');
        }
        
        $scheduling_service = $this->container->get('scheduling_service');
        $slots = $scheduling_service->get_available_slots($product_id, $date);
        
        wp_send_json_success([
            'slots' => $slots,
            'date' => $date
        ]);
    }
    
    /**
     * AJAX: Calculate booking price
     * 
     * @return void
     */
    public function ajax_calculate_booking_price() {
        check_ajax_referer('wcefp_frontend', 'nonce');
        
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $tickets = $_POST['tickets'] ?? [];
        $extras = $_POST['extras'] ?? [];
        $date = sanitize_text_field($_POST['date'] ?? '');
        
        if (!$product_id) {
            wp_send_json_error('Missing product ID');
        }
        
        $tickets_service = $this->container->get('tickets_service');
        $extras_service = $this->container->get('extras_service');
        
        $context = ['date' => $date];
        
        // Calculate ticket prices
        $ticket_calculation = $tickets_service->calculate_ticket_prices($product_id, $tickets, $context);
        
        // Calculate extras prices
        $extras_calculation = $extras_service->calculate_extras_price($product_id, $extras, array_merge($context, [
            'tickets' => $tickets
        ]));
        
        // Combine calculations
        $total_calculation = [
            'tickets' => $ticket_calculation,
            'extras' => $extras_calculation,
            'grand_total' => $ticket_calculation['total'] + $extras_calculation['total']
        ];
        
        wp_send_json_success($total_calculation);
    }
    
    /**
     * AJAX: Validate booking selection
     * 
     * @return void
     */
    public function ajax_validate_booking_selection() {
        check_ajax_referer('wcefp_frontend', 'nonce');
        
        $product_id = (int) ($_POST['product_id'] ?? 0);
        $tickets = $_POST['tickets'] ?? [];
        $extras = $_POST['extras'] ?? [];
        $slot_datetime = sanitize_text_field($_POST['slot_datetime'] ?? '');
        
        if (!$product_id) {
            wp_send_json_error('Missing product ID');
        }
        
        $tickets_service = $this->container->get('tickets_service');
        $extras_service = $this->container->get('extras_service');
        $capacity_service = $this->container->get('capacity_service');
        
        $context = [
            'date' => date('Y-m-d', strtotime($slot_datetime)),
            'tickets' => $tickets
        ];
        
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => []
        ];
        
        // Validate tickets
        $ticket_validation = $tickets_service->validate_ticket_selection($product_id, $tickets);
        if (!$ticket_validation['valid']) {
            $validation['valid'] = false;
            $validation['errors'] = array_merge($validation['errors'], $ticket_validation['errors']);
        }
        $validation['warnings'] = array_merge($validation['warnings'], $ticket_validation['warnings'] ?? []);
        
        // Validate extras
        $extras_validation = $extras_service->validate_extras_selection($product_id, $extras, $context);
        if (!$extras_validation['valid']) {
            $validation['valid'] = false;
            $validation['errors'] = array_merge($validation['errors'], $extras_validation['errors']);
        }
        $validation['warnings'] = array_merge($validation['warnings'], $extras_validation['warnings'] ?? []);
        
        // Validate capacity
        if ($slot_datetime) {
            $total_participants = array_sum($tickets);
            $capacity_check = $capacity_service->check_availability($product_id, $slot_datetime, $total_participants);
            
            if (!$capacity_check['available']) {
                $validation['valid'] = false;
                $validation['errors'][] = $capacity_check['message'];
            } elseif (!empty($capacity_check['warning'])) {
                $validation['warnings'][] = $capacity_check['message'];
            }
        }
        
        wp_send_json_success($validation);
    }
    
    /**
     * Get service instance
     * 
     * @param string $service_name Service name
     * @return object|null Service instance
     */
    public function get_service($service_name) {
        return $this->container->get($service_name);
    }
}