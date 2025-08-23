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
    }
    
    /**
     * Boot services
     */
    public function boot() {
        // Initialize export manager
        $this->container->get('export.manager')->init();
        
        // Initialize Gutenberg block manager
        $this->container->get('gutenberg.manager')->init();
        
        // Add admin menu items
        add_action('admin_menu', [$this, 'add_admin_menus'], 20);
        
        // Register AJAX handlers
        add_action('wp_ajax_wcefp_export_bookings', [$this, 'handle_export_bookings']);
        add_action('wp_ajax_wcefp_export_calendar', [$this, 'handle_export_calendar']);
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
    }
    
    /**
     * Render export page
     */
    public function render_export_page() {
        $export_manager = $this->container->get('export.manager');
        include WCEFP_PLUGIN_DIR . 'admin/views/export-page.php';
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
}