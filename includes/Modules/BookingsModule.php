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
        // Security check
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        // Handle bulk actions and form submissions
        $this->handle_booking_list_actions();
        
        try {
            $table = $this->container->get('bookings.table');
        } catch (\Exception $e) {
            // Fallback: create table directly if container fails
            $table = new \WCEFP\Admin\Tables\BookingsListTable();
        }
        
        ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php esc_html_e('Prenotazioni', 'wceventsfp'); ?></h1>
                <div class="wcefp-page-actions">
                    <a href="<?php echo admin_url('admin.php?page=wcefp-booking-calendar'); ?>" class="page-title-action">
                        📅 <?php esc_html_e('Vista Calendario', 'wceventsfp'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=wcefp-bookings&action=export'); ?>" class="page-title-action">
                        📊 <?php esc_html_e('Esporta CSV', 'wceventsfp'); ?>
                    </a>
                </div>
            </div>
            
            <div class="wcefp-bookings-overview">
                <?php $this->render_bookings_stats(); ?>
            </div>
            
            <form method="get" id="wcefp-bookings-filter" class="wcefp-bookings-form">
                <input type="hidden" name="page" value="wcefp-bookings" />
                
                <?php
                $table->prepare_items();
                $table->search_box(__('Cerca prenotazioni', 'wceventsfp'), 'bookings');
                $table->display();
                ?>
            </form>
        </div>
        
        <style>
        .wcefp-page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .wcefp-page-actions {
            display: flex;
            gap: 10px;
        }
        .wcefp-bookings-overview {
            margin-bottom: 20px;
        }
        .wcefp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .wcefp-stat-card {
            background: white;
            padding: 15px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            text-align: center;
        }
        .wcefp-stat-number {
            display: block;
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 5px;
        }
        .wcefp-stat-label {
            color: #646970;
            font-size: 13px;
        }
        .wcefp-bookings-form {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
        }
        </style>
        <?php
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
    
    /**
     * Handle booking list actions (bulk actions, filters, etc.)
     * 
     * @return void
     */
    private function handle_booking_list_actions(): void {
        if (!isset($_REQUEST['action']) && !isset($_REQUEST['action2'])) {
            return;
        }
        
        $action = $_REQUEST['action'] !== -1 ? $_REQUEST['action'] : $_REQUEST['action2'];
        
        if (!$action || $action === -1) {
            return;
        }
        
        // Handle individual actions
        if (isset($_REQUEST['booking']) && is_numeric($_REQUEST['booking'])) {
            $booking_id = absint($_REQUEST['booking']);
            $this->handle_single_booking_action($action, $booking_id);
            return;
        }
        
        // Handle bulk actions
        if (isset($_REQUEST['booking']) && is_array($_REQUEST['booking'])) {
            $booking_ids = array_map('absint', $_REQUEST['booking']);
            $this->handle_bulk_booking_action($action, $booking_ids);
            return;
        }
    }
    
    /**
     * Handle single booking action
     * 
     * @param string $action
     * @param int $booking_id
     * @return void
     */
    private function handle_single_booking_action(string $action, int $booking_id): void {
        // Verify nonce for the specific action
        $nonce_action = $action . '_booking_' . $booking_id;
        if (!wp_verify_nonce($_REQUEST['_wpnonce'] ?? '', $nonce_action)) {
            wp_die(__('Security check failed.', 'wceventsfp'));
        }
        
        $success_message = '';
        $error_message = '';
        
        switch ($action) {
            case 'confirm':
                $this->confirm_booking($booking_id);
                $success_message = __('Booking confirmed successfully.', 'wceventsfp');
                break;
                
            case 'cancel':
                $this->cancel_booking($booking_id);
                $success_message = __('Booking cancelled successfully.', 'wceventsfp');
                break;
                
            case 'delete':
                // For safety, require additional confirmation for delete
                if (!isset($_REQUEST['confirm_delete'])) {
                    $error_message = __('Delete action requires confirmation.', 'wceventsfp');
                } else {
                    do_action('wcefp_booking_deleted', $booking_id);
                    $success_message = __('Booking deleted successfully.', 'wceventsfp');
                }
                break;
        }
        
        // Add admin notice
        if ($success_message) {
            add_action('admin_notices', function() use ($success_message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($success_message) . '</p></div>';
            });
        } elseif ($error_message) {
            add_action('admin_notices', function() use ($error_message) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($error_message) . '</p></div>';
            });
        }
        
        // Redirect to avoid resubmission
        wp_redirect(admin_url('admin.php?page=wcefp-bookings'));
        exit;
    }
    
    /**
     * Handle bulk booking action
     * 
     * @param string $action
     * @param array $booking_ids
     * @return void
     */
    private function handle_bulk_booking_action(string $action, array $booking_ids): void {
        if (empty($booking_ids)) {
            return;
        }
        
        $count = 0;
        $action_label = '';
        
        foreach ($booking_ids as $booking_id) {
            switch ($action) {
                case 'confirm':
                    $this->confirm_booking($booking_id);
                    $count++;
                    $action_label = __('confirmed', 'wceventsfp');
                    break;
                    
                case 'cancel':
                    $this->cancel_booking($booking_id);
                    $count++;
                    $action_label = __('cancelled', 'wceventsfp');
                    break;
                    
                case 'delete':
                    do_action('wcefp_booking_deleted', $booking_id);
                    $count++;
                    $action_label = __('deleted', 'wceventsfp');
                    break;
                    
                case 'export':
                    // Handle export separately
                    $this->export_bookings_csv($booking_ids);
                    return;
            }
        }
        
        if ($count > 0) {
            $message = sprintf(
                _n(
                    '%d booking %s.',
                    '%d bookings %s.',
                    $count,
                    'wceventsfp'
                ),
                $count,
                $action_label
            );
            
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            });
        }
        
        // Redirect to avoid resubmission
        wp_redirect(admin_url('admin.php?page=wcefp-bookings'));
        exit;
    }
    
    /**
     * Export bookings to CSV
     * 
     * @param array $booking_ids Optional specific booking IDs to export
     * @return void
     */
    private function export_bookings_csv(array $booking_ids = []): void {
        // This would implement CSV export functionality
        // For now, just provide a placeholder response
        
        $filename = 'wcefp_bookings_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID',
            'Customer Name',
            'Customer Email',
            'Event Title',
            'Event Date',
            'Participants',
            'Status',
            'Amount',
            'Booking Date'
        ]);
        
        // Sample data for now - would query actual bookings
        $sample_bookings = [
            [1, 'Mario Rossi', 'mario@example.com', 'Wine Tasting', '2024-01-15 18:00', 2, 'confirmed', '89.90', '2024-01-12'],
            [2, 'Anna Bianchi', 'anna@example.com', 'Cooking Class', '2024-01-22 19:30', 4, 'pending', '179.80', '2024-01-14']
        ];
        
        foreach ($sample_bookings as $booking) {
            if (empty($booking_ids) || in_array($booking[0], $booking_ids)) {
                fputcsv($output, $booking);
            }
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Render bookings statistics overview
     * 
     * @return void
     */
    private function render_bookings_stats(): void {
        // Get booking statistics (placeholder - would query actual data)
        $stats = $this->get_booking_statistics();
        
        ?>
        <div class="wcefp-stats-grid">
            <div class="wcefp-stat-card">
                <span class="wcefp-stat-number"><?php echo absint($stats['total']); ?></span>
                <span class="wcefp-stat-label"><?php esc_html_e('Prenotazioni Totali', 'wceventsfp'); ?></span>
            </div>
            <div class="wcefp-stat-card">
                <span class="wcefp-stat-number"><?php echo absint($stats['confirmed']); ?></span>
                <span class="wcefp-stat-label"><?php esc_html_e('Confermate', 'wceventsfp'); ?></span>
            </div>
            <div class="wcefp-stat-card">
                <span class="wcefp-stat-number"><?php echo absint($stats['pending']); ?></span>
                <span class="wcefp-stat-label"><?php esc_html_e('In Attesa', 'wceventsfp'); ?></span>
            </div>
            <div class="wcefp-stat-card">
                <span class="wcefp-stat-number"><?php echo esc_html(wc_price($stats['revenue'])); ?></span>
                <span class="wcefp-stat-label"><?php esc_html_e('Ricavi Totali', 'wceventsfp'); ?></span>
            </div>
            <div class="wcefp-stat-card">
                <span class="wcefp-stat-number"><?php echo absint($stats['this_month']); ?></span>
                <span class="wcefp-stat-label"><?php esc_html_e('Questo Mese', 'wceventsfp'); ?></span>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get booking statistics
     * 
     * @return array
     */
    private function get_booking_statistics(): array {
        // Placeholder implementation - would query actual database
        return [
            'total' => 156,
            'confirmed' => 98,
            'pending' => 34,
            'cancelled' => 24,
            'revenue' => 12450.50,
            'this_month' => 47
        ];
    }
}