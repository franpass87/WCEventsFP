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
        
        // Only register functional classes - remove empty stubs
        // TODO: Implement SettingsManager and DashboardWidgets when needed
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
        
        // Initialize only functional admin services
        $this->container->get('admin.menu');
        $this->container->get('admin.product');
    }

}