<?php
/**
 * Menu Manager
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.1.1
 */

namespace WCEFP\Admin;

use WCEFP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manages WordPress admin menus for WCEFP
 */
class MenuManager {
    
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
        $this->init();
    }
    
    /**
     * Initialize menu hooks
     * 
     * @return void
     */
    private function init() {
        add_action('admin_menu', [$this, 'add_admin_menu'], 10);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts'], 10);
        
        // Add AJAX handlers for booking quick actions
        add_action('wp_ajax_wcefp_booking_quick_action', [$this, 'handle_booking_quick_action']);
        add_action('wp_ajax_wcefp_get_booking_calendar_events', [$this, 'handle_get_calendar_events']);
    }
    
    /**
     * Load legacy admin classes
     * 
     * @return void
     */
    private function load_legacy_admin_classes() {
        // Load legacy recurring class
        $recurring_file = WCEFP_PLUGIN_DIR . 'includes/Legacy/class-wcefp-recurring.php';
        if (file_exists($recurring_file)) {
            require_once $recurring_file;
        }
        
        // Load legacy vouchers admin class
        $vouchers_file = WCEFP_PLUGIN_DIR . 'admin/class-wcefp-vouchers-admin.php';
        if (file_exists($vouchers_file)) {
            require_once $vouchers_file;
            
            // Also load the vouchers table class
            $vouchers_table_file = WCEFP_PLUGIN_DIR . 'admin/class-wcefp-vouchers-table.php';
            if (file_exists($vouchers_table_file)) {
                require_once $vouchers_table_file;
            }
        }
    }
    
    /**
     * Add admin menu items
     * 
     * @return void
     */
    public function add_admin_menu() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        // Load legacy admin classes if they exist
        $this->load_legacy_admin_classes();
        
        // Main menu page
        add_menu_page(
            __('WC Events', 'wceventsfp'),
            __('WC Events', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp',
            [$this, 'render_main_page'],
            'dashicons-calendar-alt',
            56
        );
        
        // Submenu pages
        add_submenu_page(
            'wcefp',
            __('Occorrenze', 'wceventsfp'),
            __('Occorrenze', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-occurrences',
            [$this, 'render_occurrences_page']
        );
        
        add_submenu_page(
            'wcefp',
            __('Prenotazioni', 'wceventsfp'),
            __('Prenotazioni', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-bookings',
            [$this, 'render_bookings_page']
        );
        
        // Hidden booking view page (not shown in menu)
        add_submenu_page(
            null, // Hidden from menu
            __('Visualizza Prenotazione', 'wceventsfp'),
            __('Visualizza Prenotazione', 'wceventsfp'),
            'manage_wcefp_bookings',
            'wcefp-booking-view',
            [$this, 'render_booking_view_page']
        );
        
        // Booking calendar view (submenu under bookings)
        add_submenu_page(
            'wcefp',
            __('Calendario Prenotazioni', 'wceventsfp'),
            __('📅 Calendario', 'wceventsfp'),
            'manage_wcefp_bookings',
            'wcefp-booking-calendar',
            [$this, 'render_booking_calendar_page']
        );
        
        add_submenu_page(
            'wcefp',
            __('Vouchers', 'wceventsfp'),
            __('Vouchers', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-vouchers',
            [$this, 'render_vouchers_page']
        );
        
        add_submenu_page(
            'wcefp',
            __('Chiusure', 'wceventsfp'),
            __('Chiusure', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-closures',
            [$this, 'render_closures_page']
        );
        
        add_submenu_page(
            'wcefp',
            __('Impostazioni', 'wceventsfp'),
            __('Impostazioni', 'wceventsfp'),
            'manage_woocommerce',
            'wcefp-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Enqueue admin scripts
     * 
     * @return void
     */
    public function enqueue_admin_scripts() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wcefp') === false) {
            return;
        }
        
        // Enqueue modal system first
        wp_enqueue_script(
            'wcefp-modals',
            WCEFP_PLUGIN_URL . 'assets/js/wcefp-modals.js',
            ['jquery'],
            WCEFP_VERSION,
            true
        );
        
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('wp-jquery-ui-dialog');
    }
    
    /**
     * Render main admin page
     * 
     * @return void
     */
    public function render_main_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('WC Events Dashboard', 'wceventsfp') . '</h1>';
        echo '<div class="wcefp-admin-section">';
        echo '<p>' . esc_html__('Welcome to WCEventsFP administration panel.', 'wceventsfp') . '</p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render occurrences page
     * 
     * @return void
     */
    public function render_occurrences_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Occorrenze', 'wceventsfp') . '</h1>';
        
        // Check if we have access to occurrence management systems
        $has_recurring = class_exists('WCEFP_Recurring');
        $has_query_builder = false;
        
        try {
            $query_builder = $this->container->get('db.query_builder');
            $has_query_builder = ($query_builder !== null);
        } catch (\Exception $e) {
            // Query builder not available
        }
        
        if ($has_recurring || $has_query_builder) {
            echo '<div class="wcefp-occurrences-manager">';
            
            if ($has_recurring) {
                $this->render_occurrence_interface();
            }
            
            if ($has_query_builder) {
                echo '<h2>' . esc_html__('Existing Occurrences', 'wceventsfp') . '</h2>';
                try {
                    $occurrences = $query_builder->get_occurrences();
                    $this->render_occurrences_list($occurrences);
                } catch (\Exception $e) {
                    echo '<p class="notice notice-warning"><strong>' . esc_html__('Warning:', 'wceventsfp') . '</strong> ' . esc_html($e->getMessage()) . '</p>';
                }
            }
            
            echo '</div>';
        } else {
            echo '<div class="wcefp-occurrences-placeholder">';
            echo '<h2>' . esc_html__('Occurrence Management', 'wceventsfp') . '</h2>';
            echo '<p>' . esc_html__('The occurrence management system allows you to generate and manage event dates and times.', 'wceventsfp') . '</p>';
            echo '<p class="description">' . esc_html__('This feature is currently being loaded. Please ensure all plugin components are properly installed.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render bookings page
     * 
     * @return void
     */
    public function render_bookings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Prenotazioni', 'wceventsfp') . '</h1>';
        
        try {
            // Use the new WP_List_Table implementation for better UI/UX
            if (class_exists('WCEFP\Admin\Tables\BookingsListTable')) {
                $bookings_table = new \WCEFP\Admin\Tables\BookingsListTable();
                $bookings_table->prepare_items();
                
                echo '<form method="get" id="wcefp-bookings-filter">';
                echo '<input type="hidden" name="page" value="wcefp-bookings" />';
                $bookings_table->search_box(__('Search', 'wceventsfp'), 'bookings');
                $bookings_table->display();
                echo '</form>';
                
            } else {
                // Fallback to existing implementation
                $query_builder = $this->container->get('db.query_builder');
                if ($query_builder && method_exists($query_builder, 'get_bookings')) {
                    $bookings = $query_builder->get_bookings();
                    $this->render_bookings_list($bookings);
                } else {
                    echo '<div class="wcefp-bookings-placeholder">';
                    echo '<h2>' . esc_html__('Booking Management', 'wceventsfp') . '</h2>';
                    echo '<p>' . esc_html__('The booking management system displays and manages customer reservations.', 'wceventsfp') . '</p>';
                    echo '<p class="description">' . esc_html__('Query builder service not available. Please check plugin configuration.', 'wceventsfp') . '</p>';
                    echo '</div>';
                }
            }
        } catch (\Exception $e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>' . esc_html__('Error loading bookings:', 'wceventsfp') . '</strong> ' . esc_html($e->getMessage()) . '</p>';
            echo '<p class="description">' . esc_html__('This may be due to database connection issues or missing plugin components.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render vouchers page
     * 
     * @return void
     */
    public function render_vouchers_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Vouchers', 'wceventsfp') . '</h1>';
        
        // Integrate with existing voucher management
        if (class_exists('WCEFP_Vouchers_Admin')) {
            // Ensure the class is properly initialized
            if (method_exists('WCEFP_Vouchers_Admin', 'dispatch')) {
                // Remove the wrap div since dispatch() may include its own
                echo '</div>';
                WCEFP_Vouchers_Admin::dispatch();
                return;
            }
        }
        
        // Fallback: show placeholder interface
        echo '<div class="wcefp-voucher-placeholder">';
        echo '<p>' . esc_html__('Voucher management system is being loaded...', 'wceventsfp') . '</p>';
        echo '<p class="description">' . esc_html__('If this message persists, please check that all plugin components are properly installed.', 'wceventsfp') . '</p>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Render closures page
     * 
     * @return void
     */
    public function render_closures_page() {
        if (class_exists('WCEFP_Closures') && method_exists('WCEFP_Closures', 'render_admin_page')) {
            WCEFP_Closures::render_admin_page();
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Chiusure', 'wceventsfp') . '</h1>';
            echo '<div class="wcefp-closures-placeholder">';
            echo '<p>' . esc_html__('Closure management system is being loaded...', 'wceventsfp') . '</p>';
            echo '<p class="description">' . esc_html__('This feature manages extraordinary closures for events and experiences.', 'wceventsfp') . '</p>';
            
            // Provide a basic interface placeholder
            echo '<h2>' . esc_html__('Temporary Closures Interface', 'wceventsfp') . '</h2>';
            echo '<p>' . esc_html__('Coming soon: Interface for managing exceptional closure dates.', 'wceventsfp') . '</p>';
            echo '</div>';
            echo '</div>';
        }
    }
    
    /**
     * Render settings page
     * 
     * @return void
     */
    public function render_settings_page() {
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Impostazioni WCEventsFP', 'wceventsfp') . '</h1>';
        
        // Try to use the new settings system first
        if (class_exists('WCEFP_Admin_Settings')) {
            try {
                $settings = WCEFP_Admin_Settings::get_instance();
                if (method_exists($settings, 'render_settings_page')) {
                    // Remove the wrap div since the settings class includes its own
                    echo '</div>';
                    $settings->render_settings_page();
                    return;
                }
            } catch (\Exception $e) {
                // Log error but continue with fallback
                error_log('WCEventsFP: Settings rendering error: ' . $e->getMessage());
            }
        }
        
        // Fallback: Use WordPress Settings API
        echo '<form method="post" action="options.php">';
        
        // Check if settings are registered
        if (false !== get_option('wcefp_settings', false)) {
            settings_fields('wcefp_settings');
            do_settings_sections('wcefp_settings');
            submit_button();
        } else {
            // Basic settings interface
            echo '<div class="wcefp-settings-placeholder">';
            echo '<h2>' . esc_html__('Basic Settings', 'wceventsfp') . '</h2>';
            echo '<p>' . esc_html__('Settings system is being initialized...', 'wceventsfp') . '</p>';
            echo '<p class="description">' . esc_html__('The full settings interface will be available once all components are loaded.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
        
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Render occurrence management interface
     * 
     * @return void
     */
    private function render_occurrence_interface() {
        echo '<div class="wcefp-occurrence-generator">';
        echo '<h3>' . esc_html__('Genera Occorrenze', 'wceventsfp') . '</h3>';
        echo '<p>' . esc_html__('Use the recurring events system to generate occurrences.', 'wceventsfp') . '</p>';
        
        // Basic form to interface with WCEFP_Recurring
        echo '<form method="post" action="admin-ajax.php" class="wcefp-occurrence-form">';
        wp_nonce_field('wcefp_admin', 'nonce');
        echo '<input type="hidden" name="action" value="wcefp_generate_occurrences" />';
        
        echo '<table class="form-table">';
        echo '<tr><th><label for="product_id">' . esc_html__('Product ID:', 'wceventsfp') . '</label></th>';
        echo '<td><input type="number" name="product_id" id="product_id" class="regular-text" /></td></tr>';
        
        echo '<tr><th><label for="from_date">' . esc_html__('From Date:', 'wceventsfp') . '</label></th>';
        echo '<td><input type="date" name="from" id="from_date" class="regular-text" /></td></tr>';
        
        echo '<tr><th><label for="to_date">' . esc_html__('To Date:', 'wceventsfp') . '</label></th>';
        echo '<td><input type="date" name="to" id="to_date" class="regular-text" /></td></tr>';
        echo '</table>';
        
        echo '<p class="submit"><input type="submit" value="' . esc_attr__('Generate Occurrences', 'wceventsfp') . '" class="button-primary" /></p>';
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * Render bookings list
     * 
     * @param array $bookings List of bookings
     * @return void
     */
    private function render_bookings_list($bookings) {
        if (empty($bookings)) {
            echo '<p>' . esc_html__('No bookings found.', 'wceventsfp') . '</p>';
            return;
        }
        
        echo '<div class="wcefp-bookings-list">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Customer', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Occurrence', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Status', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Date', 'wceventsfp') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($bookings as $booking) {
            echo '<tr>';
            echo '<td>' . esc_html($booking['id'] ?? '') . '</td>';
            echo '<td>' . esc_html($booking['customer_name'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '<td>' . esc_html($booking['occurrence_id'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '<td>' . esc_html($booking['status'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '<td>' . esc_html($booking['created_at'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    /**
     * Render occurrences list
     * 
     * @param array $occurrences List of occurrences
     * @return void
     */
    private function render_occurrences_list($occurrences) {
        if (empty($occurrences)) {
            echo '<p>' . esc_html__('No occurrences found.', 'wceventsfp') . '</p>';
            return;
        }
        
        echo '<div class="wcefp-occurrences-list">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('ID', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Product', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Date & Time', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Capacity', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Booked', 'wceventsfp') . '</th>';
        echo '<th>' . esc_html__('Status', 'wceventsfp') . '</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($occurrences as $occurrence) {
            echo '<tr>';
            echo '<td>' . esc_html($occurrence['id'] ?? '') . '</td>';
            echo '<td>' . esc_html($occurrence['product_title'] ?? get_the_title($occurrence['product_id'] ?? 0)) . '</td>';
            echo '<td>' . esc_html($occurrence['start_datetime'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '<td>' . esc_html($occurrence['capacity'] ?? '0') . '</td>';
            echo '<td>' . esc_html($occurrence['booked'] ?? '0') . '</td>';
            echo '<td>' . esc_html($occurrence['status'] ?? __('N/A', 'wceventsfp')) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    /**
     * Render booking view page
     * 
     * @return void
     */
    public function render_booking_view_page() {
        // Security check
        if (!current_user_can('manage_wcefp_bookings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wcefp_view_booking')) {
            wp_die(__('Security check failed.', 'wceventsfp'));
        }
        
        $booking_id = absint($_GET['booking_id'] ?? 0);
        if (!$booking_id) {
            wp_die(__('Invalid booking ID.', 'wceventsfp'));
        }
        
        // Get booking data (placeholder - would connect to actual data source)
        $booking_data = $this->get_booking_details($booking_id);
        if (!$booking_data) {
            wp_die(__('Booking not found.', 'wceventsfp'));
        }
        
        ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php printf(__('Prenotazione #%d', 'wceventsfp'), $booking_id); ?></h1>
                <a href="<?php echo admin_url('admin.php?page=wcefp-bookings'); ?>" class="page-title-action">
                    <?php _e('← Torna alle Prenotazioni', 'wceventsfp'); ?>
                </a>
            </div>
            
            <div class="wcefp-booking-view">
                <!-- Customer Information -->
                <div class="wcefp-section">
                    <h2><?php _e('Informazioni Cliente', 'wceventsfp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Nome:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['customer_name']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Email:', 'wceventsfp'); ?></th>
                            <td><a href="mailto:<?php echo esc_attr($booking_data['customer_email']); ?>"><?php echo esc_html($booking_data['customer_email']); ?></a></td>
                        </tr>
                        <tr>
                            <th><?php _e('Telefono:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['customer_phone'] ?? __('N/A', 'wceventsfp')); ?></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Event Information -->
                <div class="wcefp-section">
                    <h2><?php _e('Dettagli Evento', 'wceventsfp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Evento:', 'wceventsfp'); ?></th>
                            <td>
                                <strong><?php echo esc_html($booking_data['event_title']); ?></strong>
                                <br><small>ID: <?php echo absint($booking_data['event_id']); ?></small>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Data e Ora:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['occurrence_datetime']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Partecipanti:', 'wceventsfp'); ?></th>
                            <td><?php echo absint($booking_data['participants']); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Importo:', 'wceventsfp'); ?></th>
                            <td><strong><?php echo wc_price($booking_data['total_amount']); ?></strong></td>
                        </tr>
                    </table>
                </div>
                
                <!-- Booking Status -->
                <div class="wcefp-section">
                    <h2><?php _e('Stato e Note', 'wceventsfp'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Stato:', 'wceventsfp'); ?></th>
                            <td>
                                <span class="wcefp-status-badge wcefp-status-<?php echo esc_attr($booking_data['status']); ?>">
                                    <?php echo esc_html(ucfirst($booking_data['status'])); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Note:', 'wceventsfp'); ?></th>
                            <td><?php echo nl2br(esc_html($booking_data['notes'] ?? __('Nessuna nota', 'wceventsfp'))); ?></td>
                        </tr>
                        <tr>
                            <th><?php _e('Check-in:', 'wceventsfp'); ?></th>
                            <td>
                                <?php if ($booking_data['checkin_status'] === 'checked_in'): ?>
                                    <span class="wcefp-status-badge wcefp-status-checked-in">
                                        <?php printf(__('✓ Check-in effettuato: %s', 'wceventsfp'), $booking_data['checkin_time']); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="wcefp-status-badge wcefp-status-pending">
                                        <?php _e('In attesa di check-in', 'wceventsfp'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Quick Actions -->
                <div class="wcefp-section">
                    <h2><?php _e('Azioni Rapide', 'wceventsfp'); ?></h2>
                    <div class="wcefp-quick-actions">
                        <?php if ($booking_data['checkin_status'] !== 'checked_in'): ?>
                            <button type="button" 
                                    class="button button-primary wcefp-quick-action" 
                                    data-action="checkin" 
                                    data-booking-id="<?php echo $booking_id; ?>">
                                <?php _e('Segna come Check-in', 'wceventsfp'); ?>
                            </button>
                        <?php endif; ?>
                        
                        <button type="button" 
                                class="button button-secondary wcefp-quick-action" 
                                data-action="resend_email" 
                                data-booking-id="<?php echo $booking_id; ?>">
                            <?php _e('Reinvia Email Conferma', 'wceventsfp'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Extra & Voucher Information -->
                <?php if (!empty($booking_data['extras']) || !empty($booking_data['voucher_code'])): ?>
                <div class="wcefp-section">
                    <h2><?php _e('Extra e Voucher', 'wceventsfp'); ?></h2>
                    <table class="form-table">
                        <?php if (!empty($booking_data['extras'])): ?>
                        <tr>
                            <th><?php _e('Extra:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['extras']); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($booking_data['voucher_code'])): ?>
                        <tr>
                            <th><?php _e('Voucher:', 'wceventsfp'); ?></th>
                            <td><?php echo esc_html($booking_data['voucher_code']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Add JavaScript for quick actions -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.wcefp-quick-action').on('click', function() {
                var $button = $(this);
                var action = $button.data('action');
                var bookingId = $button.data('booking-id');
                
                if (!confirm('<?php _e("Sei sicuro di voler eseguire questa azione?", "wceventsfp"); ?>')) {
                    return;
                }
                
                $button.prop('disabled', true).text('<?php _e("Elaborazione...", "wceventsfp"); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wcefp_booking_quick_action',
                        booking_action: action,
                        booking_id: bookingId,
                        nonce: '<?php echo wp_create_nonce("wcefp_quick_action"); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert(response.data || '<?php _e("Errore durante l\\'operazione", "wceventsfp"); ?>');
                            $button.prop('disabled', false).text($button.data('original-text'));
                        }
                    },
                    error: function() {
                        alert('<?php _e("Errore di connessione", "wceventsfp"); ?>');
                        $button.prop('disabled', false).text($button.data('original-text'));
                    }
                });
            });
            
            // Store original button text
            $('.wcefp-quick-action').each(function() {
                $(this).data('original-text', $(this).text());
            });
        });
        </script>
        
        <style>
        .wcefp-booking-view {
            background: #fff;
            padding: 20px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            margin-top: 20px;
        }
        .wcefp-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f0f0f1;
        }
        .wcefp-section:last-child {
            border-bottom: none;
        }
        .wcefp-section h2 {
            margin-top: 0;
            color: #1e1e1e;
        }
        .wcefp-quick-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .wcefp-status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        .wcefp-status-confirmed { background-color: #48bb78; }
        .wcefp-status-pending { background-color: #f56565; }
        .wcefp-status-completed { background-color: #38b2ac; }
        .wcefp-status-cancelled { background-color: #a0aec0; }
        .wcefp-status-checked-in { background-color: #48bb78; }
        </style>
        <?php
    }
    
    /**
     * Get booking details for view page
     * 
     * @param int $booking_id
     * @return array|false
     */
    private function get_booking_details($booking_id) {
        // Placeholder implementation - in real application this would query the database
        // For now, return sample data that matches the booking structure
        
        $sample_bookings = [
            1 => [
                'id' => 1,
                'customer_name' => 'Mario Rossi',
                'customer_email' => 'mario@example.com',
                'customer_phone' => '+39 123 456 7890',
                'event_title' => 'Wine Tasting Experience',
                'event_id' => 123,
                'occurrence_datetime' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime('+7 days 18:00')),
                'participants' => 2,
                'status' => 'confirmed',
                'total_amount' => 89.90,
                'notes' => 'Preferisce vini rossi. Anniversario di matrimonio.',
                'checkin_status' => 'pending',
                'checkin_time' => null,
                'extras' => 'Degustazione formaggi (+15€)',
                'voucher_code' => 'WELCOME2024',
                'created_at' => date('Y-m-d H:i:s', strtotime('-3 days'))
            ],
            2 => [
                'id' => 2,
                'customer_name' => 'Anna Bianchi',
                'customer_email' => 'anna@example.com',
                'customer_phone' => '+39 098 765 4321',
                'event_title' => 'Cooking Class',
                'event_id' => 124,
                'occurrence_datetime' => wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime('+14 days 19:30')),
                'participants' => 4,
                'status' => 'pending',
                'total_amount' => 179.80,
                'notes' => 'Gruppo vegetariano, nessuna allergia.',
                'checkin_status' => 'pending',
                'checkin_time' => null,
                'extras' => null,
                'voucher_code' => null,
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
            ]
        ];
        
        return $sample_bookings[$booking_id] ?? false;
    }
    
    /**
     * Handle booking quick actions via AJAX
     * 
     * @return void
     */
    public function handle_booking_quick_action() {
        // Security checks
        if (!check_ajax_referer('wcefp_quick_action', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_wcefp_bookings')) {
            wp_send_json_error(__('Insufficient permissions.', 'wceventsfp'));
        }
        
        $booking_id = absint($_POST['booking_id'] ?? 0);
        $booking_action = sanitize_text_field($_POST['booking_action'] ?? '');
        
        if (!$booking_id || !$booking_action) {
            wp_send_json_error(__('Invalid parameters.', 'wceventsfp'));
        }
        
        switch ($booking_action) {
            case 'checkin':
                $result = $this->mark_booking_checkin($booking_id);
                break;
                
            case 'resend_email':
                $result = $this->resend_booking_email($booking_id);
                break;
                
            default:
                wp_send_json_error(__('Unknown action.', 'wceventsfp'));
        }
        
        if ($result) {
            wp_send_json_success(__('Action completed successfully.', 'wceventsfp'));
        } else {
            wp_send_json_error(__('Action failed.', 'wceventsfp'));
        }
    }
    
    /**
     * Mark booking as checked in
     * 
     * @param int $booking_id
     * @return bool
     */
    private function mark_booking_checkin($booking_id) {
        // Placeholder implementation - would update database
        // For now, just simulate success
        
        // In real implementation:
        // update_post_meta($booking_id, '_wcefp_checkin_status', 'checked_in');
        // update_post_meta($booking_id, '_wcefp_checkin_time', current_time('mysql'));
        
        return true;
    }
    
    /**
     * Resend booking confirmation email
     * 
     * @param int $booking_id
     * @return bool
     */
    private function resend_booking_email($booking_id) {
        // Placeholder implementation - would send email
        // For now, just simulate success
        
        // In real implementation:
        // $booking_data = $this->get_booking_details($booking_id);
        // $email_manager = new EmailManager();
        // return $email_manager->send_booking_confirmation($booking_id, $booking_data);
        
        return true;
    }
    
    /**
     * Render booking calendar page
     * 
     * @return void
     */
    public function render_booking_calendar_page() {
        // Security check
        if (!current_user_can('manage_wcefp_bookings')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wceventsfp'));
        }
        
        ?>
        <div class="wrap">
            <div class="wcefp-page-header">
                <h1><?php _e('Calendario Prenotazioni', 'wceventsfp'); ?></h1>
                <p class="wcefp-page-description">
                    <?php _e('Visualizza tutte le prenotazioni in un\'interfaccia calendario intuitiva.', 'wceventsfp'); ?>
                </p>
                <div class="wcefp-page-meta">
                    <span class="wcefp-page-badge">
                        📅 <?php _e('Vista Calendario', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-page-badge">
                        📋 <?php _e('Gestione Prenotazioni', 'wceventsfp'); ?>
                    </span>
                </div>
            </div>
            
            <div class="wcefp-calendar-container">
                <div class="wcefp-calendar-toolbar">
                    <div class="wcefp-calendar-nav">
                        <button type="button" class="button" id="wcefp-calendar-prev">
                            <?php _e('← Precedente', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button" id="wcefp-calendar-today">
                            <?php _e('Oggi', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button" id="wcefp-calendar-next">
                            <?php _e('Successivo →', 'wceventsfp'); ?>
                        </button>
                    </div>
                    
                    <div class="wcefp-calendar-views">
                        <button type="button" class="button button-primary wcefp-calendar-view" data-view="month">
                            <?php _e('Mese', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button wcefp-calendar-view" data-view="agendaWeek">
                            <?php _e('Settimana', 'wceventsfp'); ?>
                        </button>
                        <button type="button" class="button wcefp-calendar-view" data-view="agendaDay">
                            <?php _e('Giorno', 'wceventsfp'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="wcefp-booking-calendar" class="wcefp-calendar-widget"></div>
            </div>
            
            <!-- Booking Legend -->
            <div class="wcefp-calendar-legend">
                <h3><?php _e('Legenda:', 'wceventsfp'); ?></h3>
                <div class="wcefp-legend-items">
                    <span class="wcefp-legend-item">
                        <span class="wcefp-color-box" style="background-color: #48bb78;"></span>
                        <?php _e('Confermato', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-legend-item">
                        <span class="wcefp-color-box" style="background-color: #f56565;"></span>
                        <?php _e('In Attesa', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-legend-item">
                        <span class="wcefp-color-box" style="background-color: #38b2ac;"></span>
                        <?php _e('Completato', 'wceventsfp'); ?>
                    </span>
                    <span class="wcefp-legend-item">
                        <span class="wcefp-color-box" style="background-color: #a0aec0;"></span>
                        <?php _e('Annullato', 'wceventsfp'); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Load FullCalendar if not already loaded -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css">
        <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize FullCalendar for bookings
            var calendarEl = document.getElementById('wcefp-booking-calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'it',
                firstDay: 1, // Monday
                headerToolbar: false, // We use custom toolbar
                height: 'auto',
                events: function(fetchInfo, successCallback, failureCallback) {
                    // Get booking events via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wcefp_get_booking_calendar_events',
                            start: fetchInfo.startStr,
                            end: fetchInfo.endStr,
                            nonce: '<?php echo wp_create_nonce("wcefp_calendar_events"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                successCallback(response.data);
                            } else {
                                failureCallback(response.data || 'Error loading events');
                            }
                        },
                        error: function() {
                            failureCallback('Network error');
                        }
                    });
                },
                eventClick: function(info) {
                    // Open booking details
                    if (info.event.extendedProps.booking_id) {
                        var viewUrl = '<?php echo admin_url("admin.php?page=wcefp-booking-view&booking_id="); ?>' + 
                                     info.event.extendedProps.booking_id + 
                                     '&_wpnonce=<?php echo wp_create_nonce("wcefp_view_booking"); ?>';
                        window.location.href = viewUrl;
                    }
                },
                eventDidMount: function(info) {
                    // Add tooltips with booking details
                    $(info.el).attr('title', info.event.extendedProps.tooltip || info.event.title);
                }
            });
            
            calendar.render();
            
            // Custom toolbar handlers
            $('#wcefp-calendar-prev').on('click', function() {
                calendar.prev();
            });
            
            $('#wcefp-calendar-next').on('click', function() {
                calendar.next();
            });
            
            $('#wcefp-calendar-today').on('click', function() {
                calendar.today();
            });
            
            $('.wcefp-calendar-view').on('click', function() {
                var view = $(this).data('view');
                $('.wcefp-calendar-view').removeClass('button-primary').addClass('button-secondary');
                $(this).removeClass('button-secondary').addClass('button-primary');
                calendar.changeView(view);
            });
        });
        </script>
        
        <style>
        .wcefp-calendar-container {
            background: #fff;
            padding: 20px;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            margin-top: 20px;
        }
        .wcefp-calendar-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f0f0f1;
        }
        .wcefp-calendar-nav {
            display: flex;
            gap: 5px;
        }
        .wcefp-calendar-views {
            display: flex;
            gap: 5px;
        }
        .wcefp-calendar-widget {
            min-height: 600px;
        }
        .wcefp-calendar-legend {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .wcefp-legend-items {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .wcefp-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }
        .wcefp-color-box {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }
        .wcefp-page-header {
            margin-bottom: 0;
        }
        .wcefp-page-description {
            font-size: 14px;
            color: #646970;
            margin: 10px 0;
        }
        .wcefp-page-meta {
            display: flex;
            gap: 10px;
            margin: 10px 0;
        }
        .wcefp-page-badge {
            background: #f0f0f1;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            color: #50575e;
        }
        </style>
        <?php
    }
    
    /**
     * Handle calendar events AJAX request
     * 
     * @return void
     */
    public function handle_get_calendar_events() {
        // Security checks
        if (!check_ajax_referer('wcefp_calendar_events', 'nonce', false)) {
            wp_send_json_error(__('Security check failed.', 'wceventsfp'));
        }
        
        if (!current_user_can('manage_wcefp_bookings')) {
            wp_send_json_error(__('Insufficient permissions.', 'wceventsfp'));
        }
        
        $start = sanitize_text_field($_POST['start'] ?? '');
        $end = sanitize_text_field($_POST['end'] ?? '');
        
        // Get booking events for calendar (placeholder implementation)
        $events = $this->get_booking_calendar_events($start, $end);
        
        wp_send_json_success($events);
    }
    
    /**
     * Get booking events for calendar display
     * 
     * @param string $start Start date
     * @param string $end End date
     * @return array
     */
    private function get_booking_calendar_events($start, $end) {
        // Placeholder implementation - would query actual booking data
        $sample_events = [
            [
                'id' => 'booking-1',
                'title' => 'Wine Tasting - Mario Rossi (2 pax)',
                'start' => date('Y-m-d\TH:i:s', strtotime('+7 days 18:00')),
                'backgroundColor' => '#48bb78',
                'borderColor' => '#38a169',
                'extendedProps' => [
                    'booking_id' => 1,
                    'customer_name' => 'Mario Rossi',
                    'status' => 'confirmed',
                    'participants' => 2,
                    'tooltip' => 'Wine Tasting Experience - Mario Rossi (2 partecipanti) - Confermato'
                ]
            ],
            [
                'id' => 'booking-2',
                'title' => 'Cooking Class - Anna Bianchi (4 pax)',
                'start' => date('Y-m-d\TH:i:s', strtotime('+14 days 19:30')),
                'backgroundColor' => '#f56565',
                'borderColor' => '#e53e3e',
                'extendedProps' => [
                    'booking_id' => 2,
                    'customer_name' => 'Anna Bianchi',
                    'status' => 'pending',
                    'participants' => 4,
                    'tooltip' => 'Cooking Class - Anna Bianchi (4 partecipanti) - In Attesa'
                ]
            ]
        ];
        
        // Filter events by date range if provided
        if ($start && $end) {
            $start_timestamp = strtotime($start);
            $end_timestamp = strtotime($end);
            
            $sample_events = array_filter($sample_events, function($event) use ($start_timestamp, $end_timestamp) {
                $event_timestamp = strtotime($event['start']);
                return $event_timestamp >= $start_timestamp && $event_timestamp <= $end_timestamp;
            });
        }
        
        return array_values($sample_events);
    }
}