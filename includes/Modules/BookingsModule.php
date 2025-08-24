<?php
/**
 * Bookings Module
 * 
 * @package WCEFP
 * @subpackage Modules
 * @since 2.1.4
 */

namespace WCEFP\Modules;

use WCEFP\Core\ServiceProvider;
use WCEFP\Admin\Tables\BookingsListTable;
use WCEFP\Features\BookingFeatures\DigitalCheckinManager;
use WCEFP\Utils\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Bookings management module
 */
class BookingsModule extends ServiceProvider {
    
    /**
     * Register module services
     * 
     * @return void
     */
    public function register(): void {
        $this->container->singleton('bookings.table', BookingsListTable::class);
        $this->container->singleton('bookings.checkin', DigitalCheckinManager::class);
    }
    
    /**
     * Boot module services
     * 
     * @return void
     */
    public function boot(): void {
        // Hook into WordPress lifecycle
        add_action('init', [$this, 'initialize_cpt'], 20);
        add_action('admin_menu', [$this, 'add_admin_pages'], 15);
        add_action('wp_ajax_wcefp_booking_action', [$this, 'handle_booking_actions']);
        
        Logger::info('Bookings module booted successfully');
    }
    
    /**
     * Initialize custom post types and taxonomies
     * 
     * @return void
     */
    public function initialize_cpt(): void {
        // Bookings are stored as WooCommerce order line items
        // No additional CPTs needed for core booking functionality
    }
    
    /**
     * Add admin menu pages - handled by central MenuManager
     * 
     * @return void
     */
    public function add_admin_pages(): void {
        // Menu registration moved to MenuManager for centralized control
        // This method kept for module compatibility but no longer adds menus
    }
    
    /**
     * Render bookings management page
     * 
     * @return void
     */
    public function render_bookings_page(): void {
        $table = $this->container->get('bookings.table');
        
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Booking Management', 'wceventsfp') . '</h1>';
        
        // Render bookings table
        $table->prepare_items();
        $table->display();
        
        echo '</div>';
    }
    
    /**
     * Handle AJAX booking actions
     * 
     * @return void
     */
    public function handle_booking_actions(): void {
        // Verify nonce and capabilities
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'wcefp_booking_actions') || 
            !current_user_can('manage_woocommerce')) {
            wp_die(__('Security check failed', 'wceventsfp'));
        }
        
        $action = sanitize_text_field($_POST['booking_action'] ?? '');
        $booking_id = intval($_POST['booking_id'] ?? 0);
        
        switch ($action) {
            case 'confirm':
                $this->confirm_booking($booking_id);
                break;
            case 'cancel':
                $this->cancel_booking($booking_id);
                break;
            case 'checkin':
                $checkin_manager = $this->container->get('bookings.checkin');
                $checkin_manager->process_checkin($booking_id);
                break;
        }
        
        wp_send_json_success(['message' => __('Action completed successfully', 'wceventsfp')]);
    }
    
    /**
     * Confirm a booking
     * 
     * @param int $booking_id
     * @return void
     */
    private function confirm_booking(int $booking_id): void {
        // Implementation for booking confirmation
        do_action('wcefp_booking_confirmed', $booking_id);
    }
    
    /**
     * Cancel a booking
     * 
     * @param int $booking_id
     * @return void
     */
    private function cancel_booking(int $booking_id): void {
        // Implementation for booking cancellation
        do_action('wcefp_booking_cancelled', $booking_id);
    }
}