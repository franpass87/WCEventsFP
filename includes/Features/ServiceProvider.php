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
        
        // Only register classes that have actual implementations
        // Voucher and Cache managers wrap existing legacy classes
        $this->container->singleton('features.vouchers', function($container) {
            // Wrap existing WCEFP_Gift class
            return new VoucherManager();
        });
        
        $this->container->singleton('features.caching', function($container) {
            // Wrap existing WCEFP_Cache class
            return new CacheManager();
        });
        
        // TODO: Implement and register additional features when ready:
        // - AnalyticsTracker
        // - NotificationSystem  
        // - SecurityManager
    }
    
    /**
     * Boot services
     * 
     * @return void
     */
    public function boot() {
        // Initialize only implemented features
        $this->container->get('features.caching');
        // $this->container->get('features.vouchers'); // Initialize when needed
    }
}