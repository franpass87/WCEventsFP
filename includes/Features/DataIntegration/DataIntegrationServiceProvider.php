<?php
/**
 * Data & Integration Service Provider
 * 
 * Manages export capabilities, content management, and data integration features.
 * Part of Phase 3: Data & Integration of the UI/UX Feature Pack.
 * 
 * @package WCEFP\Features\DataIntegration
 * @since 2.2.0
 */

namespace WCEFP\Features\DataIntegration;

use WCEFP\Core\ServiceProvider;

class DataIntegrationServiceProvider extends ServiceProvider {
    
    /**
     * Register services
     */
    public function register() {
        $this->container->singleton('export.manager', function() {
            return new ExportManager();
        });
        
        $this->container->singleton('gutenberg.manager', function() {
            return new GutenbergManager();
        });
        
        $this->container->singleton('calendar.integration', function() {
            return new CalendarIntegrationManager();
        });
    }
    
    /**
     * Boot services
     */
    public function boot() {
        // Initialize export manager
        $this->container->get('export.manager')->init();
        
        // Initialize Gutenberg block manager
        $this->container->get('gutenberg.manager')->init();
        
        // Initialize calendar integration manager
        $this->container->get('calendar.integration')->init();
        
        // Add admin menu items
        add_action('admin_menu', [$this, 'add_admin_menus'], 20);
        
        // Register AJAX handlers
        add_action('wp_ajax_wcefp_export_bookings', [$this, 'handle_export_bookings']);
        add_action('wp_ajax_wcefp_export_calendar', [$this, 'handle_export_calendar']);
        add_action('wp_ajax_wcefp_download_event_ics', [$this, 'handle_ics_download']);
        add_action('wp_ajax_nopriv_wcefp_download_event_ics', [$this, 'handle_ics_download']);
        add_action('wp_ajax_wcefp_get_calendar_buttons', [$this, 'handle_calendar_buttons']);
        add_action('wp_ajax_wcefp_track_calendar_usage', [$this, 'handle_calendar_tracking']);
        add_action('wp_ajax_nopriv_wcefp_track_calendar_usage', [$this, 'handle_calendar_tracking']);
        add_action('wp_ajax_wcefp_generate_admin_calendar_feed', [$this, 'handle_generate_admin_feed']);
        add_action('wp_ajax_wcefp_revoke_admin_calendar_feed', [$this, 'handle_revoke_admin_feed']);
    }
    
    /**
     * Add admin menu items
     */
    public function add_admin_menus() {
        // Add export submenu
        add_submenu_page(
            'wcefp-main',
            __('Export Data', 'wceventsfp'),
            __('Export', 'wceventsfp'),
            'export',
            'wcefp-export',
            [$this, 'render_export_page']
        );
        
        // Add calendar integration submenu
        add_submenu_page(
            'wcefp-main',
            __('Calendar Integration', 'wceventsfp'),
            __('Calendar', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-calendar',
            [$this, 'render_calendar_page']
        );
    }
    
    /**
     * Render export page
     */
    public function render_export_page() {
        $export_manager = $this->container->get('export.manager');
        include WCEFP_PLUGIN_DIR . 'admin/views/export-page.php';
    }
    
    /**
     * Render calendar integration page
     */
    public function render_calendar_page() {
        include WCEFP_PLUGIN_DIR . 'admin/views/calendar-integration.php';
    }
    
    /**
     * Handle bookings export AJAX request
     */
    public function handle_export_bookings() {
        if (!current_user_can('export') || !wp_verify_nonce($_POST['nonce'] ?? '', 'wcefp_export')) {
            wp_die(__('Access denied.', 'wceventsfp'));
        }
        
        $export_manager = $this->container->get('export.manager');
        $export_manager->export_bookings_csv($_POST);
    }
    
    /**
     * Handle calendar export AJAX request
     */
    public function handle_export_calendar() {
        if (!current_user_can('export') || !wp_verify_nonce($_POST['nonce'] ?? '', 'wcefp_export')) {
            wp_die(__('Access denied.', 'wceventsfp'));
        }
        
        $export_manager = $this->container->get('export.manager');
        $export_manager->export_calendar_ics($_POST);
    }
    
    /**
     * Handle ICS download request
     */
    public function handle_ics_download() {
        $calendar_integration = $this->container->get('calendar.integration');
        $calendar_integration->handle_ics_download();
    }
    
    /**
     * Handle calendar buttons request
     */
    public function handle_calendar_buttons() {
        check_ajax_referer('wcefp_calendar', 'nonce');
        
        $booking_id = absint($_POST['booking_id'] ?? 0);
        
        if (!$booking_id) {
            wp_send_json_error(['message' => __('Invalid booking ID', 'wceventsfp')]);
        }
        
        // Get booking data
        global $wpdb;
        $booking_data = $wpdb->get_row($wpdb->prepare("
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
        
        if (!$booking_data) {
            wp_send_json_error(['message' => __('Booking not found', 'wceventsfp')]);
        }
        
        $calendar_integration = $this->container->get('calendar.integration');
        $buttons = $calendar_integration->generate_calendar_buttons($booking_data);
        
        wp_send_json_success(['buttons' => $buttons]);
    }
    
    /**
     * Handle calendar usage tracking
     */
    public function handle_calendar_tracking() {
        check_ajax_referer('wcefp_calendar', 'nonce');
        
        $calendar_type = sanitize_text_field($_POST['calendar_type'] ?? '');
        
        if (empty($calendar_type)) {
            wp_send_json_error();
        }
        
        // Log calendar usage for analytics
        $usage_data = get_option('wcefp_calendar_usage', []);
        $today = date('Y-m-d');
        
        if (!isset($usage_data[$today])) {
            $usage_data[$today] = [];
        }
        
        if (!isset($usage_data[$today][$calendar_type])) {
            $usage_data[$today][$calendar_type] = 0;
        }
        
        $usage_data[$today][$calendar_type]++;
        
        // Keep only last 30 days of data
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        foreach ($usage_data as $date => $data) {
            if ($date < $cutoff_date) {
                unset($usage_data[$date]);
            }
        }
        
        update_option('wcefp_calendar_usage', $usage_data);
        
        wp_send_json_success();
    }
    
    /**
     * Handle generate admin calendar feed
     */
    public function handle_generate_admin_feed() {
        $calendar_integration = $this->container->get('calendar.integration');
        $calendar_integration->generate_admin_calendar_feed();
    }
    
    /**
     * Handle revoke admin calendar feed
     */
    public function handle_revoke_admin_feed() {
        check_ajax_referer('wcefp_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'wceventsfp')]);
        }
        
        // Remove admin calendar token
        delete_option('wcefp_admin_calendar_token');
        delete_option('wcefp_admin_calendar_token_expiry');
        
        wp_send_json_success(['message' => __('Calendar feed revoked successfully', 'wceventsfp')]);
    }
}