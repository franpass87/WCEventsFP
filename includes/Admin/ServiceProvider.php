<?php
/**
 * Admin Service Provider
 * 
 * @package WCEFP
 * @subpackage Admin
 * @since 2.0.1
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
        
        // Load stub classes
        require_once __DIR__ . '/Stubs.php';
        
        // Register admin classes with the container
        $this->container->singleton('admin.menu', function($container) {
            return new MenuManager($container);
        });
        
        $this->container->singleton('admin.product', function($container) {
            return new ProductAdmin($container);
        });
        
        $this->container->singleton('admin.settings', function($container) {
            return new SettingsManager($container);
        });
        
        $this->container->singleton('admin.dashboard', function($container) {
            return new DashboardWidgets($container);
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
        
        // Initialize admin services conditionally
        // Only initialize new menu manager if no existing WCEFP admin menu exists
        if (!$this->has_existing_admin_menu()) {
            $this->container->get('admin.menu');
        }
        
        // Always initialize product admin and settings
        $this->container->get('admin.product');
        $this->container->get('admin.settings');
        $this->container->get('admin.dashboard');
    }
    
    /**
     * Check if existing admin menu system is already present
     * 
     * @return bool
     */
    private function has_existing_admin_menu() {
        // Check if legacy classes have already added admin menus
        return class_exists('WCEFP_Channel_Management') || 
               class_exists('WCEFP_Resource_Management') ||
               class_exists('WCEFP_Commission_Management');
    }
}