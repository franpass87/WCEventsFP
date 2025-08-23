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
        // TODO: Integrate with existing occurrence management
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
        // TODO: Integrate with existing booking management
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
        // TODO: Integrate with existing voucher management
        echo '</div>';
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
}