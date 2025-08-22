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
        
        // Initialize admin services
        $this->container->get('admin.menu');
        $this->container->get('admin.product');
        $this->container->get('admin.settings');
        $this->container->get('admin.dashboard');
    }
}