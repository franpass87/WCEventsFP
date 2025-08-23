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
}