<?php
/**
 * Admin Service Provider
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.1.1
 */

namespace WCEFP\Admin;

use WCEFP\Core\ServiceProvider;
use WCEFP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers admin-related services
 */
class AdminServiceProvider extends \WCEFP\Core\ServiceProvider {
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    public function register() {
        if (!is_admin()) {
            return;
        }
        
        // Register admin classes with the container
        $this->container->singleton('admin.menu', function($container) {
            return new MenuManager($container);
        });
        
        $this->container->singleton('admin.product', function($container) {
            return new ProductAdmin($container);
        });
        
        $this->container->singleton('admin.meeting_points', function($container) {
            return new MeetingPointsManager();
        });
        
        // Additional admin services
        $this->container->singleton('admin.settings', function($container) {
            return new SettingsManager($container);
        });
        
        $this->container->singleton('admin.dashboard', function($container) {
            return new DashboardWidgets($container);
        });
        
        $this->container->singleton('admin.features', function($container) {
            return new FeatureManager($container);
        });
        
        $this->container->singleton('admin.diagnostics', function($container) {
            return new DiagnosticsPage();
        });
        
        $this->container->singleton('admin.advanced_analytics', function($container) {
            return new AdvancedAnalyticsPage();
        });
        
        $this->container->singleton('admin.health_monitoring', function($container) {
            return new HealthMonitoringSystem();
        });
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot() {
        if (!is_admin()) {
            return;
        }
        
        // Initialize admin services (conditionally)
        $this->container->get('admin.menu');
        $this->container->get('admin.product');
        $this->container->get('admin.meeting_points');
        
        // Initialize additional services if classes exist
        if (class_exists('\WCEFP\Admin\SettingsManager')) {
            $this->container->get('admin.settings');
        }
        
        if (class_exists('\WCEFP\Admin\DashboardWidgets')) {
            $this->container->get('admin.dashboard');
        }
        
        if (class_exists('\WCEFP\Admin\FeatureManager')) {
            $this->container->get('admin.features');
        }
        
        if (class_exists('\WCEFP\Admin\DiagnosticsPage')) {
            $this->container->get('admin.diagnostics');
        }
        
        if (class_exists('\WCEFP\Admin\AdvancedAnalyticsPage')) {
            $this->container->get('admin.advanced_analytics');
        }
        
        if (class_exists('\WCEFP\Admin\HealthMonitoringSystem')) {
            $this->container->get('admin.health_monitoring');
        }
        
        // Register AJAX handlers for legacy classes
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     * 
     * @return void
     */
    private function register_ajax_handlers() {
        // AJAX handler for generating occurrences
        if (class_exists('WCEFP_Recurring')) {
            add_action('wp_ajax_wcefp_generate_occurrences', ['WCEFP_Recurring', 'ajax_generate_occurrences']);
        }
        
        // AJAX handlers for closures management
        if (class_exists('WCEFP_Closures')) {
            add_action('wp_ajax_wcefp_add_closure', ['WCEFP_Closures', 'ajax_add_closure']);
            add_action('wp_ajax_wcefp_delete_closure', ['WCEFP_Closures', 'ajax_delete_closure']);
            add_action('wp_ajax_wcefp_list_closures', ['WCEFP_Closures', 'ajax_list_closures']);
        }
        
        // AJAX handlers for meeting points
        if (class_exists('WCEFP_MeetingPoints_CPT')) {
            add_action('wp_ajax_wcefp_get_meeting_points', ['WCEFP_MeetingPoints_CPT', 'ajax_get_meeting_points']);
        }
        
        // AJAX handlers for extra services
        if (class_exists('WCEFP_Extra_Services')) {
            add_action('wp_ajax_wcefp_update_extra_services_price', ['WCEFP_Extra_Services', 'ajax_update_price']);
            add_action('wp_ajax_nopriv_wcefp_update_extra_services_price', ['WCEFP_Extra_Services', 'ajax_update_price']);
        }
    }

}