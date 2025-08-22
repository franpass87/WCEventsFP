<?php
/**
 * Features Service Provider
 * 
 * @package WCEFP
 * @subpackage Features
 * @since 2.0.1
 */

namespace WCEFP\Features;

use WCEFP\Core\ServiceProvider;
use WCEFP\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers feature-related services
 */
class FeaturesServiceProvider extends \WCEFP\Core\ServiceProvider {
    
    /**
     * Register services in the container
     * 
     * @return void
     */
    public function register() {
        // Load stub classes
        require_once __DIR__ . '/Stubs.php';
        
        // Register feature classes
        $this->container->singleton('features.vouchers', function($container) {
            // Wrap existing WCEFP_Gift class
            return new VoucherManager();
        });
        
        $this->container->singleton('features.analytics', function($container) {
            return new AnalyticsTracker();
        });
        
        $this->container->singleton('features.notifications', function($container) {
            return new NotificationSystem();
        });
        
        $this->container->singleton('features.caching', function($container) {
            // Wrap existing WCEFP_Cache class
            return new CacheManager();
        });
        
        $this->container->singleton('features.security', function($container) {
            return new SecurityManager();
        });
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot() {
        // Initialize core features
        $this->container->get('features.analytics');
        $this->container->get('features.security');
        $this->container->get('features.caching');
    }
}