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
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
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
        
        // Integrate with existing occurrence management
        if (class_exists('WCEFP_Recurring')) {
            // Show occurrence generation interface
            echo '<div class="wcefp-occurrences-manager">';
            $this->render_occurrence_interface();
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('Occurrence management not available.', 'wceventsfp') . '</p>';
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
        
        // Integrate with existing booking management using QueryBuilder
        try {
            $query_builder = $this->container->get('query_builder');
            if ($query_builder) {
                $bookings = $query_builder->get_bookings();
                $this->render_bookings_list($bookings);
            } else {
                echo '<p>' . esc_html__('Booking management not available.', 'wceventsfp') . '</p>';
            }
        } catch (\Exception $e) {
            echo '<p>' . esc_html__('Error loading bookings:', 'wceventsfp') . ' ' . esc_html($e->getMessage()) . '</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Render vouchers page
     * 
     * @return void
     */
    public function render_vouchers_page() {
        // Integrate with existing voucher management
        if (class_exists('WCEFP_Vouchers_Admin')) {
            // Use existing voucher admin functionality
            WCEFP_Vouchers_Admin::dispatch();
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Vouchers', 'wceventsfp') . '</h1>';
            echo '<p>' . esc_html__('Voucher management not available.', 'wceventsfp') . '</p>';
            echo '</div>';
        }
    }
    
    /**
     * Render closures page
     * 
     * @return void
     */
    public function render_closures_page() {
        if (class_exists('WCEFP_Closures')) {
            WCEFP_Closures::render_admin_page();
        } else {
            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Chiusure', 'wceventsfp') . '</h1>';
            echo '<p>' . esc_html__('Closures management not available.', 'wceventsfp') . '</p>';
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
        echo '<form method="post" action="options.php">';
        settings_fields('wcefp_settings');
        do_settings_sections('wcefp_settings');
        submit_button();
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
}